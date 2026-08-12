<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformLoginRequest;
use App\Models\PlatformAdmin;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformAuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render(
            'Platform/Auth/Login',
        );
    }

    public function store(
        PlatformLoginRequest $request,
    ): RedirectResponse {
        $request->authenticate();
        $request->session()->regenerate();

        $admin = Auth::guard('platform')->user();

        if ($admin instanceof PlatformAdmin) {
            $admin->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();
        }

        return $this->responseService->success(
            message: 'Welcome to the Super Admin console.',
            redirectTo: route('platform.dashboard'),
        );
    }

    public function destroy(
        Request $request,
    ): RedirectResponse {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->responseService->success(
            message: 'You have signed out of the Super Admin console.',
            redirectTo: route('platform.login'),
        );
    }
}
