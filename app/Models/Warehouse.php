<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Warehouse extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
        'status',
        'is_default',
        'address',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductWarehouseSetting, $this>
     */
    public function productSettings(): HasMany
    {
        return $this->hasMany(
            ProductWarehouseSetting::class,
        );
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(
            PurchaseOrder::class,
        );
    }
}