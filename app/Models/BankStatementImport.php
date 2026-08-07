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

final class BankStatementImport extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'bank_account_id',
        'statement_reference',
        'source_filename',
        'source_sha256',
        'period_start',
        'period_end',
        'currency_code',
        'opening_balance',
        'closing_balance',
        'line_count',
        'status',
        'imported_by_user_id',
        'imported_at',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id')->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('line_number');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class)->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'bank_account_id' => 'integer',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'opening_balance' => 'decimal:6',
            'closing_balance' => 'decimal:6',
            'line_count' => 'integer',
            'imported_by_user_id' => 'integer',
            'imported_at' => 'immutable_datetime',
        ];
    }
}
