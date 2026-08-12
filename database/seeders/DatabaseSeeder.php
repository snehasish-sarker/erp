<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            [
                'code' => 'ERP-DEMO',
            ],
            [
                'name' => 'ERP Demo Company',
                'slug' => 'erp-demo-company',
                'status' => 'active',
                'currency_code' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'email' => 'company@erp.test',
                'phone' => null,
                'address' => 'Dhaka, Bangladesh',
            ],
        );

        $tenant->users()->updateOrCreate(
            [
                'email' => 'admin@erp.test',
            ],
            [
                'branch_id' => null,
                'name' => 'ERP Administrator',
                'status' => 'active',
                'password' => Hash::make(
                    'Password123!',
                ),
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            PlatformAdminSeeder::class,
            SaasPlanSeeder::class,
            PermissionSeeder::class,
            DefaultChartOfAccountsSeeder::class,
        ]);
    }
}