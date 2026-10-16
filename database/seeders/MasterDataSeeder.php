<?php

namespace Database\Seeders;

use App\Enums\ChecklistInput;
use App\Enums\ProcurementStage;
use App\Enums\SystemStatus;
use App\Enums\WorkCategory;
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
use App\Services\ProgressStatusResolver;
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
        $this->seedAmendmentFormat();
        $this->seedProgressStatuses();
        // Document types first: the checklist steps link to them.
        $this->seedDocumentTypes();
        $this->seedChecklistItems();
        // The Keuangan stage that follows execution.
        $this->call(FinanceStageSeeder::class);
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
        // The last column is the SPK/PJ document format the type picks.
        $types = [
            ['khs', 'KHS', 'Kontrak Harga Satuan.', null],
            ['lumsum', 'Lumsum', 'Kontrak lumsum (harga borongan tetap).', null],
            ['barang', 'Barang', 'Format dokumen Barang.', WorkCategory::Barang],
            ['jasa', 'Jasa', 'Format dokumen Jasa.', WorkCategory::Jasa],
        ];

        foreach ($types as $index => [$code, $name, $description, $category]) {
            ContractType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'work_category' => $category,
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
            // [code, name, starting sequence, asks for barang/jasa, method]
            ['SPK', 'Surat Perintah Kerja', 75, true, 'spk'],
            ['PJ', 'Perjanjian', 20, true, 'tender'],
            ['SPPL', 'SPPL', 1, false, 'surat-pesanan'],
        ];

        // A procurement takes its method from its format, so the method is
        // no longer asked on the procurement form.
        $methods = ProcurementMethod::query()->pluck('id', 'code');

        foreach ($formats as $index => [$code, $name, $startsAt, $usesWorkCategory, $method]) {
            ContractNumberFormat::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'prefix' => 'KDD',
                    'unit_segment' => '612/UPKD',
                    'sequence_length' => 3,
                    'starting_sequence' => $startsAt,
                    'uses_work_category' => $usesWorkCategory,
                    'procurement_method_id' => $methods[$method] ?? null,
                    'description' => null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * The format amendments are numbered with: KDD054.Amd/612/UPKD/2026.
     *
     * Created once; what the administrator later sets on it stays.
     */
    protected function seedAmendmentFormat(): void
    {
        if (ContractNumberFormat::withTrashed()->where('for_amendment', true)->exists()) {
            return;
        }

        ContractNumberFormat::query()->create([
            'code' => 'Amd',
            'name' => 'Amandemen Kontrak',
            'prefix' => 'KDD',
            'unit_segment' => '612/UPKD',
            'sequence_length' => 3,
            'starting_sequence' => 1,
            'uses_work_category' => false,
            'for_amendment' => true,
            'description' => 'Nomor amandemen kontrak, contoh KDD054.Amd/612/UPKD/2026.',
            'sort_order' => 99,
            'is_active' => true,
        ]);
    }

    /**
     * Seed the status progres reference data.
     *
     * Every status is set by the system (ProgressStatusResolver), found by
     * its `system_key`. A status already there keeps the name it was given
     * on the master data; only a missing one is created.
     */
    protected function seedProgressStatuses(): void
    {
        foreach (SystemStatus::cases() as $index => $status) {
            $existing = ProgressStatus::withTrashed()->where('system_key', $status->value)->first();

            if ($existing !== null) {
                continue;
            }

            $taken = ProgressStatus::withTrashed()->where('slug', Str::slug($status->label()))->first();

            // A status of the same name typed in before becomes the system one.
            if ($taken !== null && $taken->system_key === null) {
                $taken->forceFill(['system_key' => $status->value, 'category' => $status->category(), 'is_active' => true])->save();

                continue;
            }

            $created = new ProgressStatus([
                'name' => $status->label(),
                'slug' => Str::slug($status->label()),
                'category' => $status->category(),
                'sort_order' => $index + 1,
                'is_default' => $status === SystemStatus::InisiasiRendan,
                'is_active' => true,
            ]);
            $created->system_key = $status->value;
            $created->save();
        }

        app(ProgressStatusResolver::class)->forget();
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
            // One step for the SMART SCM document and every Berita Acara.
            ['Proses SMART SCM & Berita Acara', false],
            ['BA Negosiasi', false],
            ['Penyusunan Kontrak', false],
            ['Surat Pesanan', false],
            ['Rekening Pelaksana', false],
            ['Jaminan Bank', false],
            ['Kontrak', false],
            ['Lampiran Surat Pesanan', false],
            ['Rentang Waktu', false],
            ['Rentang Waktu Pelaksanaan', false],
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
            if ($this->removedByUser(ProcurementStage::Perencanaan, $name)) {
                continue;
            }

            ChecklistItem::query()->updateOrCreate(
                ['stage' => ProcurementStage::Perencanaan->value, 'name' => $name],
                [
                    'is_optional' => $isOptional,
                    'sort_order' => $index + 1,
                    // The bare "Checklist Perencanaan" tick duplicated the
                    // stage heading; the Nota Dinas Usulan and TOR are filed
                    // on the proposer's tab instead.
                    'is_active' => ! in_array($name, ['Checklist Perencanaan', 'Nota Dinas Usulan', 'TOR / KAK'], true),
                ],
            );
        }

        foreach ($execution as $index => [$name, $isOptional]) {
            if ($this->removedByUser(ProcurementStage::Pelaksanaan, $name)) {
                continue;
            }

            ChecklistItem::query()->updateOrCreate(
                ['stage' => ProcurementStage::Pelaksanaan->value, 'name' => $name],
                [
                    'is_optional' => $isOptional,
                    'input_kind' => $inputs[$name] ?? null,
                    'sort_order' => $index + 1,
                    // Penyusunan Kontrak is no longer part of any format, and
                    // Evaluasi Dokumen is no longer a step of its own.
                    'is_active' => ! in_array($name, ['Penyusunan Kontrak', 'Evaluasi Dokumen'], true),
                ],
            );
        }

        $this->seedChecklistExclusions();
        $this->linkChecklistDocuments();
    }

    /**
     * Whether a seeded step was deleted on the Item Checklist screen.
     *
     * Seeding again must not bring it back as a fresh item: that would add
     * it to every running procurement the user had taken it out of.
     */
    protected function removedByUser(ProcurementStage $stage, string $name): bool
    {
        $items = ChecklistItem::withTrashed()
            ->forStage($stage)
            ->where('name', $name)
            ->whereNull('procurement_id')
            ->get();

        return $items->isNotEmpty() && $items->every(fn (ChecklistItem $item): bool => $item->trashed());
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
                // One step: the SMART SCM document and the six berita acara,
                // all of which have to be signed and filed before the step
                // counts as finished.
                'Proses SMART SCM & Berita Acara' => [
                    'proses-smart-scm',
                    'ba-aanwijzing',
                    'lampiran-bapp',
                    'ba-evaluasi-teknis',
                    'ba-evaluasi-harga',
                    'ba-hasil-evaluasi',
                    'ba-klarifikasi',
                ],
                'Penyusunan Kontrak' => ['penyusunan-kontrak'],
                'Jaminan Bank' => ['jaminan-bank'],
                // Only the SPK with its articles is uploaded for the contract.
                'Kontrak' => ['spk'],
                'Masa Pemeliharaan' => ['masa-pemeliharaan'],
                // SPPL.
                'BA Negosiasi' => ['ba-negosiasi-sppl'],
                'Surat Pesanan' => ['purchase-order'],
                // One appendix for goods and services alike.
                'Lampiran Surat Pesanan' => ['lampiran-sp'],
            ],
        ];

        /** @var array<string, array<int, string>> $alternatives */
        $alternatives = [];

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
            ['stage' => ProcurementStage::Pelaksanaan, 'from' => 'Proses SMART SCM', 'to' => 'Proses SMART SCM & Berita Acara'],
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
     * Switch off the steps each contract format does not go through.
     *
     * Steps are switched off by format only: the method follows the format,
     * so the SPPL (let by Surat Pesanan, the lightest method) also skips the
     * tender paperwork and the SMART SCM/PR pipeline.
     */
    protected function seedChecklistExclusions(): void
    {
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
            $skip(ProcurementStage::Perencanaan, [
                'RAB (Rencana Anggaran Biaya)',
                'RKS (Rencana Kerja dan Syarat)',
                'Inisiasi SMART SCM',
                'PR / RO',
                'UPB',
                'HPE (Harga Perkiraan Engineer)',
            ], [$formats['SPPL']]);
            $skip(ProcurementStage::Pelaksanaan, [
                'Penyusunan HPS',
                'Proses SMART SCM & Berita Acara',
                'Penyusunan Kontrak',
                'Kontrak',
                'Jaminan Bank',
                'Rentang Waktu',
                'Masa Pemeliharaan',
            ], [$formats['SPPL']]);
        }

        // The SPPL execution steps are not part of SPK or PJ, except the bank
        // account and warranty the SPK also states (Pasal 5 and Pasal 9).
        $skip(
            ProcurementStage::Pelaksanaan,
            ['BA Negosiasi', 'Lampiran Surat Pesanan', 'Rentang Waktu Pelaksanaan'],
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
            ['lampiran-sp', 'Lampiran SP Barang dan Jasa', ProcurementStage::Pelaksanaan],
            ['surat-pesanan', 'Surat Pesanan', ProcurementStage::Pelaksanaan],
            // Drawn from the Amandemen tab, with the amendment's own values.
            ['amandemen-kontrak', 'Amandemen Kontrak', ProcurementStage::Pelaksanaan],
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
                // The TOR is generated when the work is proposed.
                'nota-dinas-usulan', 'rab', 'penawaran', 'csms', 'nota-dinas-perintah-pekerjaan',
                'penyusunan-hps', 'proses-smart-scm', 'jaminan-bank', 'amandemen', 'masa-pemeliharaan',
            ])
            ->update(['upload_only' => true]);
    }
}
