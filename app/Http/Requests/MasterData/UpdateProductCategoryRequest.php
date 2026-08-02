<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $productCategory = $this->route(
            'productCategory',
        );

        return $productCategory
            instanceof ProductCategory
            && $this->user()?->can(
                'update',
                $productCategory,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

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
            'parent_id' => $this->filled('parent_id')
                ? $this->input('parent_id')
                : null,

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