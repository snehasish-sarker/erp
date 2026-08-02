<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
            'Authentication is required.',
        );

        $tenant = $user->tenant()->first();

        abort_if(
            $tenant === null,
            Response::HTTP_FORBIDDEN,
            'No tenant is assigned to this user.',
        );

        $tenantId = (int) $tenant->getKey();

        $this->tenantContext->set($tenant);
        $this->permissionRegistrar->setPermissionsTeamId($tenantId);

        try {
            return $next($request);
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId(null);
            $this->tenantContext->clear();
        }
    }
}