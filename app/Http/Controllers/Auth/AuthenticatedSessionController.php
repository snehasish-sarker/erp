<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CommonResponseService $responseService,
        private readonly AuditLogService $auditLogService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render(
            'Auth/Login',
        );
    }

    public function store(
        LoginRequest $request,
    ): RedirectResponse {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user instanceof User) {
            $this->recordAuthenticationEvent(
                user: $user,
                event: 'login',
                metadata: [
                    'remembered' =>
                        $request->boolean('remember'),
                ],
            );
        }

        return $this->responseService->success(
            message:
                'Welcome back. You have signed in successfully.',
            redirectTo: route('dashboard'),
        );
    }

    public function destroy(
        Request $request,
    ): RedirectResponse {
        $user = $request->user();

        /*
         * Record logout before authentication is cleared so the
         * audit trail retains the authenticated actor snapshot.
         */
        if ($user instanceof User) {
            $this->recordAuthenticationEvent(
                user: $user,
                event: 'logout',
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->responseService->success(
            message:
                'You have signed out successfully.',
            redirectTo: route('login'),
        );
    }

    /**
     * Authentication routes run outside tenant.context middleware.
     *
     * Establish the authenticated user's tenant only while writing
     * the authentication audit event, then restore the previous
     * context.
     *
     * @param array<string, mixed> $metadata
     */
    private function recordAuthenticationEvent(
        User $user,
        string $event,
        array $metadata = [],
    ): void {
        $tenantId = (int) $user->tenant_id;

        if ($tenantId <= 0) {
            throw new LogicException(
                'The authenticated user does not have a valid tenant.',
            );
        }

        $tenant = Tenant::withTrashed()
            ->whereKey($tenantId)
            ->first();

        if (!$tenant instanceof Tenant) {
            throw new LogicException(
                'The authenticated user tenant could not be resolved.',
            );
        }

        $previousTenant = $this->tenantContext->get();

        $this->tenantContext->set(
            $tenant,
        );

        try {
            $this->auditLogService->recordCustomEvent(
                subject: $user,
                event: $event,
                metadata: $metadata,
            );
        } finally {
            if ($previousTenant instanceof Tenant) {
                $this->tenantContext->set(
                    $previousTenant,
                );
            } else {
                $this->tenantContext->clear();
            }
        }
    }
}