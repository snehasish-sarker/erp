<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductCategory extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'slug',
        'description',
        'sort_order',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class,
        );
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            ProductCategory::class,
            'parent_id',
        );
    }

    /**
     * @return HasMany<ProductCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            ProductCategory::class,
            'parent_id',
        )
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'product_category_id',
        );
    }
}