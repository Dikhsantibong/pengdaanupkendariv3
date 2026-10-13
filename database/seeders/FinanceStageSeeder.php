<?php

namespace Database\Seeders;

use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\ChecklistItem;
use App\Models\DocumentType;
use App\Models\ProgressStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The default master data of the Keuangan stage.
 *
 * After execution, the job is handed to Keuangan, who checks the invoice
 * papers, pays the partner and closes the procurement. The steps and the
 * documents they ask for are ordinary master data: an administrator edits
 * them on Data Master → Item Checklist and Jenis Dokumen.
 *
 * Only missing rows are added, so whatever an administrator has changed is
 * kept when this runs again.
 */
class FinanceStageSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The documents filed during the Keuangan stage, all uploaded as scans.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_TYPES = [
        'invoice' => 'Invoice / Surat Tagihan',
        'kuitansi' => 'Kuitansi',
        'bast' => 'Berita Acara Serah Terima (BAST)',
        'faktur-pajak' => 'Faktur Pajak',
        'pengajuan-pembayaran' => 'Dokumen Pengajuan Pembayaran',
        'bukti-pembayaran' => 'Bukti Transfer Pembayaran',
    ];

    /**
     * The Keuangan steps: name, whether optional, and their document codes.
     *
     * @var array<int, array{0: string, 1: bool, 2: array<int, string>}>
     */
    public const STEPS = [
        ['Penerimaan Tagihan', false, ['invoice', 'kuitansi']],
        ['Berita Acara Serah Terima', false, ['bast']],
        ['Faktur Pajak', true, ['faktur-pajak']],
        ['Verifikasi Dokumen Tagihan', false, []],
        ['Pengajuan Pembayaran', false, ['pengajuan-pembayaran']],
        ['Pembayaran ke Mitra', false, ['bukti-pembayaran']],
    ];

    /**
     * The status a procurement is given once it is paid and closed.
     */
    public const CLOSED_STATUS = 'Selesai';

    /**
     * Add the missing Keuangan document types, steps and closing status.
     */
    public function run(): void
    {
        $typeIds = [];
        $sortOrder = (int) DocumentType::query()->max('sort_order');

        foreach (self::DOCUMENT_TYPES as $code => $name) {
            $type = DocumentType::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'stage' => ProcurementStage::Keuangan,
                    'upload_only' => true,
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                ],
            );

            $typeIds[$code] = $type->id;
        }

        foreach (self::STEPS as $index => [$name, $isOptional, $codes]) {
            $item = ChecklistItem::query()->firstOrCreate(
                ['stage' => ProcurementStage::Keuangan->value, 'name' => $name],
                ['is_optional' => $isOptional, 'sort_order' => $index + 1, 'is_active' => true],
            );

            // A step an administrator already linked is left as it is.
            if ($item->wasRecentlyCreated && $codes !== []) {
                $links = [];

                foreach ($codes as $order => $code) {
                    $links[$typeIds[$code]] = ['sort_order' => $order + 1, 'is_alternative' => false];
                }

                $item->documentTypes()->sync($links);
            }
        }

        if (! ProgressStatus::query()->where('name', self::CLOSED_STATUS)->exists()) {
            $slug = 'selesai';

            while (DB::table('progress_statuses')->where('slug', $slug)->exists()) {
                $slug .= '-pengadaan';
            }

            ProgressStatus::query()->create([
                'name' => self::CLOSED_STATUS,
                'slug' => $slug,
                'category' => StatusCategory::Selesai,
                'sort_order' => (int) ProgressStatus::query()->max('sort_order') + 1,
                'is_default' => false,
                'is_active' => true,
            ]);
        }
    }
}
