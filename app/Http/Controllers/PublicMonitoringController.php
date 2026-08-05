<?php

namespace App\Http\Controllers;

use App\Support\ExecutionBoard;
use App\Support\PlanningBoard;
use Inertia\Inertia;
use Inertia\Response;

class PublicMonitoringController extends Controller
{
    public function __construct(
        protected PlanningBoard $planningBoard,
        protected ExecutionBoard $executionBoard,
    ) {}

    /**
     * Show the public board for the planning stage.
     *
     * The page polls this endpoint, so every prop is recomputed on each request
     * and nothing here requires authentication.
     */
    public function planning(): Response
    {
        return Inertia::render('public-monitoring/planning', $this->planningBoard->payload());
    }

    /**
     * Show the public board for the execution stage.
     */
    public function execution(): Response
    {
        return Inertia::render('public-monitoring/execution', $this->executionBoard->payload());
    }
}
