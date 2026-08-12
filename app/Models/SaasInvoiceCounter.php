<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SaasInvoiceCounter extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'counter_key',
        'period_key',
        'next_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
