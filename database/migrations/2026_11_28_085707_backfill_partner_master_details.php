<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clean placeholder dashes ("-") on Data Master Mitra and backfill any
     * missing partner details (including phone_number added later) from the
     * most recent partner candidate or procurement records without overwriting
     * values already set in Data Master Mitra.
     */
    public function up(): void
    {
        if (! Schema::hasTable('partners') || ! Schema::hasColumn('partners', 'phone_number')) {
            return;
        }

        $clean = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || preg_match('/^[\-\x{2013}\x{2014}]+$/u', $trimmed) === 1) {
                return null;
            }

            return $trimmed;
        };

        $normaliseKey = static fn (?string $name): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));

        /** @var array<string, array{vendor_code: ?string, director_name: ?string, phone_number: ?string, address: ?string}> $detailsByKey */
        $detailsByKey = [];

        $recordDetails = static function (?string $name, ?string $vendorCode, ?string $director, ?string $phone, ?string $address) use (&$detailsByKey, $clean, $normaliseKey): void {
            $key = $normaliseKey($name);

            if ($key === '' || $key === '-') {
                return;
            }

            $detailsByKey[$key] ??= [
                'vendor_code' => null,
                'director_name' => null,
                'phone_number' => null,
                'address' => null,
            ];

            $detailsByKey[$key]['vendor_code'] ??= $clean($vendorCode);
            $detailsByKey[$key]['director_name'] ??= $clean($director);
            $detailsByKey[$key]['phone_number'] ??= $clean($phone);
            $detailsByKey[$key]['address'] ??= $clean($address);
        };

        if (Schema::hasTable('procurement_partner_candidates')) {
            DB::table('procurement_partner_candidates')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['name', 'vendor_code', 'director_name', 'phone_number', 'address'])
                ->each(fn (object $row) => $recordDetails(
                    $row->name,
                    $row->vendor_code,
                    $row->director_name,
                    $row->phone_number,
                    $row->address,
                ));
        }

        if (Schema::hasTable('procurements')) {
            DB::table('procurements')
                ->whereNotNull('partner_name')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['partner_name', 'partner_vendor_code', 'partner_director_name', 'partner_phone_number', 'partner_address'])
                ->each(fn (object $row) => $recordDetails(
                    $row->partner_name,
                    $row->partner_vendor_code,
                    $row->partner_director_name,
                    $row->partner_phone_number,
                    $row->partner_address,
                ));
        }

        DB::table('partners')
            ->orderBy('id')
            ->get(['id', 'name', 'vendor_code', 'director_name', 'phone_number', 'address'])
            ->each(function (object $partner) use ($detailsByKey, $clean, $normaliseKey): void {
                $key = $normaliseKey($partner->name);
                $fallback = $detailsByKey[$key] ?? [];
                $updates = [];

                foreach (['vendor_code', 'director_name', 'phone_number', 'address'] as $field) {
                    $cleaned = $clean($partner->{$field});
                    $resolved = $cleaned ?? ($fallback[$field] ?? null);

                    if ($resolved !== $partner->{$field}) {
                        $updates[$field] = $resolved;
                    }
                }

                if ($updates !== []) {
                    DB::table('partners')
                        ->where('id', $partner->id)
                        ->update($updates);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data cleanup and backfill migration intentionally leaves existing data intact.
    }
};
