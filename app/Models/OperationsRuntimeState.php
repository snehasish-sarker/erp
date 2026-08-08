<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class OperationsRuntimeState extends Model
{
    protected $primaryKey = 'state_key';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'state_key',
        'value',
        'touched_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'touched_at' => 'immutable_datetime',
        ];
    }
}
