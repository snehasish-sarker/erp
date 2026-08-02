<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tenant_code' => [
                'required',
                'string',
                'max:50',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $tenant = Tenant::query()
            ->where('code', $this->string('tenant_code')->toString())
            ->whereIn('status', [
                'trial',
                'active',
            ])
            ->first();

        $authenticated = $tenant !== null
            && Auth::guard('web')->attempt(
                [
                    'tenant_id' => $tenant->getKey(),
                    'email' => $this->string('email')->toString(),
                    'password' => $this->string('password')->toString(),
                    'status' => 'active',
                ],
                $this->boolean('remember'),
            );

        if (!$authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'The provided company code or credentials are incorrect.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn(
            $this->throttleKey(),
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower(
                $this->string('tenant_code')->toString()
                .'|'
                .$this->string('email')->toString()
                .'|'
                .$this->ip(),
            ),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_code' => Str::upper(
                trim($this->string('tenant_code')->toString()),
            ),
            'email' => Str::lower(
                trim($this->string('email')->toString()),
            ),
        ]);
    }
}