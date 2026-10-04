<?php

namespace Database\Seeders;

use App\Enums\ChecklistInput;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\BudgetSource;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use App\Models\WorkDirector;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the modular master data tables with the UP Kendari reference data.
     */
    public function run(): void
    {
        $this->seedUnitManagers();
        $this->seedWorkDirectors();
        $this->seedTargetUnits();
        $this->seedProcurementMethods();
        $this->seedBudgetSources();
        $this->seedContractTypes();
        $this->seedContractNumberFormats();
        $this->seedProgressStatuses();
        // Document types first: the checklist steps link to them.
        $this->seedDocumentTypes();
        $this->seedChecklistItems();
    }

    /**
     * Seed the unit manager reference data.
     */
    protected function seedUnitManagers(): void
    {
        UnitManager::query()->updateOrCreate(
            ['name' => 'MUHAMMAD RUSLI'],
            [
                'position' => 'MANAGER',
                'description' => 'Manager PT PLN Nusantara Power UP Kendari',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );
    }

    /**
     * Seed the direksi pekerjaan reference data.
     */
    protected function seedWorkDirectors(): void
    {
        $names = [
            'Asman Pemeliharaan',
            'Asman Business Support',
            'Asman Operasi',
            'Team Leader K3',
            'Asman Engineering',
            'Team Leader Lingkungan',
        ];

        foreach ($names as $index => $name) {
            WorkDirector::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }

    /**
     * Seed the unit tujuan reference data.
     */
    protected function seedTargetUnits(): void
    {
        $units = [
            ['PLTU Moramo', null],
            ['PLTD Wua Wua', null],
            ['PLTD Poasia', null],
            ['PLTD Poasia Containerized', null],
            ['PLTD Kolaka', null],
            ['PLTD Lanipa', null],
            ['PLTD Ladumpi', null],
            ['PLTM Sabilambo', null],
            ['PLTM Mikuasi', null],
            ['PLTD Bau Bau', null],
            ['PLTD Pasar Wajo', null],
            ['PLTD Winning', null],
            ['PLTD Raha', null],
            ['PLTD Wangi Wangi', 'Sistem Isolated Wangi Wangi'],
            ['PLTD Ereke', 'Sistem Isolated Ereke'],
            ['PLTD Langara', 'Sistem Isolated Langara'],
            ['PLTM Rongi', null],
        ];

        foreach ($units as $index => [$name, $systemName]) {
            TargetUnit::query()->updateOrCreate(
                ['name' => $name],
                ['system_name' => $systemName, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }

    /**
     * Seed the metode pengadaan reference data.
     */
    protected function seedProcurementMethods(): void
    {
        $methods = [
            ['surat-pesanan', 'Surat Pesanan', 'Pengadaan langsung melalui surat pesanan.'],
            ['spk', 'SPK', 'Surat Perintah Kerja.'],
            ['tender', 'Tender', 'Pengadaan melalui proses tender.'],
        ];

        foreach ($methods as $index => [$code, $name, $description]) {
            ProcurementMethod::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Seed the sumber anggaran reference data.
     */
    protected function seedBudgetSources(): void
    {
        $sources = [
            ['AO', 'AO', 'Anggaran Operasi.'],
            ['AI', 'AI', 'Anggaran Investasi.'],
        ];

        foreach ($sources as $index => [$code, $name, $description]) {
            BudgetSource::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Seed the jenis kontrak reference data.
     */
    protected function seedContractTypes(): void
    {
        $types = [
            ['khs', 'KHS', 'Kontrak Harga Satuan.'],
            ['lumsum', 'Lumsum', 'Kontrak lumsum (harga borongan tetap).'],
        ];

        foreach ($types as $index => [$code, $name, $description]) {
            ContractType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Seed the contract number formats.
     *
     * SPK and PJ are the two kinds UP Kendari issues, each keeping its own
     * running count within a year: KDD075.SPK/612/UPKD/2026. The starting
     * sequences are where the unit stands today, so the first number the
     * system hands out carries on from the last one issued on paper.
     */
    protected function seedContractNumberFormats(): void
    {
        $formats = [
            ['SPK', 'Surat Perintah Kerja', 75],
            ['PJ', 'Perjanjian', 20],
            ['SPPL', 'SPPL', 1],
        ];

        foreach ($formats as $index => [$code, $name, $startsAt]) {
            ContractNumberFormat::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'prefix' => 'KDD',
                    'unit_segment' => '612/UPKD',
                    'sequence_length' => 3,
                    'starting_sequence' => $startsAt,
                    'description' => null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Seed the status progres reference data.
     */
    protected function seedProgressStatuses(): void
    {
        $statuses = [
            ['Pending', StatusCategory::Pending, true],
            ['Batal', StatusCategory::Batal, false],
            ['Inisiasi Laksdan', StatusCategory::Berjalan, false],
            ['Penyusunan RKS', StatusCategory::Berjalan, false],
            ['Kelengkapan Dokumen', StatusCategory::Berjalan, false],
            ['Penawaran Harga', StatusCategory::Berjalan, false],
            ['Disposisi AMS', StatusCategory::Berjalan, false],
            ['Proses Validasi', StatusCategory::Selesai, false],
        ];

        foreach ($statuses as $index => [$name, $category, $isDefault]) {
            ProgressStatus::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'sort_order' => $index + 1,
                    'is_default' => $isDefault,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Seed the checklist items for both procurement stages.
     */
    protected function seedChecklistItems(): void
    {
        $this->renameLegacyChecklistItems();

        $planning = [
            ['Checklist Perencanaan', false],
            ['Nota Dinas Usulan', false],
            ['TOR / KAK', false],
            ['RAB (Rencana Anggaran Biaya)', false],
            ['Penawaran', false],
            ['CSMS (Sertifikat)', true],
            ['Nota Dinas ke Pengadaan', false],
            ['HPE (Harga Perkiraan Engineer)', false],
            ['UPB', false],
            ['RKS (Rencana Kerja dan Syarat)', false],
            ['Inisiasi SMART SCM', false],
            ['PR / RO', true],
        ];

        // SPK/PJ and SPPL steps side by side; each format switches off the
        // steps that are not its own (see seedChecklistExclusions).
        $execution = [
            ['Evaluasi Dokumen', false],
            ['Penyusunan HPS', false],
            ['Proses SMART SCM', false],
            ['Berita Acara', false],
            ['BA Negosiasi', false],
            ['Penyusunan Kontrak', false],
            ['Surat Pesanan', false],
            ['Rekening Pelaksana', false],
            ['Jaminan Bank', false],
            ['Kontrak', false],
            ['Lampiran Surat Pesanan', false],
            ['Rentang Waktu', false],
            ['Rentang Waktu Pelaksanaan', false],
            ['Amandemen', true],
            ['Masa Pemeliharaan', false],
            ['Masa Garansi', false],
        ];

        /** @var array<string, ChecklistInput> $inputs */
        $inputs = [
            'Rekening Pelaksana' => ChecklistInput::BankAccount,
            'Rentang Waktu Pelaksanaan' => ChecklistInput::ContractPeriod,
            'Masa Garansi' => ChecklistInput::Warranty,
        ];

        foreach ($planning as $index => [$name, $isOptional]) {
            ChecklistItem::query()->updateOrCreate(
                ['stage' => ProcurementStage::Perencanaan->value, 'name' => $name],
                ['is_optional' => $isOptional, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }

        foreach ($execution as $index => [$name, $isOptional]) {
            ChecklistItem::query()->updateOrCreate(
                ['stage' => ProcurementStage::Pelaksanaan->value, 'name' => $name],
                [
                    'is_optional' => $isOptional,
                    'input_kind' => $inputs[$name] ?? null,
                    'sort_order' => $index + 1,
                    // Penyusunan Kontrak is no longer part of any format.
                    'is_active' => $name !== 'Penyusunan Kontrak',
                ],
            );
        }

        $this->seedChecklistExclusions();
        $this->linkChecklistDocuments();
    }

    /**
     * Attach the document each checklist step produces.
     *
     * A step listed here gains generate, edit and upload actions, and cannot
     * be ticked until the signed copy is filed. Steps left out of the map are
     * plain ticks with no paperwork; their link is cleared so that removing a
     * step from this list actually takes the document actions away again.
     */
    protected function linkChecklistDocuments(): void
    {
        /** @var array<string, array<string, array<int, string>>> $map */
        $map = [
            ProcurementStage::Perencanaan->value => [
                'Nota Dinas Usulan' => ['nota-dinas-usulan'],
                'TOR / KAK' => ['tor'],
                'RAB (Rencana Anggaran Biaya)' => ['rab'],
                'Penawaran' => ['penawaran'],
                'CSMS (Sertifikat)' => ['csms'],
                'Nota Dinas ke Pengadaan' => ['nota-dinas-perintah-pekerjaan'],
                'HPE (Harga Perkiraan Engineer)' => ['hpe'],
                'UPB' => ['upb'],
                'RKS (Rencana Kerja dan Syarat)' => ['rks'],
            ],
            ProcurementStage::Pelaksanaan->value => [
                'Penyusunan HPS' => ['penyusunan-hps'],
                'Proses SMART SCM' => ['proses-smart-scm'],
                // One step, six berita acara, all of which have to be signed
                // and filed before the step counts as finished.
                'Berita Acara' => [
                    'ba-aanwijzing',
                    'lampiran-bapp',
                    'ba-evaluasi-teknis',
                    'ba-evaluasi-harga',
                    'ba-hasil-evaluasi',
                    'ba-klarifikasi',
                ],
                'Penyusunan Kontrak' => ['penyusunan-kontrak'],
                'Jaminan Bank' => ['jaminan-bank'],
                'Kontrak' => ['spk', 'lampiran-spk', 'ba-negosiasi'],
                'Amandemen' => ['amandemen'],
                'Masa Pemeliharaan' => ['masa-pemeliharaan'],
                // SPPL.
                'BA Negosiasi' => ['ba-negosiasi-sppl'],
                'Surat Pesanan' => ['purchase-order'],
                'Lampiran Surat Pesanan' => [],
            ],
        ];

        /** @var array<string, array<int, string>> $alternatives */
        $alternatives = [
            'Lampiran Surat Pesanan' => ['surat-pesanan-barang', 'surat-pesanan-jasa'],
        ];

        $typeIds = DocumentType::query()->pluck('id', 'code');

        foreach ($map as $stage => $steps) {
            foreach ($steps as $name => $codes) {
                $item = ChecklistItem::query()
                    ->where('stage', $stage)
                    ->where('name', $name)
                    ->first();

                if ($item === null) {
                    continue;
                }

                $links = [];
                $order = 0;

                foreach (array_values($codes) as $code) {
                    if (isset($typeIds[$code])) {
                        $links[$typeIds[$code]] = ['sort_order' => ++$order, 'is_alternative' => false];
                    }
                }

                // Alternatives: one signed copy among them completes the step.
                foreach ($alternatives[$name] ?? [] as $code) {
                    if (isset($typeIds[$code])) {
                        $links[$typeIds[$code]] = ['sort_order' => ++$order, 'is_alternative' => true];
                    }
                }

                $item->documentTypes()->sync($links);
            }

            // Steps left out of the map are plain ticks; clearing them means
            // removing a step from the list actually takes its documents away.
            ChecklistItem::query()
                ->where('stage', $stage)
                ->whereNotIn('name', array_keys($steps))
                ->get()
                ->each(fn (ChecklistItem $item) => $item->documentTypes()->detach());
        }
    }

    /**
     * Carry checklist items that were renamed over to their current wording.
     *
     * The items below are matched on stage and name, so without this an
     * existing installation would gain a second item under the new wording and
     * leave the old one behind, orphaning every tick already recorded against
     * it. Renaming in place keeps the history attached.
     */
    protected function renameLegacyChecklistItems(): void
    {
        /** @var array<int, array{stage: ProcurementStage, from: string, to: string}> $renames */
        $renames = [
            ['stage' => ProcurementStage::Perencanaan, 'from' => 'Smart SCM', 'to' => 'Inisiasi SMART SCM'],
            ['stage' => ProcurementStage::Pelaksanaan, 'from' => 'Progress Pengadaan', 'to' => 'Proses SMART SCM'],
            ['stage' => ProcurementStage::Perencanaan, 'from' => 'TOR (Term of Reference)', 'to' => 'TOR / KAK'],
            ['stage' => ProcurementStage::Perencanaan, 'from' => 'CSMS', 'to' => 'CSMS (Sertifikat)'],
            ['stage' => ProcurementStage::Perencanaan, 'from' => 'Nota Dinas Perintah Pekerjaan', 'to' => 'Nota Dinas ke Pengadaan'],
            ['stage' => ProcurementStage::Pelaksanaan, 'from' => 'Surat Pesanan', 'to' => 'Lampiran Surat Pesanan'],
            ['stage' => ProcurementStage::Pelaksanaan, 'from' => 'Purchase Order (PO)', 'to' => 'Surat Pesanan'],
        ];

        foreach ($renames as $rename) {
            $existing = ChecklistItem::withTrashed()
                ->forStage($rename['stage'])
                ->where('name', $rename['to'])
                ->exists();

            if ($existing) {
                continue;
            }

            ChecklistItem::withTrashed()
                ->forStage($rename['stage'])
                ->where('name', $rename['from'])
                ->update(['name' => $rename['to']]);
        }
    }

    /**
     * Switch off the planning steps that a Surat Pesanan does not go through.
     *
     * A Surat Pesanan is the lightest method: it skips the tender paperwork
     * and the SMART SCM/PR pipeline entirely.
     */
    protected function seedChecklistExclusions(): void
    {
        $suratPesanan = ProcurementMethod::query()->where('code', 'surat-pesanan')->first();

        if ($suratPesanan === null) {
            return;
        }

        $excluded = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->whereIn('name', [
                'RKS (Rencana Kerja dan Syarat)',
                'Inisiasi SMART SCM',
                'PR / RO',
                'UPB',
                'HPE (Harga Perkiraan Engineer)',
            ])
            ->pluck('id');

        foreach ($excluded as $checklistItemId) {
            $suratPesanan->excludedChecklistItems()->syncWithoutDetaching([$checklistItemId]);
        }

        $formats = ContractNumberFormat::query()->pluck('id', 'code');

        $skip = function (ProcurementStage $stage, array $names, array $formatIds): void {
            ChecklistItem::query()
                ->forStage($stage)
                ->whereIn('name', $names)
                ->get()
                ->each(fn (ChecklistItem $item) => $item->excludedContractNumberFormats()->syncWithoutDetaching($formatIds));
        };

        if (isset($formats['SPPL'])) {
            // SPPL folds the RAB into its Penawaran, so it skips the RAB step,
            // and has its own execution steps in place of the SPK/PJ ones.
            $skip(ProcurementStage::Perencanaan, ['RAB (Rencana Anggaran Biaya)'], [$formats['SPPL']]);
            $skip(ProcurementStage::Pelaksanaan, [
                'Penyusunan HPS',
                'Proses SMART SCM',
                'Berita Acara',
                'Penyusunan Kontrak',
                'Kontrak',
                'Jaminan Bank',
                'Rentang Waktu',
                'Amandemen',
                'Masa Pemeliharaan',
            ], [$formats['SPPL']]);
        }

        // The SPPL execution steps are not part of SPK or PJ.
        $skip(
            ProcurementStage::Pelaksanaan,
            ['BA Negosiasi', 'Lampiran Surat Pesanan', 'Rekening Pelaksana', 'Rentang Waktu Pelaksanaan', 'Masa Garansi'],
            array_values(array_filter([$formats['SPK'] ?? null, $formats['PJ'] ?? null])),
        );
    }

    /**
     * Seed the document types that can be generated.
     */
    protected function seedDocumentTypes(): void
    {
        $types = [
            ['nota-dinas-usulan', 'Nota Dinas Usulan', ProcurementStage::Perencanaan],
            ['tor', 'TOR (Term of Reference)', ProcurementStage::Perencanaan],
            ['rab', 'RAB (Rencana Anggaran Biaya)', ProcurementStage::Perencanaan],
            ['penawaran', 'Penawaran', ProcurementStage::Perencanaan],
            ['csms', 'CSMS', ProcurementStage::Perencanaan],
            ['nota-dinas-perintah-pekerjaan', 'Nota Dinas Perintah Pekerjaan', ProcurementStage::Perencanaan],
            ['hpe', 'HPE (Harga Perkiraan Engineer)', ProcurementStage::Perencanaan],
            ['upb', 'UPB', ProcurementStage::Perencanaan],
            ['rks', 'RKS (Rencana Kerja dan Syarat)', ProcurementStage::Perencanaan],
            ['inisiasi-smart-scm', 'Inisiasi SMART SCM', ProcurementStage::Perencanaan],
            ['pr-ro', 'PR / RO', ProcurementStage::Perencanaan],
            ['penyusunan-hps', 'Penyusunan HPS', ProcurementStage::Pelaksanaan],
            ['proses-smart-scm', 'Proses SMART SCM', ProcurementStage::Pelaksanaan],
            ['berita-acara', 'Berita Acara', ProcurementStage::Pelaksanaan],
            ['penyusunan-kontrak', 'Penyusunan Kontrak', ProcurementStage::Pelaksanaan],
            ['jaminan-bank', 'Jaminan Bank', ProcurementStage::Pelaksanaan],
            ['amandemen', 'Amandemen', ProcurementStage::Pelaksanaan],
            ['masa-pemeliharaan', 'Masa Pemeliharaan', ProcurementStage::Pelaksanaan],
            ['ba-evaluasi-teknis', 'Berita Acara Evaluasi Teknis', ProcurementStage::Pelaksanaan],
            ['spk', 'SPK (Surat Perintah Kerja)', ProcurementStage::Pelaksanaan],
            ['lampiran-spk', 'Lampiran Surat Perintah Kerja', ProcurementStage::Pelaksanaan],
            ['ba-negosiasi', 'Berita Acara Negosiasi dan Lampiran', ProcurementStage::Pelaksanaan],
            ['ba-aanwijzing', 'Berita Acara Aanwijzing', ProcurementStage::Pelaksanaan],
            ['lampiran-bapp', 'Lampiran BAPP', ProcurementStage::Pelaksanaan],
            ['ba-evaluasi-harga', 'Berita Acara Evaluasi Harga', ProcurementStage::Pelaksanaan],
            ['ba-hasil-evaluasi', 'Berita Acara Hasil Evaluasi', ProcurementStage::Pelaksanaan],
            ['ba-klarifikasi', 'Berita Acara Klarifikasi', ProcurementStage::Pelaksanaan],
            ['kontrak', 'Kontrak', ProcurementStage::Pelaksanaan],
            ['ba-negosiasi-sppl', 'Berita Acara Negosiasi (SPPL)', ProcurementStage::Pelaksanaan],
            ['purchase-order', 'Surat Pesanan', ProcurementStage::Pelaksanaan],
            ['surat-pesanan-barang', 'Lampiran SP Barang', ProcurementStage::Pelaksanaan],
            ['surat-pesanan-jasa', 'Lampiran SP Jasa', ProcurementStage::Pelaksanaan],
            ['surat-pesanan', 'Surat Pesanan', ProcurementStage::Pelaksanaan],
            ['lampiran-sp-barang', 'Lampiran SP Barang', ProcurementStage::Pelaksanaan],
            ['lampiran-sp-jasa', 'Lampiran SP Jasa', ProcurementStage::Pelaksanaan],
        ];

        foreach ($types as $index => [$code, $name, $stage]) {
            DocumentType::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'stage' => $stage, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }

        // Steps that turned out to be plain ticks: the type is switched off so
        // it stops being offered, but stays on record in case it is wanted
        // back later. Any document already generated from it is untouched.
        DocumentType::query()
            ->whereIn('code', ['pr-ro', 'inisiasi-smart-scm', 'rentang-waktu'])
            ->update(['is_active' => false]);

        // The opening planning documents are prepared outside the system and
        // simply uploaded on their step; nothing is generated for them.
        DocumentType::query()
            // Only the Berita Acara and Kontrak documents are still generated
            // during execution; the rest is uploaded.
            ->whereIn('code', [
                'nota-dinas-usulan', 'tor', 'rab', 'penawaran', 'csms', 'nota-dinas-perintah-pekerjaan',
                'penyusunan-hps', 'proses-smart-scm', 'jaminan-bank', 'amandemen', 'masa-pemeliharaan',
            ])
            ->update(['upload_only' => true]);
    }
}
