<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureBranchAccess
{
    public function __construct(
        private BranchAccessService $branchAccessService,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $routeParameter = 'branch',
        string $requireActive = 'false',
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $routeValue = $request->route(
            $routeParameter,
        );

        if ($routeValue instanceof Branch) {
            $branch = $routeValue;
        } else {
            abort_unless(
                is_int($routeValue)
                || (
                    is_string($routeValue)
                    && ctype_digit($routeValue)
                ),
                404,
            );

            $branch = Branch::query()
                ->findOrFail(
                    (int) $routeValue,
                );

            $request
                ->route()
                ?->setParameter(
                    $routeParameter,
                    $branch,
                );
        }

        $mustBeActive = filter_var(
            $requireActive,
            FILTER_VALIDATE_BOOL,
        );

        $this->branchAccessService
            ->authorizeBranch(
                user: $user,
                branch: $branch,
                requireActive: $mustBeActive,
            );

        return $next($request);
    }
}