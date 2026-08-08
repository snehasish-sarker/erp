<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

final class StoreManagementBudgetRequest extends ManagementBudgetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management_budgets.create') === true;
    }
}
