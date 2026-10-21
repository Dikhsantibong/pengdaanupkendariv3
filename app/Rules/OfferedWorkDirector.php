<?php

namespace App\Rules;

use App\Models\AssessmentForm;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * A Direksi Pekerjaan picked from the names the Direksi Pekerjaan sheet
 * offers for the assessment's BASTP date. Without a list on that sheet the
 * name is taken as typed.
 */
class OfferedWorkDirector implements DataAwareRule, ValidationRule
{
    /**
     * The data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $sheet = AssessmentForm::workDirectorSheet();

        if ($sheet === null || ($sheet->assessor_options ?? []) === []) {
            return;
        }

        $offered = array_map(
            fn (string $option): string => mb_strtoupper(AssessmentForm::nameOf($option)),
            $sheet->optionsFor($this->bastpDate()),
        );

        if (! in_array(mb_strtoupper(trim((string) $value)), $offered, true)) {
            $fail('Direksi pekerjaan harus dipilih dari daftar yang tersedia untuk tanggal BASTP ini.');
        }
    }

    /**
     * The BASTP date given alongside, if it reads as a date.
     */
    protected function bastpDate(): ?CarbonImmutable
    {
        $date = $this->data['bastp_date'] ?? null;

        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (Throwable) {
            return null;
        }
    }
}
