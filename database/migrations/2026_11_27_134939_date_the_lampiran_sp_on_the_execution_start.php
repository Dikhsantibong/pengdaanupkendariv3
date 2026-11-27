<?php

use App\Models\Procurement;
use App\Services\DocumentGenerator;
use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Lampiran SP (Syarat Umum) is dated like its Surat Pesanan: on the start
 * date given on the "Rentang Waktu Pelaksanaan" step, not the day the
 * document was generated.
 *
 * Every Lampiran SP template has its heading's date placeholder swapped,
 * keeping any other edit; an untouched template stays recognised as such.
 * Unedited documents follow the template on their own; an edited one, not
 * yet signed, has the heading's date turned into the live start date.
 */
return new class extends Migration
{
    private const OLD = 'Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_dokumen}}';

    private const NEW = 'Surat Pesanan (SP) Nomor {{nomor_pengadaan}} tanggal {{tanggal_mulai_pelaksanaan}}';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $typeIds = DB::table('document_types')->where('code', 'like', 'lampiran-sp%')
            ->where('code', 'not like', 'lampiran-spk%')
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return;
        }

        $this->swapTemplates($typeIds->all(), self::OLD, self::NEW);
        $this->redateEditedDocuments($typeIds->all());
    }

    /**
     * Reverse the migrations.
     *
     * The templates go back to the generation date; documents keep their text.
     */
    public function down(): void
    {
        $typeIds = DB::table('document_types')->where('code', 'like', 'lampiran-sp%')
            ->where('code', 'not like', 'lampiran-spk%')
            ->pluck('id');

        $this->swapTemplates($typeIds->all(), self::NEW, self::OLD);
    }

    /**
     * Swap the heading's placeholder in every template of these types.
     *
     * @param  list<int>  $typeIds
     */
    private function swapTemplates(array $typeIds, string $from, string $to): void
    {
        $library = app(DocumentTemplateLibrary::class);

        foreach (DB::table('document_templates')->whereIn('document_type_id', $typeIds)->get(['id', 'body', 'library_checksum']) as $template) {
            if (! str_contains((string) $template->body, $from)) {
                continue;
            }

            $body = str_replace($from, $to, (string) $template->body);
            $untouched = $template->library_checksum !== null
                && $library->checksum((string) $template->body) === $template->library_checksum;

            $changes = ['body' => $body, 'updated_at' => now()];

            // An untouched default stays recognised as one.
            if ($untouched) {
                $changes['library_checksum'] = $library->checksum($body);
            }

            DB::table('document_templates')->where('id', $template->id)->update($changes);
        }
    }

    /**
     * Date the heading of each edited, unsigned Lampiran SP on its start date.
     *
     * @param  list<int>  $typeIds
     */
    private function redateEditedDocuments(array $typeIds): void
    {
        $generator = app(DocumentGenerator::class);

        $documents = DB::table('procurement_documents')
            ->whereIn('document_type_id', $typeIds)
            ->where('revision', '>', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('procurement_document_uploads')
                ->whereColumn('procurement_document_uploads.procurement_document_id', 'procurement_documents.id'))
            ->get(['id', 'procurement_id', 'rendered_body']);

        foreach ($documents as $document) {
            $procurement = Procurement::query()->withTrashed()->find($document->procurement_id);

            if ($procurement === null) {
                continue;
            }

            $date = $generator->placeholderValues($procurement)['tanggal_mulai_pelaksanaan'] ?? '-';
            $field = '<span data-field="tanggal_mulai_pelaksanaan" data-value="'.e($date).'">'.e($date).'</span>';

            // The date that ends the heading "Surat Pesanan (SP) Nomor … tanggal …".
            $body = preg_replace(
                '/(Surat Pesanan \(SP\) Nomor (?:(?!<\/div>).){0,400}?\btanggal\s+)(?:(?!<\/div>)[^<]|<(?!\/div>)[^>]*>)*?(?=\s*<\/div>)/is',
                '${1}'.$field,
                (string) $document->rendered_body,
                1,
            );

            if ($body !== null && $body !== $document->rendered_body) {
                DB::table('procurement_documents')->where('id', $document->id)->update([
                    'rendered_body' => $body,
                    'updated_at' => now(),
                ]);
                Cache::forget('document-live:'.$document->id);
            }
        }
    }
};
