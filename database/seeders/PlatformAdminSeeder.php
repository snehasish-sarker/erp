<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

final class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $platformAdmin = PlatformAdmin::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'email' => 'superadmin@erp.test',
                ],
                [
                    'name' => 'ERP Platform Administrator',
                    'password' => 'Password123!',
                    'status' => 'active',
                ],
            );

        if ($platformAdmin->trashed()) {
            $platformAdmin->restore();
        }
    }
}