<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsActive
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATUSES = [
        'trial',
        'active',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $tenant = $this->tenantContext->tenant();

        abort_unless(
            in_array(
                $tenant->status,
                self::ALLOWED_STATUSES,
                true,
            ),
            Response::HTTP_FORBIDDEN,
            'This company account is not currently active.',
        );

        return $next($request);
    }
}