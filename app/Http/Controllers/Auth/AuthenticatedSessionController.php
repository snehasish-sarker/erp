<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CommonResponseService $responseService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(
        LoginRequest $request,
    ): RedirectResponse {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user instanceof User) {
            $this->auditLogService->recordCustomEvent(
                subject: $user,
                event: 'login',
                metadata: [
                    'remembered' =>
                        $request->boolean('remember'),
                ],
            );
        }

        return $this->responseService->success(
            message: 'Welcome back. You have signed in successfully.',
            redirectTo: route('dashboard'),
        );
    }

    public function destroy(
        Request $request,
    ): RedirectResponse {
        $user = $request->user();

        /*
         * Record the event before clearing authentication so the actor
         * snapshot and tenant can be resolved safely.
         */
        if ($user instanceof User) {
            $this->auditLogService->recordCustomEvent(
                subject: $user,
                event: 'logout',
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->responseService->success(
            message: 'You have signed out successfully.',
            redirectTo: route('login'),
        );
    }
}