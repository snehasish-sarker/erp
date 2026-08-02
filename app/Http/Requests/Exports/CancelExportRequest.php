<?php

declare(strict_types=1);

namespace App\Http\Requests\Exports;

use App\Models\ExportRequest;
use Illuminate\Foundation\Http\FormRequest;

final class CancelExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exportRequest = $this->route(
            'exportRequest',
        );

        return $exportRequest instanceof ExportRequest
            && $this->user()?->can(
                'cancel',
                $exportRequest,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}