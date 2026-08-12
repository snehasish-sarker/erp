<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateTenantSaasInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
