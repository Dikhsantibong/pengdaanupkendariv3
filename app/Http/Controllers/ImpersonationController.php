<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * The administrator's "Pindah Akun" in the header.
 */
class ImpersonationController extends Controller
{
    /**
     * The accounts the administrator may switch into, filtered by a search.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(Impersonation::canStart($request->user(), $request), 403);

        $search = $request->string('search')->trim()->limit(100, '')->value();

        $users = Impersonation::targets()
            ->whereKeyNot($request->user()->getKey())
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('position', 'like', "%{$search}%")))
            ->orderBy('role')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'role', 'position']);

        return response()->json([
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->label(),
                'position' => $user->position,
            ])->all(),
            'needsPassword' => Impersonation::needsPassword($request),
        ]);
    }

    /**
     * Switch into the chosen account.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        /** @var User $administrator */
        $administrator = $request->user();

        abort_unless(Impersonation::canStart($administrator, $request), 403);
        abort_unless(Impersonation::canTarget($administrator, $user), 403);

        if (Impersonation::needsPassword($request)) {
            $request->validate(
                ['password' => ['required', 'string', 'current_password:web']],
                [],
                ['password' => 'kata sandi Anda'],
            );

            Impersonation::confirmPassword($request);
        }

        Impersonation::start($request, $administrator, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Anda sekarang bekerja sebagai {$user->name}. Klik \"Kembali\" di kanan atas untuk kembali ke akun Administrator.",
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Come back to the administrator account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(Impersonation::isActive($request), 403);

        $administrator = Impersonation::stop($request);

        if ($administrator === null) {
            // The administrator was deactivated or demoted meanwhile: nobody
            // is signed back in.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kembali ke akun Administrator.']);

        return redirect()->route('dashboard');
    }
}
