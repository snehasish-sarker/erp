<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Tenant;

final class UpdateCompanySettingsAction
{
    /**
     * @param array{
     *     name: string,
     *     currency_code: string,
     *     timezone: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null
     * } $attributes
     */
    public function execute(
        Tenant $tenant,
        array $attributes,
    ): Tenant {
        $tenant->fill($attributes);
        $tenant->save();

        return $tenant->refresh();
    }
}