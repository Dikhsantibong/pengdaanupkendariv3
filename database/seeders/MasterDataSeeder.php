<?php

namespace Database\Seeders;

use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Models\BudgetSource;
use App\Models\ChecklistItem;
use App\Models\DocumentType;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
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
        $this->seedWorkDirectors();
        $this->seedTargetUnits();
        $this->seedProcurementMethods();
        $this->seedBudgetSources();
        $this->seedProgressStatuses();
        $this->seedChecklistItems();
        $this->seedDocumentTypes();
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
        $planning = [
            ['Checklist Perencanaan', false],
            ['Nota Dinas Usulan', false],
            ['TOR (Term of Reference)', false],
            ['RAB (Rencana Anggaran Biaya)', false],
            ['Penawaran', false],
            ['CSMS', true],
            ['Nota Dinas Perintah Pekerjaan', false],
            ['HPE (Harga Perkiraan Engineer)', false],
            ['UPB', false],
            ['RKS (Rencana Kerja dan Syarat)', false],
            ['Smart SCM', false],
            ['PR / RO', true],
        ];

        $execution = [
            ['Evaluasi Dokumen', false],
            ['Penyusunan HPS', false],
            ['Progress Pengadaan', false],
            ['Berita Acara', false],
            ['Penyusunan Kontrak', false],
            ['Purchase Order (PO)', false],
            ['Jaminan Bank', false],
            ['Kontrak', false],
            ['Rentang Waktu', false],
            ['Amandemen', true],
            ['Masa Pemeliharaan', false],
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
                ['is_optional' => $isOptional, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
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
            ['hpe', 'HPE (Harga Perkiraan Engineer)', ProcurementStage::Perencanaan],
            ['upb', 'UPB', ProcurementStage::Perencanaan],
            ['rks', 'RKS (Rencana Kerja dan Syarat)', ProcurementStage::Perencanaan],
            ['berita-acara', 'Berita Acara', ProcurementStage::Pelaksanaan],
            ['kontrak', 'Kontrak', ProcurementStage::Pelaksanaan],
        ];

        foreach ($types as $index => [$code, $name, $stage]) {
            DocumentType::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'stage' => $stage, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
