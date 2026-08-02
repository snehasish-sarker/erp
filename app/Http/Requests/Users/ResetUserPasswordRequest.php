<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('managedUser');

        return $managedUser instanceof User
            && $this->user()?->can(
                'resetPassword',
                $managedUser,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers(),
            ],
        ];
    }
}