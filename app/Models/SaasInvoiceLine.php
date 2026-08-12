<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaasInvoiceLine extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'saas_invoice_id',
        'description',
        'quantity',
        'unit_amount_minor',
        'line_total_minor',
        'metadata',
    ];

    /** @return BelongsTo<SaasInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'saas_invoice_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'line_total_minor' => 'integer',
            'metadata' => 'array',
        ];
    }
}
