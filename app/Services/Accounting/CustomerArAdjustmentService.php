<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerArAdjustmentAccountingGateway;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerArAdjustment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\CustomerArAdjustmentDirectionRegistry;
use App\Support\Accounting\CustomerSettlementStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerArAdjustmentService
{
    private const MONEY_SCALE = 6;
    private const RATE_SCALE = 8;
    private const MAXIMUM_AMOUNT = '99999999999999.999999';
    private const MAXIMUM_RATE = '999999999999.99999999';
    public function __construct(private readonly TenantContext $tenantContext, private readonly BranchAccessService $branchAccessService, private readonly DocumentNumberService $documentNumberService, private readonly AccountingPeriodService $accountingPeriodService, private readonly CustomerArAdjustmentAccountingGateway $accountingGateway, private readonly CustomerSettlementStatusRegistry $statusRegistry, private readonly CustomerArAdjustmentDirectionRegistry $directionRegistry,)
    {
    }
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): CustomerArAdjustment
    {
        $normalized = $this->normalize($data, $this->tenantContext->tenant());
        return DB::transaction(function() use($normalized, $actor): CustomerArAdjustment
        {
            [$branch, $customer, $account] = $this->context($normalized, $actor, true);
            $baseAmount = BigDecimal::of($normalized['amount'])->multipliedBy($normalized['exchange_rate'])->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
            $this->ensureBaseAmountWithinRange($baseAmount);
            return $this->load(CustomerArAdjustment::query()->create(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'offset_account_id' => $account->getKey(), 'adjustment_date' => $normalized['adjustment_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'exchange_rate' => $normalized['exchange_rate'], 'direction' => $normalized['direction'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'offset_account_code' => $account->code, 'offset_account_name' => $account->name, 'status' => 'draft', 'amount' => $normalized['amount'], 'base_amount' => $this->decimal($baseAmount), 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => 1, 'created_by_user_id' => $actor->getKey(),]));
        }
        , attempts: 5);
    }
    /** @param array<string, mixed> $data */
    public function update(CustomerArAdjustment $adjustment, array $data, User $actor): CustomerArAdjustment
    {
        $normalized = $this->normalize($data, $this->tenantContext->tenant());
        return DB::transaction(function() use($adjustment, $normalized, $actor): CustomerArAdjustment
        {
            $locked = CustomerArAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
            $this->requireStatus($locked, 'draft', 'Only a draft AR Adjustment can be edited.');
            [$branch, $customer, $account] = $this->context($normalized, $actor, true);
            $baseAmount = BigDecimal::of($normalized['amount'])->multipliedBy($normalized['exchange_rate'])->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
            $this->ensureBaseAmountWithinRange($baseAmount);
            $locked->fill(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'offset_account_id' => $account->getKey(), 'adjustment_date' => $normalized['adjustment_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'exchange_rate' => $normalized['exchange_rate'], 'direction' => $normalized['direction'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'offset_account_code' => $account->code, 'offset_account_name' => $account->name, 'amount' => $normalized['amount'], 'base_amount' => $this->decimal($baseAmount), 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => (int) $locked->revision + 1,]);
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function delete(CustomerArAdjustment $adjustment, User $actor): void
    {
        DB::transaction(function() use($adjustment, $actor): void
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, false);
            $this->requireStatus($locked, 'draft', 'Only a draft AR Adjustment can be deleted.');
            $locked->delete();
        }
        , attempts: 5);
    }

    public function submit(CustomerArAdjustment $adjustment, User $actor): CustomerArAdjustment
    {
        return DB::transaction(function() use($adjustment, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, true);
            $this->transition($locked, 'submitted');
            $this->validateStoredContext($locked, $actor);
            if (!$locked->hasAdjustmentNumber()) {
                $allocation = $this->documentNumberService->allocate(documentType: 'customer_ar_adjustment', branchId: (int) $locked->branch_id, idempotencyKey: sprintf('customer-ar-adjustment:%d:%d', (int) $locked->tenant_id, (int) $locked->getKey()), allocatableType: CustomerArAdjustment::class, allocatableId: (int) $locked->getKey(), allocatedAt: $locked->adjustment_date,);
                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->adjustment_number = $allocation->number;
            }
            $locked->status = 'submitted';
            $locked->submitted_by_user_id = $actor->getKey();
            $locked->submitted_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function returnToDraft(CustomerArAdjustment $adjustment, User $actor): CustomerArAdjustment
    {
        return DB::transaction(function() use($adjustment, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, true);
            $this->transition($locked, 'draft');
            $locked->status = 'draft';
            $locked->submitted_by_user_id = null;
            $locked->submitted_at = null;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function approve(CustomerArAdjustment $adjustment, User $actor): CustomerArAdjustment
    {
        return DB::transaction(function() use($adjustment, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, true);
            $this->transition($locked, 'approved');
            $this->validateStoredContext($locked, $actor);
            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function cancel(CustomerArAdjustment $adjustment, string $reason, User $actor): CustomerArAdjustment
    {
        return DB::transaction(function() use($adjustment, $reason, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, false);
            $this->transition($locked, 'cancelled');
            $locked->status = 'cancelled';
            $locked->cancellation_reason = $this->requiredText($reason, 500, 'cancellation_reason');
            $locked->cancelled_by_user_id = $actor->getKey();
            $locked->cancelled_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function post(CustomerArAdjustment $adjustment, User $actor): CustomerArAdjustment
    {
        return DB::transaction(function() use($adjustment, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, true);
            $this->transition($locked, 'posted');
            $this->validateStoredContext($locked, $actor);
            $period = $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $reference = $this->accountingGateway->post($locked->load('offsetAccount'), $period, $actor);
            $locked->status = 'posted';
            $locked->posted_by_user_id = $actor->getKey();
            $locked->posted_at = now();
            $locked->accounting_posting_reference = $reference;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function reverse(CustomerArAdjustment $adjustment, string $postingDate, string $reason, User $actor): CustomerArAdjustment
    {
        $tenant = $this->tenantContext->tenant();
        $date = CarbonImmutable::parse($this->date($postingDate, 'reversal_posting_date', $tenant->timezone), $tenant->timezone);
        $reason = $this->requiredText($reason, 500, 'reversal_reason');
        return DB::transaction(function() use($adjustment, $date, $reason, $actor): CustomerArAdjustment
        {
            $locked = $this->lockAndAuthorize($adjustment, $actor, true);
            $this->transition($locked, 'reversed');
            $period = $this->accountingPeriodService->lockOpenPeriod($date);
            $reference = $this->accountingGateway->reverse($locked, $period, $date, $reason, $actor);
            $locked->status = 'reversed';
            $locked->reversal_posting_date = $date->toDateString();
            $locked->reversal_reason = $reason;
            $locked->reversed_by_user_id = $actor->getKey();
            $locked->reversed_at = now();
            $locked->accounting_reversal_reference = $reference;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }
    /** @return array<string, mixed> */
    private function normalize(array $data, Tenant $tenant): array
    {
        $direction = trim((string)($data['direction'] ?? ''));
        if (!$this->directionRegistry->exists($direction)) {
            throw ValidationException::withMessages(['direction' => ['The adjustment direction is invalid.']]);
        }
        return['branch_id' => $this->id($data['branch_id'] ?? null, 'branch_id'), 'customer_id' => $this->id($data['customer_id'] ?? null, 'customer_id'), 'offset_account_id' => $this->id($data['offset_account_id'] ?? null, 'offset_account_id'), 'adjustment_date' => $this->date($data['adjustment_date'] ?? null, 'adjustment_date', $tenant->timezone), 'posting_date' => $this->date($data['posting_date'] ?? null, 'posting_date', $tenant->timezone), 'currency_code' => $this->currency($data['currency_code'] ?? null), 'exchange_rate' => $this->positiveRate($data['exchange_rate'] ?? null), 'direction' => $direction, 'amount' => $this->positiveMoney($data['amount'] ?? null), 'reason' => $this->requiredText($data['reason'] ?? null, 500, 'reason'), 'notes' => $this->nullableText($data['notes'] ?? null, 4000),];
    }
    /** @return array{0: Branch, 1: Customer, 2: Account} */
    private function context(array $data, User $actor, bool $active): array
    {
        $branch = Branch::query()->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();
        $this->branchAccessService->authorizeBranch($actor, $branch, $active);
        $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
        if ((int) $customer->tenant_id !== (int) $actor->tenant_id || ($active && !$customer->isActive())) {
            throw ValidationException::withMessages(['customer_id' => ['The selected customer is inactive or unavailable.']]);
        }
        $account = Account::query()->whereKey($data['offset_account_id'])->lockForUpdate()->firstOrFail();
        if (!$account->isActive() || !$account->allowsManualPosting() || in_array($account->system_key, ['accounts_receivable_control', 'customer_advances'], true)) {
            throw ValidationException::withMessages(['offset_account_id' => ['Select an active manual-posting account other than an AR control account.']]);
        }
        return[$branch, $customer, $account];
    }

    private function validateStoredContext(CustomerArAdjustment $adjustment, User $actor): void
    {
        $this->context(['branch_id' => (int) $adjustment->branch_id, 'customer_id' => (int) $adjustment->customer_id, 'offset_account_id' => (int) $adjustment->offset_account_id,], $actor, true);
    }

    private function lockAndAuthorize(CustomerArAdjustment $adjustment, User $actor, bool $active): CustomerArAdjustment
    {
        $locked = CustomerArAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
        $branch = Branch::query()->whereKey($locked->branch_id)->firstOrFail();
        $this->branchAccessService->authorizeBranch($actor, $branch, $active);
        return $locked;
    }

    private function transition(CustomerArAdjustment $adjustment, string $next): void
    {
        if (!$this->statusRegistry->canTransition($adjustment->status, $next)) {
            throw ValidationException::withMessages(['status' => ["AR Adjustment cannot move from {$adjustment->status} to {$next}."]]);
        }
    }

    private function requireStatus(CustomerArAdjustment $adjustment, string $status, string $message): void
    {
        if ($adjustment->status !== $status) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    private function load(CustomerArAdjustment $adjustment): CustomerArAdjustment
    {
        return $adjustment->load(['branch:id,name,code,status', 'customer:id,name,code,status', 'offsetAccount:id,code,name,status', 'customerOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
    }

    private function id(mixed $value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw ValidationException::withMessages([$field => ['The selected value is invalid.']]);
        }
        return $id;
    }

    private function positiveMoney(mixed $value): string
    {
        try {
            $v = BigDecimal::of((string) $value)->toScale(self::MONEY_SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => ['The amount is invalid or has more than 6 decimal places.']]);
        }
        if (!$v->isPositive() || $v->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages(['amount' => ['The amount must be greater than zero and within the supported maximum.']]);
        }
        return $v->__toString();
    }

    private function positiveRate(mixed $value): string
    {
        try {
            $v = BigDecimal::of((string) $value)->toScale(self::RATE_SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['exchange_rate' => ['The exchange rate is invalid or has more than 8 decimal places.']]);
        }
        if (!$v->isPositive() || $v->isGreaterThan(BigDecimal::of(self::MAXIMUM_RATE))) {
            throw ValidationException::withMessages(['exchange_rate' => ['The exchange rate must be greater than zero and within the supported maximum.']]);
        }
        return $v->__toString();
    }

    private function date(mixed $value, string $field, string $timezone): string
    {
        if (!is_string($value)) {
            throw ValidationException::withMessages([$field => ['The date must use YYYY-MM-DD format.']]);
        }
        $text = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $text, $timezone);
        if (!$date instanceof CarbonImmutable || $date->format('Y-m-d') !== $text) {
            throw ValidationException::withMessages([$field => ['The date must use YYYY-MM-DD format.']]);
        }
        return $text;
    }

    private function currency(mixed $value): string
    {
        $v = strtoupper(trim((string) $value));
        if (preg_match('/^[A-Z]{3}$/', $v) !== 1) {
            throw ValidationException::withMessages(['currency_code' => ['The currency code must contain three letters.']]);
        }
        return $v;
    }

    private function requiredText(mixed $value, int $max, string $field): string
    {
        $v = trim((string) $value);
        if ($v === '' || mb_strlen($v) > $max) {
            throw ValidationException::withMessages([$field => ["The {$field} field is required and may not exceed {$max} characters."]]);
        }
        return $v;
    }

    private function nullableText(mixed $value, int $max): ? string
    {
        $v = trim((string)($value ?? ''));
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > $max) {
            throw ValidationException::withMessages(['notes' => ["Notes may not exceed {$max} characters."]]);
        }
        return $v;
    }

    private function ensureBaseAmountWithinRange(BigDecimal $baseAmount): void
    {
        if ($baseAmount->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([
                'exchange_rate' => ['The adjustment base-currency amount exceeds the supported maximum.'],
            ]);
        }
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::MONEY_SCALE, RoundingMode::HalfUp)->__toString();
    }
}