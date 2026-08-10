<?php

namespace App\Http\Controllers;

use App\Http\Requests\VendorAssessments\IssueSigningLinkRequest;
use App\Http\Requests\VendorAssessments\StoreVendorAssessmentRequest;
use App\Http\Requests\VendorAssessments\UpdateVendorAssessmentRequest;
use App\Http\Requests\VendorAssessments\UpdateVendorAssessmentScoresRequest;
use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use App\Models\Procurement;
use App\Models\VendorAssessment;
use App\Services\AssessmentSigningService;
use App\Services\VendorAssessmentRenderer;
use App\Services\VendorAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class VendorAssessmentController extends Controller
{
    public function __construct(
        protected VendorAssessmentService $assessments,
        protected VendorAssessmentRenderer $renderer,
        protected AssessmentSigningService $signing,
    ) {}

    /**
     * List the assessments already opened.
     */
    public function index(Request $request): Response
    {
        $assessments = VendorAssessment::query()
            ->with(['procurement', 'creator', 'scores'])
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('project', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (VendorAssessment $assessment): array => [
                'id' => $assessment->id,
                'project' => $assessment->project,
                'vendor_name' => $assessment->vendor_name,
                'po_number' => $assessment->po_number,
                'po_date' => $assessment->po_date?->toDateString(),
                'procurement_number' => $assessment->procurement?->number,
                'overall_average' => $assessment->overallAverage(),
                'scored' => $assessment->scores->whereNotNull('level')->count(),
                'total' => $assessment->scores->count(),
                'created_by' => $assessment->creator?->name,
                'created_at' => $assessment->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('vendor-assessments/index', [
            'assessments' => $assessments,
            'filters' => [
                'search' => $request->string('search')->trim()->value() ?: null,
            ],
        ]);
    }

    /**
     * Show the form for opening a new assessment.
     */
    public function create(Request $request): Response
    {
        $procurement = $request->integer('procurement_id')
            ? Procurement::query()->find($request->integer('procurement_id'))
            : null;

        return Inertia::render('vendor-assessments/create', [
            'defaults' => $this->assessments->defaultsFrom($procurement),
            'procurements' => Procurement::query()
                ->latest('created_at')
                ->limit(200)
                ->get(['id', 'number', 'name'])
                ->map(fn (Procurement $row): array => [
                    'value' => $row->id,
                    'label' => "{$row->number} — {$row->name}",
                ])->all(),
        ]);
    }

    /**
     * Open a new assessment with an empty score sheet.
     */
    public function store(StoreVendorAssessmentRequest $request): RedirectResponse
    {
        $assessment = $this->assessments->create($request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Formulir penilaian kinerja penyedia dibuat.',
        ]);

        return to_route('vendor-assessments.show', $assessment);
    }

    /**
     * Show one assessment: every sheet, and the master recap.
     */
    public function show(VendorAssessment $assessment): Response
    {
        // Catch up with any aspect added to a sheet since this was opened.
        $this->assessments->syncScoreRows($assessment);

        $assessment->load(['procurement', 'creator', 'scores.scoredBy', 'invitations.form']);

        return Inertia::render('vendor-assessments/show', [
            'assessment' => $this->headerPayload($assessment),
            'forms' => $this->formsPayload($assessment),
            'recap' => $this->recapPayload($assessment),
            'panitiaUrl' => URL::signedRoute('vendor-assessments.download-panitia', [
                'assessment' => $assessment->id,
            ]),
            'akumulasiUrl' => URL::signedRoute('vendor-assessments.download-akumulasi', [
                'assessment' => $assessment->id,
            ]),
            'printAllUrl' => route('vendor-assessments.print-all', [
                'assessment' => $assessment->id,
            ]),
        ]);
    }

    /**
     * Correct the header of an assessment.
     */
    public function update(
        UpdateVendorAssessmentRequest $request,
        VendorAssessment $assessment,
    ): RedirectResponse {
        $assessment->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data formulir diperbarui.']);

        return back();
    }

    /**
     * Record the levels given on one assessor sheet.
     */
    public function updateScores(
        UpdateVendorAssessmentScoresRequest $request,
        VendorAssessment $assessment,
        AssessmentForm $form,
    ): RedirectResponse {
        /** @var array<int, array{aspect_id: int, level: int|null, note: string|null}> $rows */
        $rows = $request->validated('scores');

        foreach ($rows as $row) {
            $score = $assessment->scores()
                ->where('assessment_form_id', $form->id)
                ->where('assessment_aspect_id', $row['aspect_id'])
                ->first();

            if ($score === null) {
                continue;
            }

            $level = $row['level'] ?? null;

            $score->update([
                'level' => $level,
                'note' => $row['note'] ?? null,
                // Only stamp the assessor when a level is actually given, so an
                // emptied row does not look like somebody scored it.
                'scored_by' => $level === null ? null : $request->user()->id,
                'scored_at' => $level === null ? null : now(),
            ]);
        }

        if ($request->filled('assessor_name')) {
            $form->update(['assessor_name' => $request->string('assessor_name')->trim()->value()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Penilaian {$form->name} tersimpan.",
        ]);

        return back();
    }

    /**
     * Print one assessor sheet, or the master recap when no sheet is named.
     *
     * Defaults to PDF; `?format=html` returns the printable page instead.
     */
    public function print(
        Request $request,
        VendorAssessment $assessment,
        ?AssessmentForm $form = null,
    ): HttpResponse {
        if ($form !== null && ! $form->exists) {
            $form = null;
        }

        if ($request->string('format')->value() === 'html') {
            return response($this->renderer->html($assessment, $form), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        return response($this->renderer->pdf($assessment, $form), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                .$this->renderer->fileName($assessment, $form).'"',
        ]);
    }

    /**
     * View/print all sheets combined including recap as a single PDF.
     */
    public function printAll(Request $request, VendorAssessment $assessment): HttpResponse
    {
        $fileName = 'semua-penilaian-kinerja-'.$assessment->id.'-'.\Illuminate\Support\Str::slug($assessment->vendor_name).'.pdf';

        return response($this->renderer->allPdf($assessment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Issue, or re-issue, the signing link for one sheet.
     */
    public function issueLink(
        IssueSigningLinkRequest $request,
        VendorAssessment $assessment,
        AssessmentForm $form,
    ): RedirectResponse {
        $this->signing->issue(
            $assessment,
            $form,
            $request->user(),
            $request->string('recipient_name')->trim()->value() ?: null,
            $request->string('recipient_phone')->trim()->value() ?: null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Tautan penilaian {$form->name} dibuat. Tautan lama tidak berlaku lagi.",
        ]);

        return back();
    }

    /**
     * Withdraw the signing link for one sheet.
     */
    public function revokeLink(
        VendorAssessment $assessment,
        AssessmentForm $form,
    ): RedirectResponse {
        $invitation = $assessment->invitations()
            ->where('assessment_form_id', $form->id)
            ->firstOr(fn () => abort(404));

        $this->signing->revoke($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tautan dibatalkan.']);

        return back();
    }

    /**
     * Download every sheet and the recap as one archive.
     */
    public function downloadAll(VendorAssessment $assessment): BinaryFileResponse
    {
        $forms = AssessmentForm::query()->active()->ordered()->get();

        $archive = tempnam(sys_get_temp_dir(), 'penilaian');

        abort_if($archive === false, 500, 'Tidak dapat menyiapkan arsip.');

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        $zip->addFromString(
            '00-akumulasi.pdf',
            $this->renderer->pdf($assessment),
        );

        foreach ($forms as $index => $form) {
            $zip->addFromString(
                str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-'.$form->code.'.pdf',
                $this->renderer->pdf($assessment, $form),
            );
        }

        $zip->close();

        $name = 'penilaian-kinerja-'.$assessment->id.'-'
            .Str::slug($assessment->vendor_name).'.zip';

        // Deleted after the response is flushed, so the temp file never lingers.
        return response()->download($archive, $name, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    /**
     * Download the merged PDF of all assessor sheets (without recap) via signed URL.
     */
    public function downloadPanitia(VendorAssessment $assessment): HttpResponse
    {
        $fileName = 'penilaian-kinerja-'.$assessment->id.'-'.Str::slug($assessment->vendor_name).'.pdf';

        return response($this->renderer->panitiaPdf($assessment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Download just the recap (akumulasi) PDF via signed URL.
     */
    public function downloadAkumulasi(VendorAssessment $assessment): HttpResponse
    {
        $fileName = 'akumulasi-penilaian-kinerja-'.$assessment->id.'-'.Str::slug($assessment->vendor_name).'.pdf';

        return response($this->renderer->pdf($assessment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Archive an assessment.
     */
    public function destroy(VendorAssessment $assessment): RedirectResponse
    {
        $assessment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Formulir penilaian diarsipkan.']);

        return to_route('vendor-assessments.index');
    }

    /**
     * The header fields printed at the top of every sheet.
     *
     * @return array<string, mixed>
     */
    protected function headerPayload(VendorAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'procurement_id' => $assessment->procurement_id,
            'procurement_number' => $assessment->procurement?->number,
            'project' => $assessment->project,
            'po_number' => $assessment->po_number,
            'po_date' => $assessment->po_date?->toDateString(),
            'bastp_date' => $assessment->bastp_date?->toDateString(),
            'vendor_name' => $assessment->vendor_name,
            'form_number' => $assessment->form_number,
            'revision_number' => $assessment->revision_number,
            'form_date' => $assessment->form_date?->toDateString(),
            'place' => $assessment->place,
            'notes' => $assessment->notes,
            'created_by' => $assessment->creator?->name,
            'created_at' => $assessment->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Every assessor sheet with its aspects and current levels.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formsPayload(VendorAssessment $assessment): array
    {
        return AssessmentForm::query()
            ->active()
            ->ordered()
            ->with('aspects')
            ->get()
            ->map(function (AssessmentForm $form) use ($assessment): array {
                $scores = $assessment->scoresForForm($form->id);
                $aspects = [];

                foreach ($form->aspects as $aspect) {
                    $score = $scores->firstWhere('assessment_aspect_id', $aspect->id);

                    $aspects[] = [
                        'aspect_id' => $aspect->id,
                        'name' => $aspect->name,
                        'preamble' => $aspect->preamble,
                        'indicators' => $aspect->indicators,
                        'sort_order' => $aspect->sort_order,
                        'level' => $score?->level,
                        'note' => $score?->note,
                        'scored_by' => $score?->scoredBy?->name,
                        'scored_at' => $score?->scored_at?->toDateTimeString(),
                    ];
                }

                $invitation = $assessment->invitationForForm($form->id);

                return [
                    'id' => $form->id,
                    'code' => $form->code,
                    'name' => $form->name,
                    'assessor_title' => $form->assessor_title,
                    'assessor_name' => $form->assessor_name,
                    'assessor_options' => $form->assessor_options ?? [],
                    'description' => $form->description,
                    'aspects' => $aspects,
                    'invitation' => $invitation === null ? null : [
                        'url' => $invitation->url(),
                        'whatsapp_url' => $invitation->recipient_phone === null
                            ? null
                            : $invitation->whatsappUrl($assessment->project, $assessment->vendor_name),
                        'recipient_name' => $invitation->recipient_name,
                        'recipient_phone' => $invitation->recipient_phone,
                        'expires_at' => $invitation->expires_at?->toDateTimeString(),
                        'opened_at' => $invitation->opened_at?->toDateTimeString(),
                        'submitted_at' => $invitation->submitted_at?->toDateTimeString(),
                        'revoked_at' => $invitation->revoked_at?->toDateTimeString(),
                        'is_open' => $invitation->isOpen(),
                        'has_signature' => $invitation->signature_path !== null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * The master recap: every aspect averaged across its assessors.
     *
     * @return array<string, mixed>
     */
    protected function recapPayload(VendorAssessment $assessment): array
    {
        $aspects = [];

        foreach (AssessmentAspect::query()->active()->ordered()->get() as $aspect) {
            $scores = $assessment->scores->where('assessment_aspect_id', $aspect->id);
            $contributors = [];

            foreach ($scores->whereNotNull('level') as $score) {
                $contributors[] = [
                    'form' => $score->form->name,
                    'level' => $score->level,
                ];
            }

            $aspects[] = [
                'aspect_id' => $aspect->id,
                'name' => $aspect->name,
                'preamble' => $aspect->preamble,
                'indicators' => $aspect->indicators,
                'average' => $assessment->averageFor($aspect->id),
                'contributors' => $contributors,
                'pending' => $scores->whereNull('level')->count(),
            ];
        }

        return [
            'aspects' => $aspects,
            'overall_average' => $assessment->overallAverage(),
            'scored' => $assessment->scores->whereNotNull('level')->count(),
            'total' => $assessment->scores->count(),
        ];
    }
}
