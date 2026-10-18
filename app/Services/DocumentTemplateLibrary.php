<?php

namespace App\Services;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use RuntimeException;

/**
 * The default document templates shipped with the application.
 *
 * The wording lives in plain HTML files under resources/document-templates,
 * described by manifest.php: no template text is kept in PHP code. The files
 * are only the *defaults*. Once installed, a template belongs to the database
 * and is maintained from Data Master -> Template Dokumen; the library never
 * overwrites a template an administrator has changed.
 *
 * Every installed template remembers which library entry it came from
 * (library_key) and the checksum of the file text it was installed from
 * (library_checksum), so a sync can tell an untouched default (safe to
 * update) from a customised one (left alone).
 */
class DocumentTemplateLibrary
{
    /**
     * Sync outcomes.
     */
    public const INSTALLED = 'installed';

    public const UPDATED = 'updated';

    public const UP_TO_DATE = 'up-to-date';

    public const CUSTOMISED = 'customised';

    public const MISSING_TYPE = 'missing-type';

    protected string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? resource_path('document-templates'), '/\\');
    }

    /**
     * The library entries, optionally limited to groups or keys.
     *
     * @param  array<int, string>  $groups
     * @param  array<int, string>  $keys
     * @return array<string, array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}>
     */
    public function entries(array $groups = [], array $keys = []): array
    {
        $path = $this->root.'/manifest.php';

        if (! is_file($path)) {
            throw new RuntimeException("Manifest template tidak ditemukan: {$path}");
        }

        /** @var array<string, array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}> $manifest */
        $manifest = require $path;

        if ($groups !== []) {
            $manifest = array_filter($manifest, fn (array $entry): bool => in_array($entry['group'], $groups, true));
        }

        if ($keys !== []) {
            $manifest = array_intersect_key($manifest, array_flip($keys));
        }

        return $manifest;
    }

    /**
     * The default text of one entry, with line endings normalised.
     */
    public function body(string $key): string
    {
        $entry = $this->entries()[$key] ?? null;

        if ($entry === null) {
            throw new RuntimeException("Template [{$key}] tidak ada di manifest.");
        }

        $path = $this->root.'/'.$entry['file'];

        if (! is_file($path)) {
            throw new RuntimeException("File template [{$key}] tidak ditemukan: {$path}");
        }

        return $this->normalise((string) file_get_contents($path));
    }

    /**
     * The checksum used to recognise an untouched template.
     */
    public function checksum(string $body): string
    {
        return sha1($this->normalise($body));
    }

    /**
     * Install new defaults and refresh untouched ones.
     *
     * - Not installed yet: installed as the active template of its scope,
     *   unless an administrator already keeps their own template there.
     * - Installed and unchanged since: a changed file updates that template
     *   in place, so there is never a second version of it.
     * - Changed by an administrator, or replaced by their own template:
     *   left alone unless $force is given.
     *
     * A generated document keeps its own stored text, so updating the
     * template never changes a document someone has edited.
     *
     * @param  array<int, string>  $groups
     * @param  array<int, string>  $keys
     * @return array<string, string> outcome per entry key
     */
    public function sync(array $groups = [], array $keys = [], bool $force = false, bool $dryRun = false): array
    {
        $outcomes = [];

        foreach ($this->entries($groups, $keys) as $key => $entry) {
            $outcomes[$key] = $this->syncEntry($key, $entry, $force, $dryRun);
        }

        return $outcomes;
    }

    /**
     * Sync a single entry.
     *
     * @param  array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}  $entry
     */
    protected function syncEntry(string $key, array $entry, bool $force, bool $dryRun): string
    {
        $scope = $this->scope($entry);

        if ($scope === null) {
            return self::MISSING_TYPE;
        }

        $body = $this->body($key);
        $checksum = $this->checksum($body);

        $current = DocumentTemplate::query()
            ->where('library_key', $key)
            ->orderByDesc('version')
            ->first();

        $active = $this->inScope(DocumentTemplate::query(), $scope)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if ($current === null) {
            // An administrator's own template already serves this scope.
            if ($active !== null && ! $force) {
                return self::CUSTOMISED;
            }

            if (! $dryRun) {
                $this->install($key, $entry, $scope, $body, $checksum);
            }

            return self::INSTALLED;
        }

        if ($this->checksum($current->body) === $checksum && ($active === null || $active->is($current))) {
            return self::UP_TO_DATE;
        }

        $untouched = $this->checksum($current->body) === $current->library_checksum;
        $stillInUse = $active === null || $active->is($current);

        if (! $force && (! $untouched || ! $stillInUse)) {
            return self::CUSTOMISED;
        }

        if (! $dryRun) {
            $this->refresh($current, $entry, $scope, $body, $checksum);
        }

        return self::UPDATED;
    }

    /**
     * Put the new wording in the installed template itself, so a document
     * keeps a single template of one version, and make it the active one.
     *
     * @param  array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}  $entry
     * @param  array{document_type_id: int, procurement_method_id: int|null, contract_number_format_id: int|null, work_category: string|null}  $scope
     */
    protected function refresh(DocumentTemplate $template, array $entry, array $scope, string $body, string $checksum): DocumentTemplate
    {
        $template->fill([
            ...$scope,
            'name' => $entry['name'],
            'body' => $body,
            'placeholders' => self::placeholdersIn($body),
            'is_active' => true,
            'library_checksum' => $checksum,
        ])->save();

        $this->inScope(DocumentTemplate::query(), $scope)
            ->whereKeyNot($template->getKey())
            ->update(['is_active' => false]);

        return $template;
    }

    /**
     * Install an entry for the first time and make it the active one.
     *
     * @param  array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}  $entry
     * @param  array{document_type_id: int, procurement_method_id: int|null, contract_number_format_id: int|null, work_category: string|null}  $scope
     */
    protected function install(string $key, array $entry, array $scope, string $body, string $checksum): DocumentTemplate
    {
        $version = (int) $this->inScope(DocumentTemplate::query()->withTrashed(), $scope)->max('version') + 1;

        $template = DocumentTemplate::query()->create([
            ...$scope,
            'name' => $entry['name'],
            'version' => $version,
            'body' => $body,
            'placeholders' => self::placeholdersIn($body),
            'is_active' => true,
            'library_key' => $key,
            'library_checksum' => $checksum,
        ]);

        $this->inScope(DocumentTemplate::query(), $scope)
            ->whereKeyNot($template->getKey())
            ->update(['is_active' => false]);

        return $template;
    }

    /**
     * Resolve the codes of an entry to the ids of its scope.
     *
     * @param  array{group: string, file: string, document_type: string, procurement_method: string|null, contract_format: string|null, work_category: string|null, name: string}  $entry
     * @return array{document_type_id: int, procurement_method_id: int|null, contract_number_format_id: int|null, work_category: string|null}|null
     */
    public function scope(array $entry): ?array
    {
        $typeId = DocumentType::query()->where('code', $entry['document_type'])->value('id');

        if ($typeId === null) {
            return null;
        }

        $methodId = $entry['procurement_method'] === null
            ? null
            : ProcurementMethod::query()->where('code', $entry['procurement_method'])->value('id');

        $formatId = $entry['contract_format'] === null
            ? null
            : ContractNumberFormat::query()->where('code', $entry['contract_format'])->value('id');

        // A method or format the entry is written for must exist.
        if (($entry['procurement_method'] !== null && $methodId === null)
            || ($entry['contract_format'] !== null && $formatId === null)) {
            return null;
        }

        return [
            'document_type_id' => (int) $typeId,
            'procurement_method_id' => $methodId === null ? null : (int) $methodId,
            'contract_number_format_id' => $formatId === null ? null : (int) $formatId,
            'work_category' => $entry['work_category'],
        ];
    }

    /**
     * Limit a template query to one scope.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<DocumentTemplate>
     *
     * @param  TQuery  $query
     * @param  array{document_type_id: int, procurement_method_id: int|null, contract_number_format_id: int|null, work_category: string|null}  $scope
     * @return TQuery
     */
    protected function inScope($query, array $scope)
    {
        foreach ($scope as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query;
    }

    /**
     * The placeholder keys a body refers to.
     *
     * @return array<int, string>
     */
    public static function placeholdersIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Normalise line endings so a checkout on Windows matches one on Linux.
     */
    protected function normalise(string $body): string
    {
        return rtrim(str_replace("\r\n", "\n", $body))."\n";
    }
}
