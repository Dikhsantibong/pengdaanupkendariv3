<?php

namespace App\Support;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\SignedDocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The two documents a division files with a work proposal: the TOR (drafted
 * from its template, then uploaded signed) and the Nota Dinas Usulan.
 *
 * They are ordinary procurement documents, so what the proposer uploads also
 * completes the matching planning checklist steps.
 */
class ProposalDocuments
{
    /**
     * The document type codes, with the name shown when one is missing.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'tor' => 'TOR',
        'nota-dinas-usulan' => 'Nota Dinas Usulan',
    ];

    public function __construct(protected SignedDocumentStore $signed) {}

    /**
     * The proposal documents still without an uploaded, signed copy.
     *
     * @return array<int, string>
     */
    public static function missing(Procurement $procurement): array
    {
        $missing = [];

        foreach (self::types() as $type) {
            if ($procurement->signedDocumentFor($type->id) === null) {
                $missing[] = self::TYPES[$type->code];
            }
        }

        return $missing;
    }

    /**
     * File the scans sent with a proposal.
     *
     * The TOR scan joins the TOR drafted from the template; the Nota Dinas
     * gets a document of its own.
     */
    public function file(Procurement $procurement, ?UploadedFile $tor, ?UploadedFile $memo, User $uploader): void
    {
        $files = array_filter(['tor' => $tor, 'nota-dinas-usulan' => $memo]);

        foreach (self::types() as $type) {
            $file = $files[$type->code] ?? null;

            if ($file === null) {
                continue;
            }

            $procurement->unsetRelation('documents');

            $this->signed->store($this->documentFor($procurement, $type, $uploader), [$file], $uploader);
        }
    }

    /**
     * The documents as the checklist document actions render them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function payload(Procurement $procurement): array
    {
        $procurement->loadMissing('documents.signedUploads.uploadedBy');

        $resolvable = DocumentTemplate::documentTypeIdsResolvableFor(
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );

        return self::types()->map(function (DocumentType $type) use ($procurement, $resolvable): array {
            $document = $procurement->documentFor($type->id);

            return [
                'type_id' => $type->id,
                'type_name' => self::TYPES[$type->code],
                'id' => $document?->id,
                'title' => $document?->title,
                'is_signed' => $document?->isSigned() ?? false,
                'uploads' => $document === null ? [] : $document->signedUploads
                    ->map(fn ($upload): array => [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        'size' => $upload->size,
                        'uploaded_by' => $upload->uploadedBy?->name,
                        'uploaded_at' => $upload->created_at?->toDateTimeString(),
                        'url' => route('procurements.documents.signed.show', [$procurement, $document, $upload, 'preview' => 1]),
                        'is_image' => str_starts_with((string) $upload->mime, 'image/'),
                    ])
                    ->values()
                    ->all(),
                'has_template' => in_array($type->id, $resolvable, true),
                'upload_only' => $type->upload_only,
                'is_alternative' => false,
                // The drafted TOR, readable before anything is uploaded.
                'preview_url' => $document !== null && $document->document_template_id !== null
                    ? route('procurements.documents.show', [$procurement, $document, 'preview' => 1])
                    : null,
            ];
        })->values()->all();
    }

    /**
     * The two document types, in the order they are shown.
     *
     * @return Collection<int, DocumentType>
     */
    protected static function types()
    {
        return DocumentType::query()
            ->whereIn('code', array_keys(self::TYPES))
            ->get()
            ->sortBy(fn (DocumentType $type): int => (int) array_search($type->code, array_keys(self::TYPES), true))
            ->values()
            ->toBase();
    }

    /**
     * The document a scan of this type is filed on, created when missing.
     */
    protected function documentFor(Procurement $procurement, DocumentType $type, User $uploader): ProcurementDocument
    {
        return $procurement->documentFor($type->id)
            ?? $procurement->documents()->create([
                'document_type_id' => $type->id,
                'document_template_id' => null,
                'title' => $type->name.' - '.$procurement->name,
                'file_name' => Str::slug($type->code.'-'.$procurement->number).'.pdf',
                'template_version' => 0,
                'rendered_body' => '',
                'generated_by' => $uploader->id,
                'generated_at' => now(),
            ]);
    }
}
