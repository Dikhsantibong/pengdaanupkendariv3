<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementChecklistFile;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use App\Support\FinanceLetters;
use App\Support\UploadedFileType;
use App\Support\WordDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files that evidence a Penyerahan or Pembayaran requirement: every
 * such step is finished by uploading its paper, not by a bare tick.
 */
class ChecklistFileController extends Controller
{
    /**
     * Where the files are kept on the local disk.
     */
    protected const DIRECTORY = 'checklist-berkas';

    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Upload one or more files on the step.
     */
    public function store(Request $request, Procurement $procurement, ProcurementChecklist $checklist): RedirectResponse
    {
        $this->authorizeStep($procurement, $checklist);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [], ['files' => 'berkas', 'files.*' => 'berkas']);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        $stored = DB::transaction(fn () => collect($files)->map(function (UploadedFile $file) use ($request, $checklist): ProcurementChecklistFile {
            // The stored name and type come from the content, never from what
            // the browser reported.
            $extension = UploadedFileType::extension($file);
            $path = $file->storeAs(
                self::DIRECTORY.'/'.$checklist->id,
                Str::random(20).'.'.$extension,
                'local',
            );

            return $checklist->files()->create([
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime' => UploadedFileType::mime($file),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }));

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::ChecklistDiperbarui,
            "{$stored->count()} berkas {$checklist->checklistItem->name} diunggah: {$stored->pluck('file_name')->implode(', ')}.",
            ['stage' => $checklist->stage->value, 'checklist_id' => $checklist->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$stored->count()} berkas tersimpan."]);

        return back();
    }

    /**
     * Show a file, inline so it can be read in place.
     */
    public function show(Request $request, Procurement $procurement, ProcurementChecklist $checklist, ProcurementChecklistFile $file): StreamedResponse
    {
        abort_unless($checklist->procurement_id === $procurement->id && $file->procurement_checklist_id === $checklist->id, 404);
        $this->authorize('view', $procurement);

        return Storage::disk('local')->response(
            $file->path,
            $file->file_name,
            UploadedFileType::headers($file->mime),
            UploadedFileType::disposition($file->mime, ! $request->boolean('download')),
        );
    }

    /**
     * Download the partner's letter the step is evidenced by (Surat Jalan,
     * Berita Acara, Surat Permohonan Pembayaran and the like) as Word,
     * filled in with the contract's data, to be put on the partner's
     * letterhead, signed, and uploaded back on the step.
     */
    public function template(Procurement $procurement, ProcurementChecklist $checklist, DocumentGenerator $documents): StreamedResponse
    {
        abort_unless($checklist->procurement_id === $procurement->id, 404);
        $this->authorize('view', $procurement);

        $code = FinanceLetters::codeFor($checklist);
        $template = $code === null ? null : FinanceLetters::template($procurement, $code);
        abort_if($code === null || $template === null, 404);

        $letter = WordDocument::fromHtml(
            $documents->renderFinanceLetter($template, $procurement, $checklist),
            FinanceLetters::LETTERS[$code]['name'],
        );

        return response()->streamDownload(
            function () use ($letter): void {
                echo $letter;
            },
            FinanceLetters::fileName($procurement, $code),
            ['Content-Type' => 'application/msword; charset=UTF-8'],
        );
    }

    /**
     * Remove a file uploaded by mistake. A ticked step left without files
     * is unticked, so it cannot stay finished without its evidence.
     */
    public function destroy(Request $request, Procurement $procurement, ProcurementChecklist $checklist, ProcurementChecklistFile $file): RedirectResponse
    {
        $this->authorizeStep($procurement, $checklist);
        abort_unless($file->procurement_checklist_id === $checklist->id, 404);

        Storage::disk('local')->delete($file->path);
        $file->delete();

        if ($checklist->is_completed && ! $checklist->checklistItem->is_optional && ! $checklist->files()->exists()) {
            $checklist->update(['is_completed' => false, 'completed_at' => null, 'completed_by' => null]);
        }

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::ChecklistDiperbarui,
            "Berkas {$file->file_name} pada {$checklist->checklistItem->name} dihapus.",
            ['stage' => $checklist->stage->value, 'checklist_id' => $checklist->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas dihapus.']);

        return back();
    }

    /**
     * Only a step of this procurement that is evidenced by its own upload,
     * and only for who works through that stage.
     */
    protected function authorizeStep(Procurement $procurement, ProcurementChecklist $checklist): void
    {
        abort_unless($checklist->procurement_id === $procurement->id, 404);
        abort_unless($checklist->needsOwnUpload(), 404);

        $this->authorize('updateFinanceChecklist', $procurement);
    }
}
