<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'brands.create',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'website_url' => [
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim(
            (string) $this->input('slug'),
        );

        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),

            'code' => mb_strtoupper(
                trim(
                    (string) $this->input('code'),
                ),
            ),

            'slug' => $slug !== ''
                ? Str::slug($slug)
                : null,

            'website_url' => $this->filled(
                'website_url',
            )
                ? trim(
                    (string) $this->input(
                        'website_url',
                    ),
                )
                : null,

            'description' => $this->filled(
                'description',
            )
                ? trim(
                    (string) $this->input(
                        'description',
                    ),
                )
                : null,

            'sort_order' => $this->input(
                'sort_order',
                0,
            ),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'status',
                        'active',
                    ),
                ),
            ),
        ]);
    }
}