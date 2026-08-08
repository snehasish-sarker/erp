<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

final class UpdateManagementBudgetRequest extends ManagementBudgetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management_budgets.update') === true;
    }
}
