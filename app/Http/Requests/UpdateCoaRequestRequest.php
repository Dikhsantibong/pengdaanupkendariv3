<?php

namespace App\Http\Requests;

use App\Enums\CoaRequestStatus;
use App\Enums\CoaSegment;
use App\Enums\Permission;
use App\Models\CoaRequest;
use App\Support\CoaCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateCoaRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var CoaRequest|null $coaRequest */
        $coaRequest = $this->route('coaRequest');

        if ($user === null || $coaRequest === null) {
            return false;
        }

        // Keuangan / Administrator may edit any submitted or active COA request
        // (and Admin may edit drafts too) to make corrections directly.
        if ($user->hasPermission(Permission::ManageCoaRequests)) {
            return true;
        }

        // Staf / TL may edit their own draft before activation request is sent.
        return $user->hasPermission(Permission::RequestCoa)
            && $coaRequest->requested_by === $user->id
            && $coaRequest->status === CoaRequestStatus::Draft;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $defaultDistrict = CoaCatalog::formOptions()['defaultSegment2']['code'];

        $payload = [
            'segment_1' => Str::upper(trim((string) $this->input('segment_1', ''))),
            'segment_2' => Str::upper(trim((string) ($this->input('segment_2') ?: $defaultDistrict))),
            'segment_3' => Str::upper(trim((string) $this->input('segment_3', ''))),
            'segment_4' => Str::upper(trim((string) $this->input('segment_4', ''))),
            'segment_5' => Str::upper(trim((string) $this->input('segment_5', ''))),
            'segment_6' => Str::upper(trim((string) $this->input('segment_6', ''))),
            'segment_7' => Str::upper(trim((string) $this->input('segment_7', ''))),
            'expense_element' => Str::upper(trim((string) $this->input('expense_element', ''))),
            'action' => (string) $this->input('action', 'save'),
        ];

        if ($this->filled('coa_code')) {
            $payload['coa_code'] = Str::upper(trim((string) $this->input('coa_code')));
        }

        $this->merge($payload);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $canManage = $this->user()?->hasPermission(Permission::ManageCoaRequests) ?? false;
        $allowedActions = $canManage ? ['save', 'request_activation', 'activate'] : ['save', 'request_activation'];

        return [
            'segment_1' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment1)],
            'segment_2' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment2)],
            'segment_3' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment3)],
            'segment_4' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment4)],
            'segment_5' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment5)],
            'segment_6' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment6)],
            'segment_7' => ['required', 'string', 'max:32', $this->segmentExists(CoaSegment::Segment7)],
            'expense_element' => ['required', 'string', 'max:64', $this->segmentExists(CoaSegment::ExpenseElement)],
            'coa_code' => $canManage ? ['nullable', 'string', 'max:120', 'regex:/^[A-Z0-9\/._-]+$/'] : ['prohibited'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', Rule::in($allowedActions)],
        ];
    }

    /**
     * Custom attribute labels in Indonesian.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'segment_1' => 'Segment 1 (Struktur Bisnis)',
            'segment_2' => 'Segment 2 (District)',
            'segment_3' => 'Segment 3 (Fungsi)',
            'segment_4' => 'Segment 4 (Lokasi)',
            'segment_5' => 'Segment 5 (Unit)',
            'segment_6' => 'Segment 6 (Sub Unit)',
            'segment_7' => 'Segment 7 (Aktivitas)',
            'expense_element' => 'Expense Element',
            'coa_code' => 'Kode COA',
            'description' => 'Uraian Pekerjaan',
            'notes' => 'Catatan',
        ];
    }

    /**
     * Check that a submitted code exists in Master Data Segmen COA.
     */
    protected function segmentExists(CoaSegment $segment): Exists
    {
        return Rule::exists('coa_segment_options', 'code')
            ->where('segment', $segment->value)
            ->whereNull('deleted_at');
    }
}
