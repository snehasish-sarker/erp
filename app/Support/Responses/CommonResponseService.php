<?php

declare(strict_types=1);

namespace App\Support\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class CommonResponseService
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function success(
        string $message,
        mixed $data = null,
        array $meta = [],
        ?string $redirectTo = null,
        int $status = 200,
    ): JsonResponse|RedirectResponse {
        if ($this->request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'meta' => $meta,
            ], $status);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return $this->redirect($redirectTo);
    }

    /**
     * @param array<string, array<int, string>|string> $errors
     */
    public function error(
        string $message,
        array $errors = [],
        string $code = 'REQUEST_FAILED',
        ?string $redirectTo = null,
        int $status = 422,
    ): JsonResponse|RedirectResponse {
        if ($this->request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'code' => $code,
            ], $status);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
            'code' => $code,
        ]);

        $response = $this->redirect($redirectTo);

        if ($errors !== []) {
            $response->withErrors($errors);
        }

        return $response->withInput();
    }

    private function redirect(
        ?string $redirectTo,
    ): RedirectResponse {
        if ($redirectTo !== null) {
            return redirect()->to($redirectTo);
        }

        return back();
    }
}