<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BrandService
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): Brand {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $normalized,
            ): Brand {
                /*
                 * Serializing writes through the tenant record prevents two
                 * concurrent requests from passing the reserved identifier
                 * checks for the same tenant.
                 */
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                );

                return Brand::query()->create(
                    $normalized,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        Brand $brand,
        array $data,
        User $actor,
    ): Brand {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureBrandBelongsToTenant(
            brand: $brand,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $brand,
                $normalized,
            ): Brand {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedBrand = Brand::query()
                    ->whereKey($brand->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptBrandId:
                        (int) $lockedBrand->getKey(),
                );

                $this->ensureSlugIsAvailable(
                    slug: $normalized['slug'],
                    exceptBrandId:
                        (int) $lockedBrand->getKey(),
                );

                $lockedBrand->fill($normalized);
                $lockedBrand->save();

                return $lockedBrand->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Brand $brand,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureBrandBelongsToTenant(
            brand: $brand,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use ($brand): void {
                $lockedBrand = Brand::query()
                    ->whereKey($brand->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $isUsedByProduct = Product::query()
                    ->withTrashed()
                    ->where(
                        'brand_id',
                        $lockedBrand->getKey(),
                    )
                    ->exists();

                if ($isUsedByProduct) {
                    throw ValidationException::withMessages([
                        'brand' => [
                            'A brand assigned to a product cannot be deleted.',
                        ],
                    ]);
                }

                $lockedBrand->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     slug: string,
     *     website_url: string|null,
     *     description: string|null,
     *     sort_order: int,
     *     status: string
     * }
     */
    private function normalize(array $data): array
    {
        $name = trim(
            (string) ($data['name'] ?? ''),
        );

        $code = mb_strtoupper(
            trim(
                (string) ($data['code'] ?? ''),
            ),
        );

        $providedSlug = trim(
            (string) ($data['slug'] ?? ''),
        );

        $slug = Str::slug(
            $providedSlug !== ''
                ? $providedSlug
                : $name,
        );

        $websiteUrl = $this->nullableTrimmedString(
            $data['website_url'] ?? null,
        );

        $description = $this->nullableTrimmedString(
            $data['description'] ?? null,
        );

        $sortOrder = (int) (
            $data['sort_order'] ?? 0
        );

        $status = mb_strtolower(
            trim(
                (string) (
                    $data['status'] ?? 'active'
                ),
            ),
        );

        $errors = [];

        if (
            $name === ''
            || mb_strlen($name) > 120
        ) {
            $errors['name'] = [
                'The brand name is required and may not exceed 120 characters.',
            ];
        }

        if (
            $code === ''
            || mb_strlen($code) > 40
            || preg_match(
                '/^[A-Z0-9_-]+$/',
                $code,
            ) !== 1
        ) {
            $errors['code'] = [
                'The brand code may contain uppercase letters, numbers, underscores, and hyphens only.',
            ];
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 160
            || preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug,
            ) !== 1
        ) {
            $errors['slug'] = [
                'The brand slug must contain lowercase letters, numbers, and hyphens only.',
            ];
        }

        if (
            $websiteUrl !== null
            && !$this->isValidWebsiteUrl(
                $websiteUrl,
            )
        ) {
            $errors['website_url'] = [
                'The brand website must be a valid HTTP or HTTPS URL and may not exceed 2,048 characters.',
            ];
        }

        if (
            $description !== null
            && mb_strlen($description) > 2000
        ) {
            $errors['description'] = [
                'The brand description may not exceed 2,000 characters.',
            ];
        }

        if (
            $sortOrder < 0
            || $sortOrder > 4294967295
        ) {
            $errors['sort_order'] = [
                'The brand sort order must be zero or greater.',
            ];
        }

        if (
            !in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            $errors['status'] = [
                'The selected brand status is invalid.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors,
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'slug' => $slug,
            'website_url' => $websiteUrl,
            'description' => $description,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptBrandId = null,
    ): void {
        $exists = Brand::query()
            ->withTrashed()
            ->where('code', $code)
            ->when(
                $exceptBrandId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptBrandId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [
                'The brand code has already been used.',
            ],
        ]);
    }

    private function ensureSlugIsAvailable(
        string $slug,
        ?int $exceptBrandId = null,
    ): void {
        $exists = Brand::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when(
                $exceptBrandId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptBrandId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => [
                'The brand slug has already been used.',
            ],
        ]);
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected user does not belong to the active tenant.',
        );
    }

    private function ensureBrandBelongsToTenant(
        Brand $brand,
        int $tenantId,
    ): void {
        if ((int) $brand->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected brand belongs to another tenant.',
        );
    }

    private function isValidWebsiteUrl(
        string $websiteUrl,
    ): bool {
        if (mb_strlen($websiteUrl) > 2048) {
            return false;
        }

        if (
            filter_var(
                $websiteUrl,
                FILTER_VALIDATE_URL,
            ) === false
        ) {
            return false;
        }

        $scheme = parse_url(
            $websiteUrl,
            PHP_URL_SCHEME,
        );

        return is_string($scheme)
            && in_array(
                mb_strtolower($scheme),
                [
                    'http',
                    'https',
                ],
                true,
            );
    }

    private function nullableTrimmedString(
        mixed $value,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}