<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerRefundAccountingGateway;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerOpenItem;
use App\Models\CustomerRefund;
use App\Models\CustomerRefundAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\CustomerReceiptMethodRegistry;
use App\Support\Accounting\CustomerSettlementStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerRefundService
{
    private const SCALE = 6;
    private const RATE_SCALE = 8;
    private const MAXIMUM_AMOUNT = '99999999999999.999999';
    private const MAXIMUM_RATE = '999999999999.99999999';
    public function __construct(private readonly TenantContext $tenantContext, private readonly BranchAccessService $branchAccessService, private readonly DocumentNumberService $documentNumberService, private readonly AccountingPeriodService $accountingPeriodService, private readonly CustomerRefundAccountingGateway $accountingGateway, private readonly CustomerSettlementStatusRegistry $statusRegistry, private readonly CustomerReceiptMethodRegistry $methodRegistry,)
    {
    }
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): CustomerRefund
    {
        $tenant = $this->tenantContext->tenant();
        $normalized = $this->normalize($data, $tenant);
        return DB::transaction(function() use($normalized, $actor): CustomerRefund
        {
            [$branch, $customer, $account] = $this->context($normalized, $actor, true);
            $lines = $this->validateLines($normalized, $branch, $customer);
            $total = $this->sumLines($lines);
            $refund = CustomerRefund::query()->create(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'refund_account_id' => $account->getKey(), 'refund_date' => $normalized['refund_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'exchange_rate' => $normalized['exchange_rate'], 'refund_method' => $normalized['refund_method'], 'refund_reference' => $normalized['refund_reference'], 'cheque_number' => $normalized['cheque_number'], 'cheque_date' => $normalized['cheque_date'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'refund_account_code' => $account->code, 'refund_account_name' => $account->name, 'status' => 'draft', 'total_amount' => $total, 'base_cash_amount' => '0.000000', 'base_credit_amount' => '0.000000', 'exchange_difference_amount' => '0.000000', 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => 1, 'created_by_user_id' => $actor->getKey(),]);
            $this->replaceLines($refund, $lines);
            return $this->load($refund);
        }
        , attempts: 5);
    }
    /** @param array<string, mixed> $data */
    public function update(CustomerRefund $refund, array $data, User $actor): CustomerRefund
    {
        $tenant = $this->tenantContext->tenant();
        $normalized = $this->normalize($data, $tenant);
        return DB::transaction(function() use($refund, $normalized, $actor): CustomerRefund
        {
            $locked = CustomerRefund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            $this->requireStatus($locked, 'draft', 'Only a draft Customer Refund can be edited.');
            [$branch, $customer, $account] = $this->context($normalized, $actor, true);
            $lines = $this->validateLines($normalized, $branch, $customer);
            $total = $this->sumLines($lines);
            $locked->fill(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'refund_account_id' => $account->getKey(), 'refund_date' => $normalized['refund_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'exchange_rate' => $normalized['exchange_rate'], 'refund_method' => $normalized['refund_method'], 'refund_reference' => $normalized['refund_reference'], 'cheque_number' => $normalized['cheque_number'], 'cheque_date' => $normalized['cheque_date'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'refund_account_code' => $account->code, 'refund_account_name' => $account->name, 'total_amount' => $total, 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => (int) $locked->revision + 1,]);
            $locked->save();
            $this->replaceLines($locked, $lines);
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function delete(CustomerRefund $refund, User $actor): void
    {
        DB::transaction(function() use($refund, $actor): void
        {
            $locked = $this->lockAndAuthorize($refund, $actor, false);
            $this->requireStatus($locked, 'draft', 'Only a draft Customer Refund can be deleted.');
            $locked->delete();
        }
        , attempts: 5);
    }

    public function submit(CustomerRefund $refund, User $actor): CustomerRefund
    {
        return DB::transaction(function() use($refund, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, true);
            $this->transition($locked, 'submitted');
            $this->revalidate($locked, $actor);
            if (!$locked->hasRefundNumber()) {
                $allocation = $this->documentNumberService->allocate(documentType: 'customer_refund', branchId: (int) $locked->branch_id, idempotencyKey: sprintf('customer-refund:%d:%d', (int) $locked->tenant_id, (int) $locked->getKey()), allocatableType: CustomerRefund::class, allocatableId: (int) $locked->getKey(), allocatedAt: $locked->refund_date,);
                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->refund_number = $allocation->number;
            }
            $locked->status = 'submitted';
            $locked->submitted_by_user_id = $actor->getKey();
            $locked->submitted_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function returnToDraft(CustomerRefund $refund, User $actor): CustomerRefund
    {
        return DB::transaction(function() use($refund, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, true);
            $this->transition($locked, 'draft');
            $locked->status = 'draft';
            $locked->submitted_by_user_id = null;
            $locked->submitted_at = null;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function approve(CustomerRefund $refund, User $actor): CustomerRefund
    {
        return DB::transaction(function() use($refund, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, true);
            $this->transition($locked, 'approved');
            $this->revalidate($locked, $actor);
            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function cancel(CustomerRefund $refund, string $reason, User $actor): CustomerRefund
    {
        return DB::transaction(function() use($refund, $reason, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, false);
            $this->transition($locked, 'cancelled');
            $locked->status = 'cancelled';
            $locked->cancellation_reason = $this->requiredText($reason, 500, 'cancellation_reason');
            $locked->cancelled_by_user_id = $actor->getKey();
            $locked->cancelled_at = now();
            $locked->allocations()->update(['status' => 'cancelled']);
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function post(CustomerRefund $refund, User $actor): CustomerRefund
    {
        return DB::transaction(function() use($refund, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, true);
            $this->transition($locked, 'posted');
            $this->preparePostingSnapshots($locked);
            $period = $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $reference = $this->accountingGateway->post($locked->load(['allocations.creditOpenItem', 'refundAccount']), $period, $actor);
            $locked->status = 'posted';
            $locked->posted_by_user_id = $actor->getKey();
            $locked->posted_at = now();
            $locked->accounting_posting_reference = $reference;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function reverse(CustomerRefund $refund, string $postingDate, string $reason, User $actor): CustomerRefund
    {
        $tenant = $this->tenantContext->tenant();
        $date = CarbonImmutable::parse($this->date($postingDate, 'reversal_posting_date', $tenant->timezone), $tenant->timezone);
        $reason = $this->requiredText($reason, 500, 'reversal_reason');
        return DB::transaction(function() use($refund, $date, $reason, $actor): CustomerRefund
        {
            $locked = $this->lockAndAuthorize($refund, $actor, true);
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

    private function preparePostingSnapshots(CustomerRefund $refund): void
    {
        $lines = CustomerRefundAllocation::query()->where('customer_refund_id', $refund->getKey())->orderBy('line_number')->lockForUpdate()->get();
        $creditIds = $lines->pluck('credit_open_item_id')->map(static fn($id): int => (int) $id)->all();
        $credits = CustomerOpenItem::query()->whereIn('id', $creditIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $used = [];
        $creditBaseTotal = BigDecimal::zero();
        $cashBaseTotal = BigDecimal::of((string) $refund->total_amount)->multipliedBy((string) $refund->exchange_rate)->toScale(self::SCALE, RoundingMode::HALF_UP);
        if ($cashBaseTotal->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([
                'exchange_rate' => ['The refund base-currency amount exceeds the supported maximum.'],
            ]);
        }
        $remainingAmount = BigDecimal::of((string) $refund->total_amount);
        $remainingCashBase = $cashBaseTotal;
        foreach ($lines as $line) {
            $credit = $credits->get((int) $line->credit_open_item_id);
            if (!$credit instanceof CustomerOpenItem || !$credit->isCredit() || $credit->isReversed()) {
                throw ValidationException::withMessages(['allocations' => ['A selected customer credit is no longer available.']]);
            }
            $amount = BigDecimal::of((string) $line->amount);
            $alreadyUsed = $used[(int) $credit->getKey()] ?? BigDecimal::zero();
            $remainingCredit = BigDecimal::of((string) $credit->outstanding_amount)->minus($alreadyUsed);
            if ($remainingCredit->isLessThan($amount)) {
                throw ValidationException::withMessages(['allocations' => ['The refund exceeds an available customer credit balance.']]);
            }
            $used[(int) $credit->getKey()] = $alreadyUsed->plus($amount);
            $baseOutstanding = BigDecimal::of((string) $credit->base_outstanding_amount);
            $creditOutstanding = BigDecimal::of((string) $credit->outstanding_amount);
            $creditBase = $amount->compareTo($remainingCredit) === 0 && $alreadyUsed->isZero() ? $baseOutstanding: $amount->multipliedBy((string) $credit->exchange_rate)->toScale(self::SCALE, RoundingMode::HALF_UP);
            if ($creditBase->isGreaterThan($baseOutstanding)) {
                $creditBase = $baseOutstanding;
            }
            $cashBase = $amount->compareTo($remainingAmount) === 0 ? $remainingCashBase: $amount->multipliedBy((string) $refund->exchange_rate)->toScale(self::SCALE, RoundingMode::HALF_UP);
            $line->credit_document_number = $credit->document_number;
            $line->credit_item_type = $credit->item_type;
            $line->credit_source_type = $credit->source_type;
            $line->credit_source_id = $credit->source_id;
            $line->credit_exchange_rate = $credit->exchange_rate;
            $line->credit_base_amount = $this->decimal($creditBase);
            $line->cash_base_amount = $this->decimal($cashBase);
            $line->exchange_difference_amount = $this->decimal($creditBase->minus($cashBase));
            $line->save();
            $creditBaseTotal = $creditBaseTotal->plus($creditBase);
            $remainingAmount = $remainingAmount->minus($amount);
            $remainingCashBase = $remainingCashBase->minus($cashBase);
        }
        if (!$remainingAmount->isZero()) {
            throw new LogicException('The Customer Refund allocation total no longer equals the refund total.');
        }
        if ($creditBaseTotal->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([
                'allocations' => ['The refund credit carrying value exceeds the supported maximum.'],
            ]);
        }
        $refund->base_cash_amount = $this->decimal($cashBaseTotal);
        $refund->base_credit_amount = $this->decimal($creditBaseTotal);
        $refund->exchange_difference_amount = $this->decimal($creditBaseTotal->minus($cashBaseTotal));
        $refund->save();
    }
    /** @return array<string, mixed> */
    private function normalize(array $data, Tenant $tenant): array
    {
        $lines = $data['allocations'] ?? null;
        if (!is_array($lines) || $lines === []) {
            throw ValidationException::withMessages(['allocations' => ['At least one customer credit must be refunded.']]);
        }
        $method = trim((string)($data['refund_method'] ?? ''));
        if (!$this->methodRegistry->exists($method)) {
            throw ValidationException::withMessages(['refund_method' => ['The selected refund method is invalid.']]);
        }
        $chequeNumber = $this->nullableText($data['cheque_number'] ?? null, 100);
        $chequeDate = isset ($data['cheque_date']) && $data['cheque_date'] !== '' ? $this->date($data['cheque_date'], 'cheque_date', $tenant->timezone): null;
        if ($this->methodRegistry->requiresChequeDetails($method) && ($chequeNumber === null || $chequeDate === null)) {
            throw ValidationException::withMessages(['cheque_number' => ['Cheque number and cheque date are required for cheque refunds.']]);
        }
        return['branch_id' => $this->id($data['branch_id'] ?? null, 'branch_id'), 'customer_id' => $this->id($data['customer_id'] ?? null, 'customer_id'), 'refund_account_id' => $this->id($data['refund_account_id'] ?? null, 'refund_account_id'), 'refund_date' => $this->date($data['refund_date'] ?? null, 'refund_date', $tenant->timezone), 'posting_date' => $this->date($data['posting_date'] ?? null, 'posting_date', $tenant->timezone), 'currency_code' => $this->currency($data['currency_code'] ?? null), 'exchange_rate' => $this->positiveRate($data['exchange_rate'] ?? null), 'refund_method' => $method, 'refund_reference' => $this->nullableText($data['refund_reference'] ?? null, 160), 'cheque_number' => $chequeNumber, 'cheque_date' => $chequeDate, 'reason' => $this->requiredText($data['reason'] ?? null, 500, 'reason'), 'notes' => $this->nullableText($data['notes'] ?? null, 4000), 'allocations' => array_values($lines),];
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
        $account = Account::query()->whereKey($data['refund_account_id'])->lockForUpdate()->firstOrFail();
        $requiredControl = $this->methodRegistry->accountControlType($data['refund_method']);
        if (!$account->isActive() || !$account->isPostingAccount() || $account->control_type !== $requiredControl) {
            throw ValidationException::withMessages(['refund_account_id' => ["The selected refund method requires an active {$requiredControl} account."]]);
        }
        return[$branch, $customer, $account];
    }
    /** @return list<array<string, mixed>> */
    private function validateLines(array $data, Branch $branch, Customer $customer): array
    {
        $result = [];
        $seen = [];
        foreach ($data['allocations'] as $index => $input) {
            if (!is_array($input)) {
                throw ValidationException::withMessages(["allocations.{$index}" => ['Each refund allocation must be an object.']]);
            }
            $creditId = $this->id($input['credit_open_item_id'] ?? null, "allocations.{$index}.credit_open_item_id");
            if (isset ($seen[$creditId])) {
                throw ValidationException::withMessages(["allocations.{$index}.credit_open_item_id" => ['A customer credit can appear only once in a refund.']]);
            }
            $seen[$creditId] = true;
            $amount = $this->positiveMoney($input['amount'] ?? null, "allocations.{$index}.amount");
            $credit = CustomerOpenItem::query()->whereKey($creditId)->lockForUpdate()->first();
            if (!$credit instanceof CustomerOpenItem || !$credit->isCredit() || $credit->isReversed()) {
                throw ValidationException::withMessages(["allocations.{$index}.credit_open_item_id" => ['The selected customer credit is unavailable.']]);
            }
            if ((int) $credit->branch_id !== (int) $branch->getKey() || (int) $credit->customer_id !== (int) $customer->getKey() || strtoupper((string) $credit->currency_code) !== $data['currency_code']) {
                throw ValidationException::withMessages(["allocations.{$index}" => ['All refund credits must use the selected branch, customer, and currency.']]);
            }
            if (BigDecimal::of((string) $credit->outstanding_amount)->isLessThan($amount)) {
                throw ValidationException::withMessages(["allocations.{$index}.amount" => ['The refund amount exceeds the available customer credit.']]);
            }
            $result[] = ['credit_open_item_id' => $creditId, 'credit_document_number' => $credit->document_number, 'credit_item_type' => $credit->item_type, 'credit_source_type' => $credit->source_type, 'credit_source_id' => $credit->source_id, 'amount' => $this->decimal($amount), 'credit_exchange_rate' => (string) $credit->exchange_rate, 'status' => 'draft',];
        }
        return $result;
    }

    private function revalidate(CustomerRefund $refund, User $actor): void
    {
        $data = ['branch_id' => (int) $refund->branch_id, 'customer_id' => (int) $refund->customer_id, 'refund_account_id' => (int) $refund->refund_account_id, 'refund_method' => $refund->refund_method, 'currency_code' => $refund->currency_code, 'allocations' => $refund->allocations()->orderBy('line_number')->get()->map(static fn(CustomerRefundAllocation $line): array => ['credit_open_item_id' => $line->credit_open_item_id, 'amount' => (string) $line->amount])->all(),];
        [$branch, $customer] = $this->context($data, $actor, true);
        $this->validateLines($data, $branch, $customer);
    }
    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(CustomerRefund $refund, array $lines): void
    {
        $refund->allocations()->delete();
        foreach ($lines as $index => $line) {
            $refund->allocations()->create([... $line, 'line_number' => $index + 1]);
        }
    }
    /** @param list<array<string, mixed>> $lines */
    private function sumLines(array $lines): string
    {
        $total = BigDecimal::zero();
        foreach ($lines as $line) {
            $total = $total->plus((string) $line['amount']);
        }
        if ($total->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([
                'allocations' => ['The total refund amount exceeds the supported maximum.'],
            ]);
        }
        return $this->decimal($total);
    }

    private function lockAndAuthorize(CustomerRefund $refund, User $actor, bool $active): CustomerRefund
    {
        $locked = CustomerRefund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
        $data = ['branch_id' => (int) $locked->branch_id, 'customer_id' => (int) $locked->customer_id, 'refund_account_id' => (int) $locked->refund_account_id, 'refund_method' => $locked->refund_method];
        $this->context($data, $actor, $active);
        return $locked;
    }

    private function transition(CustomerRefund $refund, string $next): void
    {
        if (!$this->statusRegistry->canTransition($refund->status, $next)) {
            throw ValidationException::withMessages(['status' => ["Customer Refund cannot move from {$refund->status} to {$next}."]]);
        }
    }

    private function requireStatus(CustomerRefund $refund, string $status, string $message): void
    {
        if ($refund->status !== $status) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    private function load(CustomerRefund $refund): CustomerRefund
    {
        return $refund->load(['branch:id,name,code,status', 'customer:id,name,code,status', 'refundAccount:id,code,name,status,control_type', 'allocations.creditOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
    }

    private function id(mixed $value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw ValidationException::withMessages([$field => ['The selected value is invalid.']]);
        }
        return $id;
    }

    private function positiveMoney(mixed $value, string $field): BigDecimal
    {
        try {
            $v = BigDecimal::of((string) $value)->toScale(self::SCALE, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['The amount is invalid or has more than 6 decimal places.']]);
        }
        if (!$v->isPositive() || $v->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([$field => ['The amount must be greater than zero and within the supported maximum.']]);
        }
        return $v;
    }

    private function positiveRate(mixed $value): string
    {
        try {
            $v = BigDecimal::of((string) $value)->toScale(self::RATE_SCALE, RoundingMode::UNNECESSARY);
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
            throw ValidationException::withMessages(['text' => ["The value may not exceed {$max} characters."]]);
        }
        return $v;
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HALF_UP)->__toString();
    }
}