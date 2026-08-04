<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\Accounting\DefaultChartOfAccountsService;
use Illuminate\Database\Seeder;

final class DefaultChartOfAccountsSeeder extends Seeder
{
    public function __construct(
        private readonly DefaultChartOfAccountsService $service,
    ) {
    }

    public function run(): void
    {
        Tenant::query()
            ->orderBy('id')
            ->each(
                function (Tenant $tenant): void {
                    $this->service->provisionForTenant(
                        $tenant,
                    );
                },
            );
    }
}