<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Mitra master data, filled from every partner entered so far: the
     * planners' partner candidates, the partners carried on procurements,
     * and the vendors of the performance assessments.
     */
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor_code', 100)->nullable();
            $table->string('director_name')->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        $this->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }

    /**
     * One partner per name (spelling and spacing aside), the most recent
     * entry's details first and the gaps filled from older ones.
     */
    protected function backfill(): void
    {
        /** @var array<string, array{name: string, vendor_code: ?string, director_name: ?string, address: ?string}> $partners */
        $partners = [];

        $add = function (?string $name, ?string $vendorCode = null, ?string $director = null, ?string $address = null) use (&$partners): void {
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

            if ($name === '' || $name === '-') {
                return;
            }

            $key = mb_strtolower($name);
            $blank = fn (?string $value): ?string => $value === null || trim($value) === '' ? null : trim($value);

            $partners[$key] ??= ['name' => $name, 'vendor_code' => null, 'director_name' => null, 'address' => null];
            $partners[$key]['vendor_code'] ??= $blank($vendorCode);
            $partners[$key]['director_name'] ??= $blank($director);
            $partners[$key]['address'] ??= $blank($address);
        };

        DB::table('procurement_partner_candidates')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['name', 'vendor_code', 'director_name', 'address'])
            ->each(fn (object $row) => $add($row->name, $row->vendor_code, $row->director_name, $row->address));

        DB::table('procurements')
            ->whereNotNull('partner_name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['partner_name', 'partner_vendor_code', 'partner_director_name', 'partner_address'])
            ->each(fn (object $row) => $add($row->partner_name, $row->partner_vendor_code, $row->partner_director_name, $row->partner_address));

        DB::table('vendor_assessments')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('vendor_name')
            ->each(fn (?string $name) => $add($name));

        $now = now();

        foreach (array_chunk(array_values($partners), 200) as $chunk) {
            DB::table('partners')->insert(array_map(fn (array $partner): array => [
                ...$partner,
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }
};
