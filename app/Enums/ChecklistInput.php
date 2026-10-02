<?php

namespace App\Enums;

use App\Models\Procurement;

/**
 * Data a checklist step asks for before it can be ticked.
 *
 * The kinds are fixed in code because each one maps to its own fields and
 * rules, but which step asks for which input is master data: an
 * administrator attaches a kind to any step from the Item Checklist screen.
 */
enum ChecklistInput: string
{
    case ContractPeriod = 'contract_period';
    case Warranty = 'warranty';
    case BankAccount = 'bank_account';

    /**
     * The name shown when choosing the input on the master data screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::ContractPeriod => 'Rentang waktu (tanggal mulai + jumlah hari)',
            self::Warranty => 'Masa garansi (bulan)',
            self::BankAccount => 'Rekening pelaksana (nomor, bank, nama)',
        };
    }

    /**
     * The procurement fields this input fills in.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::ContractPeriod => ['execution_start_date', 'execution_duration_days'],
            self::Warranty => ['warranty_months'],
            self::BankAccount => ['bank_account_number', 'bank_name', 'bank_account_holder'],
        };
    }

    /**
     * Whether every field of this input has been filled in.
     */
    public function isFilledOn(Procurement $procurement): bool
    {
        foreach ($this->fields() as $field) {
            $value = $procurement->getAttribute($field);

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Every kind as a selectable option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
