<?php

use Database\Seeders\SpplDocumentTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The execution stage, per contract format.
 *
 * - A checklist step can ask for an input before it is ticked: the execution
 *   period, the warranty, or the partner's bank account.
 * - A step can carry alternative documents, of which uploading one is enough
 *   (Lampiran SP Barang or Jasa).
 * - SPPL gets its own execution steps; the SPK/PJ steps it does not go
 *   through are switched off for it, and the new steps for SPK and PJ.
 * - For every format only the Berita Acara and Kontrak documents are still
 *   generated; the other execution documents are uploaded. Penyusunan
 *   Kontrak is switched off.
 *
 * Data changes are additive and only touch rows still in their seeded shape,
 * so anything an administrator has already adjusted is left alone.
 */
return new class extends Migration
{
    /**
     * The execution documents that are uploaded from now on.
     *
     * @var array<int, string>
     */
    private const UPLOAD_ONLY = ['penyusunan-hps', 'proses-smart-scm', 'jaminan-bank', 'amandemen', 'masa-pemeliharaan'];

    /**
     * The SPK/PJ execution steps SPPL does not go through.
     *
     * @var array<int, string>
     */
    private const SKIPPED_BY_SPPL = [
        'Penyusunan HPS',
        'Proses SMART SCM',
        'Berita Acara',
        'Kontrak',
        'Jaminan Bank',
        'Rentang Waktu',
        'Amandemen',
        'Masa Pemeliharaan',
    ];

    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->string('input_kind')->nullable()->after('is_optional');
        });

        Schema::table('checklist_item_document_type', function (Blueprint $table): void {
            $table->boolean('is_alternative')->default(false)->after('sort_order');
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->date('execution_start_date')->nullable()->after('notes');
            $table->unsignedInteger('execution_duration_days')->nullable()->after('execution_start_date');
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('execution_duration_days');
            $table->string('bank_account_number')->nullable()->after('warranty_months');
            $table->string('bank_name')->nullable()->after('bank_account_number');
            $table->string('bank_account_holder')->nullable()->after('bank_name');
        });

        if (! DB::table('checklist_items')->where('stage', 'pelaksanaan')->exists()) {
            return;
        }

        DB::table('document_types')->whereIn('code', self::UPLOAD_ONLY)->update(['upload_only' => true]);

        DB::table('checklist_items')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Penyusunan Kontrak')
            ->update(['is_active' => false]);

        $this->addSpplSteps();

        (new SpplDocumentTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn([
                'execution_start_date',
                'execution_duration_days',
                'warranty_months',
                'bank_account_number',
                'bank_name',
                'bank_account_holder',
            ]);
        });

        Schema::table('checklist_item_document_type', function (Blueprint $table): void {
            $table->dropColumn('is_alternative');
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropColumn('input_kind');
        });
    }

    /**
     * Create the SPPL document types and steps, and point each format at the
     * steps it goes through.
     */
    private function addSpplSteps(): void
    {
        $now = now();

        $types = [
            ['ba-negosiasi-sppl', 'Berita Acara Negosiasi (SPPL)'],
            ['surat-pesanan', 'Surat Pesanan'],
            ['lampiran-sp-barang', 'Lampiran SP Barang'],
            ['lampiran-sp-jasa', 'Lampiran SP Jasa'],
        ];

        $order = (int) DB::table('document_types')->max('sort_order');

        foreach ($types as [$code, $name]) {
            if (DB::table('document_types')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('document_types')->insert([
                'code' => $code,
                'name' => $name,
                'stage' => 'pelaksanaan',
                'upload_only' => false,
                'sort_order' => ++$order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $typeId = fn (string $code): ?int => DB::table('document_types')->where('code', $code)->value('id');

        // name => [sort order, input kind, required docs, alternative docs]
        $steps = [
            'BA Negosiasi' => [4, null, ['ba-negosiasi-sppl'], []],
            'Surat Pesanan' => [8, null, ['surat-pesanan'], ['lampiran-sp-barang', 'lampiran-sp-jasa']],
            'Rekening Pelaksana' => [7, 'bank_account', [], []],
            'Rentang Waktu Pelaksanaan' => [9, 'contract_period', [], []],
            'Masa Garansi' => [11, 'warranty', [], []],
        ];

        $stepIds = [];

        foreach ($steps as $name => [$sortOrder, $inputKind, $required, $alternatives]) {
            $id = DB::table('checklist_items')->where('stage', 'pelaksanaan')->where('name', $name)->value('id');

            if ($id === null) {
                $id = DB::table('checklist_items')->insertGetId([
                    'stage' => 'pelaksanaan',
                    'name' => $name,
                    'description' => 'Tahapan format SPPL.',
                    'is_optional' => false,
                    'input_kind' => $inputKind,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ([[$required, false], [$alternatives, true]] as [$codes, $isAlternative]) {
                    foreach ($codes as $index => $code) {
                        $documentTypeId = $typeId($code);

                        if ($documentTypeId !== null) {
                            DB::table('checklist_item_document_type')->insert([
                                'checklist_item_id' => $id,
                                'document_type_id' => $documentTypeId,
                                'sort_order' => $index + 1,
                                'is_alternative' => $isAlternative,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }

            $stepIds[] = $id;
        }

        $formats = DB::table('contract_number_formats')->pluck('id', 'code');

        $exclude = function (array $itemIds, array $formatIds) use ($now): void {
            foreach ($itemIds as $itemId) {
                foreach ($formatIds as $formatId) {
                    DB::table('checklist_item_format_exclusions')->insertOrIgnore([
                        'checklist_item_id' => $itemId,
                        'contract_number_format_id' => $formatId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        };

        // The new steps belong to SPPL only.
        $exclude($stepIds, array_values(array_filter([$formats['SPK'] ?? null, $formats['PJ'] ?? null])));

        // SPPL skips the SPK/PJ steps it replaces.
        if (isset($formats['SPPL'])) {
            $exclude(
                DB::table('checklist_items')
                    ->where('stage', 'pelaksanaan')
                    ->whereIn('name', self::SKIPPED_BY_SPPL)
                    ->pluck('id')
                    ->all(),
                [$formats['SPPL']],
            );
        }
    }
};
