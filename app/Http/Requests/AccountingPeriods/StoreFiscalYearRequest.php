<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountingPeriods;

use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'accounting_periods.generate',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        return [
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9\-\/]*$/',

                Rule::unique('fiscal_years', 'code')
                    ->where(
                        static fn (
                            Builder $query,
                        ): Builder => $query->where(
                            'tenant_id',
                            $tenantId,
                        ),
                    ),
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $validator->errors()->has(
                        'start_date',
                    )
                ) {
                    return;
                }

                $startDate = $this->input(
                    'start_date',
                );

                if (!is_string($startDate)) {
                    return;
                }

                try {
                    $date = CarbonImmutable::parse(
                        $startDate,
                    );
                } catch (\Throwable) {
                    return;
                }

                if ($date->day !== 1) {
                    $validator->errors()->add(
                        'start_date',
                        'The fiscal year must begin on the first day of a month.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),

            'code' => mb_strtoupper(
                trim(
                    (string) $this->input('code'),
                ),
            ),
        ]);
    }
}