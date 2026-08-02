<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && $this->user()?->can(
                'assignPermissions',
                $role,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'permission_ids' => [
                'present',
                'array',
            ],
            'permission_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query->where(
                                'guard_name',
                                'web',
                            ),
                    ),
            ],
        ];
    }
}