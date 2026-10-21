<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\Procurement;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentRequest;
use Illuminate\Support\Facades\DB;

/**
 * Opens and scores a Formulir Penilaian Kinerja Penyedia Barang dan Jasa.
 */
class VendorAssessmentService
{
    /**
     * Open an assessment, laying out one empty score row per sheet and aspect.
     *
     * The rows exist from the start so every sheet prints in full, whether or
     * not its assessor has filled it in yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $author): VendorAssessment
    {
        return DB::transaction(function () use ($attributes, $author): VendorAssessment {
            $assessment = new VendorAssessment($attributes);
            $assessment->created_by = $author->id;
            $assessment->save();

            $this->syncScoreRows($assessment);

            return $assessment;
        });
    }

    /**
     * Make sure a score row exists for every active sheet and aspect pairing.
     *
     * Runs again whenever the form is opened, so an aspect added to a sheet in
     * the master data appears on assessments that are still being filled in.
     * Rows that already carry a level are never removed: they are the record.
     */
    public function syncScoreRows(VendorAssessment $assessment): void
    {
        $forms = AssessmentForm::query()->active()->ordered()->with('aspects')->get();

        $existing = $assessment->scores()
            ->get(['assessment_form_id', 'assessment_aspect_id'])
            ->map(fn ($score): string => $score->assessment_form_id.':'.$score->assessment_aspect_id)
            ->all();

        $wanted = [];

        foreach ($forms as $form) {
            foreach ($form->aspects as $aspect) {
                $key = $form->id.':'.$aspect->id;
                $wanted[] = $key;

                if (! in_array($key, $existing, true)) {
                    $assessment->scores()->create([
                        'assessment_form_id' => $form->id,
                        'assessment_aspect_id' => $aspect->id,
                    ]);
                }
            }
        }

        $assessment->scores()
            ->whereNull('level')
            ->get()
            ->each(function ($score) use ($wanted): void {
                if (! in_array($score->assessment_form_id.':'.$score->assessment_aspect_id, $wanted, true)) {
                    $score->delete();
                }
            });

        $assessment->unsetRelation('scores');
    }

    /**
     * Prefill the header of a new assessment from a procurement, or from the
     * invoice details a vendor sent through a request link.
     *
     * The details the header has no field for (termin, nilai tagihan and the
     * BAPP/BASTP number) are kept in the internal notes.
     *
     * @return array<string, mixed>
     */
    public function defaultsFrom(?Procurement $procurement, ?VendorAssessmentRequest $request = null): array
    {
        $defaults = [
            'procurement_id' => $procurement?->id,
            'project' => $procurement === null ? '' : $procurement->name,
            'po_number' => null,
            'po_date' => null,
            'bastp_date' => null,
            'vendor_name' => '',
            'work_director_name' => '',
            'has_penalty' => false,
            'notes' => '',
            'vendor_assessment_request_id' => null,
            'form_number' => 'SMT-FM-DAN-02.02',
            'revision_number' => '03',
            'form_date' => now()->toDateString(),
            'place' => 'Kendari',
        ];

        if ($request === null) {
            return $defaults;
        }

        return [
            ...$defaults,
            'project' => $request->project,
            'vendor_name' => $request->vendor_name,
            'po_number' => $request->contract_number,
            'po_date' => $request->contract_date->toDateString(),
            'bastp_date' => $request->bastp_date->toDateString(),
            'has_penalty' => $request->has_penalty,
            'vendor_assessment_request_id' => $request->id,
            'notes' => implode("\n", [
                'Termin: '.$request->term,
                'Nilai tagihan: Rp '.number_format((float) $request->invoice_amount, 0, ',', '.'),
                'No BAPP/BASTP: '.$request->bastp_number,
                'Diisi oleh: '.$request->submitter_name
                    .($request->submitter_phone ? ' ('.$request->submitter_phone.')' : ''),
            ]),
        ];
    }
}
