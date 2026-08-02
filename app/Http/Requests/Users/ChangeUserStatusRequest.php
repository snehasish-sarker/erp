<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('managedUser');

        return $managedUser instanceof User
            && $this->user()?->can(
                'changeStatus',
                $managedUser,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                    'archived',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim(
                $this->string('status')->toString(),
            ),
        ]);
    }
}