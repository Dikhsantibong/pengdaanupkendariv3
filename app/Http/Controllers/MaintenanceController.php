<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMaintenanceRequest;
use App\Support\MaintenanceMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's switch for the maintenance mode.
 */
class MaintenanceController extends Controller
{
    /**
     * Show the maintenance mode and what the login page says.
     */
    public function edit(): Response
    {
        return Inertia::render('maintenance/edit', [
            'maintenance' => MaintenanceMode::state(),
            'defaultMessage' => MaintenanceMode::DEFAULT_MESSAGE,
        ]);
    }

    /**
     * Switch the maintenance mode on or off.
     */
    public function update(UpdateMaintenanceRequest $request): RedirectResponse
    {
        $active = $request->boolean('active');
        $until = $request->filled('until')
            ? CarbonImmutable::parse($request->string('until')->value(), config('app.timezone'))
            : null;

        MaintenanceMode::update($active, $request->input('message'), $until, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active
                ? 'Mode pemeliharaan aktif. Hanya Administrator yang dapat masuk.'
                : 'Mode pemeliharaan dimatikan. Aplikasi dapat digunakan kembali.',
        ]);

        return back();
    }
}
