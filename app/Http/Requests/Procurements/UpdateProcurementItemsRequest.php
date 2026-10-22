<?php

namespace App\Http\Requests\Procurements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcurementItemsRequest extends FormRequest
{
    /**
     * Who may change the items is decided by the policy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::rowRules('rows', ['present']),
            // The editor's draft, whose item table is drawn for the preview.
            'body' => ['sometimes', 'string', 'max:2000000'],
            // A or a: how the groups are lettered.
            'group_letters' => ['sometimes', Rule::in(['upper', 'lower'])],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::rowAttributes('rows');
    }

    /**
     * The rules of a list of Rincian Item rows under the given key.
     *
     * @param  list<string>  $presence
     * @return array<string, array<mixed>>
     */
    public static function rowRules(string $key, array $presence): array
    {
        return [
            $key => [...$presence, 'array', 'max:500'],
            "{$key}.*.id" => ['nullable', 'integer'],
            "{$key}.*.kind" => ['required', Rule::in(['group', 'item'])],
            "{$key}.*.description" => ['required', 'string', 'max:500'],
            "{$key}.*.part_number" => ['nullable', 'string', 'max:100'],
            "{$key}.*.volume" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$key}.*.unit" => ['nullable', 'string', 'max:50'],
            "{$key}.*.price_before" => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            "{$key}.*.unit_price" => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            // The item's last purchase (Evaluasi Harga).
            "{$key}.*.previous_po_number" => ['nullable', 'string', 'max:100'],
            "{$key}.*.previous_po_date" => ['nullable', 'string', 'max:50'],
            "{$key}.*.previous_supplier" => ['nullable', 'string', 'max:150'],
            "{$key}.*.previous_price" => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            // Each calon mitra's unit price, keyed by the mitra (Evaluasi Harga).
            "{$key}.*.quotes" => ['nullable', 'array', 'max:50'],
            "{$key}.*.quotes.*" => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
        ];
    }

    /**
     * The readable names of a row list's fields.
     *
     * @return array<string, string>
     */
    public static function rowAttributes(string $key): array
    {
        return [
            "{$key}.*.description" => 'uraian',
            "{$key}.*.volume" => 'volume',
            "{$key}.*.unit" => 'satuan',
            "{$key}.*.price_before" => 'harga satuan sebelum nego',
            "{$key}.*.unit_price" => 'harga satuan setelah nego',
            "{$key}.*.quotes.*" => 'harga mitra',
            "{$key}.*.previous_po_number" => 'no PO',
            "{$key}.*.previous_po_date" => 'tanggal PO',
            "{$key}.*.previous_supplier" => 'supplier',
            "{$key}.*.previous_price" => 'harga lalu',
        ];
    }
}
