<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductBranchSetting extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'branch_id',
        'status',
        'is_purchasable',
        'is_sellable',
        'selling_price',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveSellingPrice(
        Product $product,
    ): string {
        return $this->selling_price !== null
            ? (string) $this->selling_price
            : (string) $product->selling_price;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'product_id' => 'integer',
            'branch_id' => 'integer',
            'is_purchasable' => 'boolean',
            'is_sellable' => 'boolean',
            'selling_price' => 'decimal:6',
        ];
    }
}