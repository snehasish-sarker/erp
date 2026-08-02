<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && $this->user()?->can(
                'update',
                $warehouse,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        $currentBranchId = $warehouse instanceof Warehouse
            ? (int) $warehouse->branch_id
            : null;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static function (
                            Builder $query,
                        ) use (
                            $tenantId,
                            $currentBranchId,
                        ): Builder {
                            return $query
                                ->where('tenant_id', $tenantId)
                                ->whereNull('deleted_at')
                                ->where(
                                    static function (
                                        Builder $branchQuery,
                                    ) use ($currentBranchId): void {
                                        $branchQuery->where(
                                            'status',
                                            '!=',
                                            'archived',
                                        );

                                        if ($currentBranchId !== null) {
                                            $branchQuery->orWhere(
                                                'id',
                                                $currentBranchId,
                                            );
                                        }
                                    },
                                );
                        },
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('warehouses', 'code')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query->where(
                                'tenant_id',
                                $tenantId,
                            ),
                    )
                    ->ignore(
                        $warehouse instanceof Warehouse
                            ? $warehouse->getKey()
                            : null,
                    ),
            ],
            'type' => [
                'required',
                'string',
                Rule::in([
                    'general',
                    'transit',
                    'returns',
                    'damaged',
                ]),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],
            'is_default' => [
                'required',
                'boolean',
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                if (
                    $this->boolean('is_default')
                    && $this->string('status')->toString() !== 'active'
                ) {
                    $validator->errors()->add(
                        'is_default',
                        'Only an active warehouse can be the default warehouse.',
                    );
                }
            },
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->integer('branch_id'),
            'name' => trim(
                $this->string('name')->toString(),
            ),
            'code' => Str::upper(
                trim(
                    $this->string('code')->toString(),
                ),
            ),
            'type' => trim(
                $this->string('type')->toString(),
            ),
            'status' => trim(
                $this->string('status')->toString(),
            ),
            'is_default' => $this->boolean('is_default'),
            'address' => $this->nullableTrimmedString('address'),
        ]);
    }

    private function nullableTrimmedString(
        string $key,
    ): ?string {
        $value = trim(
            $this->string($key)->toString(),
        );

        return $value === '' ? null : $value;
    }
}