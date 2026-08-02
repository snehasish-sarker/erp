<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateCompanySettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanySettingsRequest;
use App\Models\Tenant;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CompanySettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UpdateCompanySettingsAction $updateCompanySettings,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function edit(): Response
    {
        Gate::authorize('company_settings.view');

        return Inertia::render(
            'Settings/Company/Edit',
            [
                'company' => $this->companyData(
                    $this->tenantContext->tenant(),
                ),
                'timezoneOptions' => DateTimeZone::listIdentifiers(),
            ],
        );
    }

    public function update(
        UpdateCompanySettingsRequest $request,
    ): JsonResponse|RedirectResponse {
        $tenant = $this->updateCompanySettings->execute(
            tenant: $this->tenantContext->tenant(),
            attributes: $request->validated(),
        );

        return $this->responseService->success(
            message: 'Company settings updated successfully.',
            data: $this->companyData($tenant),
            redirectTo: route('company-settings.edit'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     slug: string,
     *     status: string,
     *     currency_code: string,
     *     timezone: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * }
     */
    private function companyData(
        Tenant $tenant,
    ): array {
        return [
            'id' => (int) $tenant->getKey(),
            'name' => $tenant->name,
            'code' => $tenant->code,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'currency_code' => $tenant->currency_code,
            'timezone' => $tenant->timezone,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
        ];
    }
}