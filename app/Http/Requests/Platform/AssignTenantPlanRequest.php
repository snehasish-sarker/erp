<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AssignTenantPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'saas_plan_id' => [
                'required',
                'integer',
                Rule::exists('saas_plans', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'billing_cycle' => [
                'required',
                'string',
                Rule::in(['monthly', 'annual']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_cycle' => Str::lower(
                trim((string) $this->input('billing_cycle', 'monthly')),
            ),
        ]);
    }
}
