<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared CRUD behaviour for every modular master data resource.
 *
 * Records that are already referenced by a procurement are soft deleted so the
 * historical data of those procurements stays intact.
 */
abstract class MasterDataController extends Controller
{
    /**
     * The Inertia page that renders this resource.
     */
    abstract protected function page(): string;

    /**
     * The human readable singular label of this resource.
     */
    abstract protected function label(): string;

    /**
     * Get the records shown on the management screen.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function records(): array;

    /**
     * Create a new empty record for this resource.
     */
    abstract protected function newRecord(): Model;

    /**
     * Get the validation rules for storing or updating a record.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(?Model $record = null): array;

    /**
     * Validation messages that replace the generated ones.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Extra props sent to the Inertia page.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [];
    }

    /**
     * Show the master data management screen.
     */
    public function index(Request $request): Response
    {
        return Inertia::render($this->page(), [
            'records' => $this->records(),
            ...$this->extraProps(),
        ]);
    }

    /**
     * Store a new master data record.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $this->newRecord()->fill($this->prepare($validated))->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->label().' ditambahkan.']);

        return back();
    }

    /**
     * Update an existing master data record.
     */
    protected function updateRecord(Request $request, Model $record): RedirectResponse
    {
        $validated = $request->validate($this->rules($record), $this->messages());

        $record->fill($this->prepare($validated, $record))->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->label().' diperbarui.']);

        return back();
    }

    /**
     * Soft delete a master data record so historical references stay valid.
     */
    protected function destroyRecord(Model $record): RedirectResponse
    {
        $record->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->label().' dinonaktifkan. Data pengadaan lama tetap utuh.',
        ]);

        return back();
    }

    /**
     * Adjust the validated attributes before they are persisted.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepare(array $validated, ?Model $record = null): array
    {
        return $validated;
    }

    /**
     * Drop the blank rows out of a repeatable list field before validation.
     *
     * A row left empty in the editor is somebody changing their mind, not an
     * error. Pruning after validation would be too late: the per-row required
     * rule would already have rejected the whole form with a message naming an
     * index the author cannot see.
     */
    protected function pruneList(Request $request, string $key): void
    {
        if (! $request->has($key)) {
            return;
        }

        $rows = $request->input($key);

        if (! is_array($rows)) {
            return;
        }

        $request->merge([$key => array_values(array_filter(
            array_map(static fn (mixed $row): string => is_string($row) ? trim($row) : '', $rows),
            static fn (string $row): bool => $row !== '',
        ))]);
    }

    /**
     * Fail validation with a message tied to a specific field.
     *
     * @throws ValidationException
     */
    protected function failValidation(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
