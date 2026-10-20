<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The RKS templates, with the old cover each one opened with.
     *
     * @var array<string, array{file: string, old_cover: string|null}>
     */
    private const TEMPLATES = [
        'rks.spk' => ['file' => 'rks/metode-spk.html', 'old_cover' => '#<section class="cover">.*?</section>#s'],
        'rks.tender' => ['file' => 'rks/metode-tender.html', 'old_cover' => '#<section class="cover">.*?</section>#s'],
        'pj.rks-jasa' => ['file' => 'pj/rks-jasa.html', 'old_cover' => '#<div class="cover">.*?<div class="foot">.*?</div>\s*</div>#s'],
        'umum.rks' => ['file' => 'umum/rks.html', 'old_cover' => null],
    ];

    /**
     * The new cover of every RKS: the blue stripes, the title, number and
     * date, the logo, the work, and Unit Pembangkitan Kendari with the year
     * on the band at the foot. Templates an administrator has changed keep
     * their changes; only their cover is swapped.
     */
    public function up(): void
    {
        if (! DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            return;
        }

        $outcomes = app(DocumentTemplateLibrary::class)->sync(keys: array_keys(self::TEMPLATES));

        foreach (self::TEMPLATES as $key => $template) {
            if (($outcomes[$key] ?? null) !== DocumentTemplateLibrary::CUSTOMISED || $template['old_cover'] === null) {
                continue;
            }

            $cover = $this->newCover($template['file']);

            if ($cover === null) {
                continue;
            }

            DB::table('document_templates')
                ->where('library_key', $key)
                ->whereNull('deleted_at')
                ->get(['id', 'body'])
                ->each(function (object $row) use ($template, $cover): void {
                    $body = (string) $row->body;

                    if (preg_match($template['old_cover'], $body) !== 1) {
                        return;
                    }

                    $body = (string) preg_replace($template['old_cover'], trim($cover), $body, 1);
                    // The rules of the old framed cover.
                    $body = (string) preg_replace('#^[ \t]*\.rks \.cover[^\n]*\n#m', '', $body);

                    DB::table('document_templates')->where('id', $row->id)->update([
                        'body' => $body,
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    /**
     * The cover as the template in the library draws it.
     */
    private function newCover(string $file): ?string
    {
        $library = (string) file_get_contents(resource_path('document-templates/'.$file));
        $pattern = '#<(section|div) class="cover" style="padding: 0;[^"]*">.*?UNIT PEMBANGKITAN KENDARI<br>\{\{tahun\}\}</div>\s*</div>\s*</\1>#s';

        return preg_match($pattern, $library, $match) === 1 ? $match[0] : null;
    }
};
