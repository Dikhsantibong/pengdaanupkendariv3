<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReportController extends Controller
{
    /**
     * Show the procurement report with the current filters applied.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $query = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($request->user())
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor']),
            $request,
        );

        $totals = (clone $query)->selectRaw('count(*) as total, coalesce(sum(hpe_value), 0) as hpe')->first();

        return Inertia::render('reports/index', [
            'procurements' => ProcurementResource::collection(
                $query->latest('created_at')->paginate(50)->withQueryString(),
            ),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
            'totals' => [
                'count' => (int) ($totals->total ?? 0),
                'hpe' => (float) ($totals->hpe ?? 0),
            ],
        ]);
    }

    /**
     * Download the filtered report as a CSV file.
     */
    public function export(Request $request): HttpResponse
    {
        $this->authorize('viewAny', Procurement::class);

        $rows = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($request->user())
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor']),
            $request,
        )->latest('created_at')->get();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membuat berkas laporan sementara.');
        }

        fputcsv($handle, [
            'Nomor Pengadaan', 'Nama Pengadaan', 'Direksi Pekerjaan', 'Unit Tujuan',
            'Metode Pengadaan', 'Sumber Anggaran', 'Nomor PR/RO', 'Nomor PRK',
            'Nilai HPE', 'Status Progres', 'PIC Perencana', 'PIC Pelaksana',
            'Status Persetujuan', 'Dibuat',
        ]);

        foreach ($rows as $procurement) {
            fputcsv($handle, [
                $procurement->number,
                $procurement->name,
                $procurement->workDirector->name,
                $procurement->targetUnit->name,
                $procurement->procurement_method_id === null ? '-' : $procurement->procurementMethod->name,
                $procurement->budget_source_id === null ? '-' : $procurement->budgetSource->name,
                $procurement->pr_ro_number_id === null ? '-' : $procurement->prRoNumber->number,
                $procurement->prk_number ?? '-',
                number_format((float) $procurement->hpe_value, 2, ',', '.'),
                $procurement->progressStatus->name,
                $procurement->planner_id === null ? '-' : $procurement->planner->name,
                $procurement->executor_id === null ? '-' : $procurement->executor->name,
                $procurement->planning_approval_state->label(),
                $procurement->created_at?->format('Y-m-d H:i'),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pengadaan-'.now()->format('Ymd-His').'.csv"',
        ]);
    }
}
