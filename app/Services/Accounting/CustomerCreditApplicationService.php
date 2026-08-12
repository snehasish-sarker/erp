<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerCreditApplicationAccountingGateway;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCreditApplication;
use App\Models\CustomerCreditApplicationLine;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\CustomerSettlementStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerCreditApplicationService
{
    private const SCALE = 6;
    private const MAXIMUM_AMOUNT = '99999999999999.999999';
    public function __construct(private readonly TenantContext $tenantContext, private readonly BranchAccessService $branchAccessService, private readonly DocumentNumberService $documentNumberService, private readonly AccountingPeriodService $accountingPeriodService, private readonly CustomerOpenItemAllocationService $allocationService, private readonly CustomerCreditApplicationAccountingGateway $accountingGateway, private readonly CustomerSettlementStatusRegistry $statusRegistry,)
    {
    }
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): CustomerCreditApplication
    {
        $tenant = $this->tenantContext->tenant();
        $normalized = $this->normalize($data, $tenant);
        return DB::transaction(function() use($normalized, $actor): CustomerCreditApplication
        {
            [$branch, $customer] = $this->context($normalized['branch_id'], $normalized['customer_id'], $actor, true);
            $lines = $this->validateLines($normalized, $branch, $customer);
            $totals = $this->lineTotals($lines);
            $application = CustomerCreditApplication::query()->create(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'application_date' => $normalized['application_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'status' => 'draft', 'total_amount' => $totals['total_amount'], 'receivable_base_amount' => '0.000000', 'credit_base_amount' => '0.000000', 'exchange_difference_amount' => '0.000000', 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => 1, 'created_by_user_id' => $actor->getKey(),]);
            $this->replaceLines($application, $lines);
            return $this->load($application);
        }
        , attempts: 5);
    }
    /** @param array<string, mixed> $data */
    public function update(CustomerCreditApplication $application, array $data, User $actor): CustomerCreditApplication
    {
        $tenant = $this->tenantContext->tenant();
        $normalized = $this->normalize($data, $tenant);
        return DB::transaction(function() use($application, $normalized, $actor): CustomerCreditApplication
        {
            $locked = CustomerCreditApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureTransition($locked, 'draft', 'Only a draft Customer Credit Application can be edited.');
            [$branch, $customer] = $this->context($normalized['branch_id'], $normalized['customer_id'], $actor, true);
            $lines = $this->validateLines($normalized, $branch, $customer);
            $totals = $this->lineTotals($lines);
            $locked->fill(['branch_id' => $branch->getKey(), 'customer_id' => $customer->getKey(), 'application_date' => $normalized['application_date'], 'posting_date' => $normalized['posting_date'], 'currency_code' => $normalized['currency_code'], 'customer_name' => $customer->name, 'customer_code' => $customer->code, 'total_amount' => $totals['total_amount'], 'reason' => $normalized['reason'], 'notes' => $normalized['notes'], 'revision' => (int) $locked->revision + 1,]);
            $locked->save();
            $this->replaceLines($locked, $lines);
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function delete(CustomerCreditApplication $application, User $actor): void
    {
        DB::transaction(function() use($application, $actor): void
        {
            $locked = CustomerCreditApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            $this->context((int) $locked->branch_id, (int) $locked->customer_id, $actor, false);
            $this->ensureTransition($locked, 'draft', 'Only a draft Customer Credit Application can be deleted.');
            $locked->delete();
        }
        , attempts: 5);
    }

    public function submit(CustomerCreditApplication $application, User $actor): CustomerCreditApplication
    {
        return DB::transaction(function() use($application, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, true);
            $this->transition($locked, 'submitted');
            $this->revalidateStoredLines($locked);
            if (!$locked->hasApplicationNumber()) {
                $allocation = $this->documentNumberService->allocate(documentType: 'customer_credit_application', branchId: (int) $locked->branch_id, idempotencyKey: sprintf('customer-credit-application:%d:%d', (int) $locked->tenant_id, (int) $locked->getKey()), allocatableType: CustomerCreditApplication::class, allocatableId: (int) $locked->getKey(), allocatedAt: $locked->application_date,);
                $locked->document_number_allocation_id = $allocation->getKey();
                $locked->application_number = $allocation->number;
            }
            $locked->status = 'submitted';
            $locked->submitted_by_user_id = $actor->getKey();
            $locked->submitted_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function returnToDraft(CustomerCreditApplication $application, User $actor): CustomerCreditApplication
    {
        return DB::transaction(function() use($application, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, true);
            $this->transition($locked, 'draft');
            $locked->status = 'draft';
            $locked->submitted_by_user_id = null;
            $locked->submitted_at = null;
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function approve(CustomerCreditApplication $application, User $actor): CustomerCreditApplication
    {
        return DB::transaction(function() use($application, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, true);
            $this->transition($locked, 'approved');
            $this->revalidateStoredLines($locked);
            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function cancel(CustomerCreditApplication $application, string $reason, User $actor): CustomerCreditApplication
    {
        return DB::transaction(function() use($application, $reason, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, false);
            $this->transition($locked, 'cancelled');
            $locked->status = 'cancelled';
            $locked->cancellation_reason = $this->requiredText($reason, 500, 'cancellation_reason');
            $locked->cancelled_by_user_id = $actor->getKey();
            $locked->cancelled_at = now();
            $locked->lines()->update(['status' => 'cancelled']);
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function post(CustomerCreditApplication $application, User $actor): CustomerCreditApplication
    {
        return DB::transaction(function() use($application, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, true);
            $this->transition($locked, 'posted');
            $period = $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $lines = CustomerCreditApplicationLine::query()->where('customer_credit_application_id', $locked->getKey())->orderBy('line_number')->lockForUpdate()->get();
            $this->lockApplicationOpenItems($lines);
            $receivableBase = BigDecimal::zero();
            $creditBase = BigDecimal::zero();
            foreach ($lines as $line) {
                $receivable = CustomerOpenItem::query()->whereKey($line->receivable_open_item_id)->lockForUpdate()->firstOrFail();
                $credit = CustomerOpenItem::query()->whereKey($line->credit_open_item_id)->lockForUpdate()->firstOrFail();
                $allocation = $this->allocationService->apply(receivableOpenItem: $receivable, creditOpenItem: $credit, accountingPeriod: $period, allocationType: 'manual', postingKey: sprintf('customer_credit_application:%d:line:%d', (int) $locked->getKey(), (int) $line->line_number), allocationDate: $locked->application_date, postingDate: $locked->posting_date, amount: (string) $line->amount, source: $locked, actor: $actor,);
                $line->customer_open_item_allocation_id = $allocation->getKey();
                $line->receivable_base_amount = $allocation->receivable_base_amount;
                $line->credit_base_amount = $allocation->credit_base_amount;
                $line->exchange_difference_amount = $allocation->exchange_difference_amount;
                $line->status = 'applied';
                $line->applied_at = now();
                $line->save();
                $receivableBase = $receivableBase->plus((string) $allocation->receivable_base_amount);
                $creditBase = $creditBase->plus((string) $allocation->credit_base_amount);
            }
            $locked->receivable_base_amount = $this->decimal($receivableBase);
            $locked->credit_base_amount = $this->decimal($creditBase);
            $locked->exchange_difference_amount = $this->decimal($creditBase->minus($receivableBase));
            $locked->save();
            $locked->accounting_posting_reference = $this->accountingGateway->post($locked->load('lines.creditOpenItem'), $period, $actor);
            $locked->status = 'posted';
            $locked->posted_by_user_id = $actor->getKey();
            $locked->posted_at = now();
            $locked->save();
            return $this->load($locked->refresh());
        }
        , attempts: 5);
    }

    public function reverse(CustomerCreditApplication $application, string $postingDate, string $reason, User $actor): CustomerCreditApplication
    {
        $tenant = $this->tenantContext->tenant();
        $date = CarbonImmutable::parse($this->date($postingDate, 'reversal_posting_date', $tenant->timezone), $tenant->timezone);
        $reason = $this->requiredText($reason, 500, 'reversal_reason');
        return DB::transaction(function() use($application, $date, $reason, $actor): CustomerCreditApplication
        {
            $locked = $this->lockAndAuthorize($application, $actor, true);
            $this->transition($locked, 'reversed');
            $period = $this->accountingPeriodService->lockOpenPeriod($date);
            $reference = $this->accountingGateway->reverse($locked, $period, $date, $reason, $actor);
            $lines = CustomerCreditApplicationLine::query()->where('customer_credit_application_id', $locked->getKey())->orderByDesc('line_number')->lockForUpdate()->get();
            $this->lockApplicationOpenItems($lines);
            foreach ($lines as $line) {
                $allocation = CustomerOpenItemAllocation::query()->whereKey($line->customer_open_item_allocation_id)->lockForUpdate()->first();
                if ($allocation instanceof CustomerOpenItemAllocation && $allocation->isApplied()) {
                    $this->allocationService->reverse($allocation, $period, $date, $reason, $actor);
                }
                $line->status = 'reversed';
                $line->reversed_at = now();
                $line->save();
            }
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
        $lines = $data['lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            throw ValidationException::withMessages(['lines' => ['At least one credit application line is required.']]);
        }
        return['branch_id' => $this->id($data['branch_id'] ?? null, 'branch_id'), 'customer_id' => $this->id($data['customer_id'] ?? null, 'customer_id'), 'application_date' => $this->date($data['application_date'] ?? null, 'application_date', $tenant->timezone), 'posting_date' => $this->date($data['posting_date'] ?? null, 'posting_date', $tenant->timezone), 'currency_code' => $this->currency($data['currency_code'] ?? null), 'reason' => $this->requiredText($data['reason'] ?? null, 500, 'reason'), 'notes' => $this->nullableText($data['notes'] ?? null, 4000), 'lines' => array_values($lines),];
    }
    /** @return list<array<string, mixed>> */
    private function validateLines(array $data, Branch $branch, Customer $customer): array
    {
        $lines = [];
        $receivableTotals = [];
        $creditTotals = [];
        $seenPairs = [];
        foreach ($data['lines'] as $index => $input) {
            if (!is_array($input)) {
                throw ValidationException::withMessages(["lines.{$index}" => ['Each allocation line must be an object.']]);
            }
            $receivableId = $this->id($input['receivable_open_item_id'] ?? null, "lines.{$index}.receivable_open_item_id");
            $creditId = $this->id($input['credit_open_item_id'] ?? null, "lines.{$index}.credit_open_item_id");
            $pair = "{$receivableId}:{$creditId}";
            if (isset ($seenPairs[$pair])) {
                throw ValidationException::withMessages(["lines.{$index}" => ['The same receivable and credit pair cannot be repeated.']]);
            }
            $seenPairs[$pair] = true;
            $amount = $this->positiveMoney($input['amount'] ?? null, "lines.{$index}.amount");
            $receivable = CustomerOpenItem::query()->whereKey($receivableId)->lockForUpdate()->first();
            $credit = CustomerOpenItem::query()->whereKey($creditId)->lockForUpdate()->first();
            if (!$receivable instanceof CustomerOpenItem || !$receivable->isReceivable() || !$credit instanceof CustomerOpenItem || !$credit->isCredit()) {
                throw ValidationException::withMessages(["lines.{$index}" => ['The selected receivable or credit item is invalid.']]);
            }
            foreach ([$receivable, $credit] as $item) {
                if ((int) $item->branch_id !== (int) $branch->getKey() || (int) $item->customer_id !== (int) $customer->getKey() || strtoupper((string) $item->currency_code) !== $data['currency_code'] || $item->isReversed()) {
                    throw ValidationException::withMessages(["lines.{$index}" => ['All open items must use the selected branch, customer, currency, and an active balance.']]);
                }
            }
            $receivableTotals[$receivableId] = ($receivableTotals[$receivableId] ?? BigDecimal::zero())->plus($amount);
            $creditTotals[$creditId] = ($creditTotals[$creditId] ?? BigDecimal::zero())->plus($amount);
            if ($receivableTotals[$receivableId]->isGreaterThan(BigDecimal::of((string) $receivable->outstanding_amount))) {
                throw ValidationException::withMessages(["lines.{$index}.amount" => ['The total application exceeds the receivable outstanding amount.']]);
            }
            if ($creditTotals[$creditId]->isGreaterThan(BigDecimal::of((string) $credit->outstanding_amount))) {
                throw ValidationException::withMessages(["lines.{$index}.amount" => ['The total application exceeds the credit outstanding amount.']]);
            }
            $lines[] = ['receivable_open_item_id' => $receivableId, 'credit_open_item_id' => $creditId, 'receivable_document_number' => $receivable->document_number, 'credit_document_number' => $credit->document_number, 'credit_item_type' => $credit->item_type, 'amount' => $this->decimal($amount), 'receivable_exchange_rate' => (string) $receivable->exchange_rate, 'credit_exchange_rate' => (string) $credit->exchange_rate, 'status' => 'draft',];
        }
        return $lines;
    }

    private function revalidateStoredLines(CustomerCreditApplication $application): void
    {
        $data = ['currency_code' => $application->currency_code, 'lines' => $application->lines()->orderBy('line_number')->get()->map(static fn(CustomerCreditApplicationLine $line): array => ['receivable_open_item_id' => $line->receivable_open_item_id, 'credit_open_item_id' => $line->credit_open_item_id, 'amount' => (string) $line->amount,])->all(),];
        $branch = Branch::query()->whereKey($application->branch_id)->lockForUpdate()->firstOrFail();
        $customer = Customer::query()->whereKey($application->customer_id)->lockForUpdate()->firstOrFail();
        $this->validateLines($data, $branch, $customer);
    }
    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(CustomerCreditApplication $application, array $lines): void
    {
        $application->lines()->delete();
        foreach ($lines as $index => $line) {
            $application->lines()->create([... $line, 'line_number' => $index + 1]);
        }
    }
    /** @param Collection<int, CustomerCreditApplicationLine> $lines */
    private function lockApplicationOpenItems(Collection $lines): void
    {
        $ids = $lines
            ->flatMap(
                static fn (CustomerCreditApplicationLine $line): array => [
                    (int) $line->receivable_open_item_id,
                    (int) $line->credit_open_item_id,
                ],
            )
            ->unique()
            ->sort()
            ->values();

        $lockedCount = CustomerOpenItem::query()
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        if ($lockedCount !== $ids->count()) {
            throw new LogicException(
                'One or more Customer Credit Application open items are unavailable.',
            );
        }
    }

    /** @param list<array<string, mixed>> $lines @return array{total_amount: string} */
    private function lineTotals(array $lines): array
    {
        $total = BigDecimal::zero();
        foreach ($lines as $line) {
            $total = $total->plus((string) $line['amount']);
        }
        if ($total->isGreaterThan(BigDecimal::of(self::MAXIMUM_AMOUNT))) {
            throw ValidationException::withMessages([
                'lines' => ['The total credit application amount exceeds the supported maximum.'],
            ]);
        }
        return['total_amount' => $this->decimal($total)];
    }
    /** @return array{0: Branch, 1: Customer} */
    private function context(int $branchId, int $customerId, User $actor, bool $active): array
    {
        $branch = Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
        $this->branchAccessService->authorizeBranch($actor, $branch, $active);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
        if ((int) $customer->tenant_id !== (int) $actor->tenant_id || ($active && !$customer->isActive())) {
            throw ValidationException::withMessages(['customer_id' => ['The selected customer is inactive or unavailable.']]);
        }
        return[$branch, $customer];
    }

    private function lockAndAuthorize(CustomerCreditApplication $application, User $actor, bool $active): CustomerCreditApplication
    {
        $locked = CustomerCreditApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
        $this->context((int) $locked->branch_id, (int) $locked->customer_id, $actor, $active);
        return $locked;
    }

    private function transition(CustomerCreditApplication $application, string $next): void
    {
        if (!$this->statusRegistry->canTransition($application->status, $next)) {
            throw ValidationException::withMessages(['status' => ["Customer Credit Application cannot move from {$application->status} to {$next}."]]);
        }
    }

    private function ensureTransition(CustomerCreditApplication $application, string $required, string $message): void
    {
        if ($application->status !== $required) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    private function load(CustomerCreditApplication $application): CustomerCreditApplication
    {
        return $application->load(['branch:id,name,code,status', 'customer:id,name,code,status', 'lines.receivableOpenItem', 'lines.creditOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
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
            $amount = BigDecimal::of((string) $value)->toScale(self::SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['The amount must be positive with at most 6 decimal places.']]);
        }
        if (!$amount->isPositive()) {
            throw ValidationException::withMessages([$field => ['The amount must be greater than zero.']]);
        }
        return $amount;
    }

    private function date(mixed $value, string $field, string $timezone): string
    {
        if (!is_string($value)) {
            throw ValidationException::withMessages([$field => ['The date must use YYYY-MM-DD format.']]);
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', trim($value), $timezone);
        if (!$date instanceof CarbonImmutable || $date->format('Y-m-d') !== trim($value)) {
            throw ValidationException::withMessages([$field => ['The date must use YYYY-MM-DD format.']]);
        }
        return trim($value);
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency_code' => ['The currency code must contain three letters.']]);
        }
        return $currency;
    }

    private function requiredText(mixed $value, int $max, string $field): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$field => ["The {$field} field is required and may not exceed {$max} characters."]]);
        }
        return $text;
    }

    private function nullableText(mixed $value, int $max): ? string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages(['notes' => ["Notes may not exceed {$max} characters."]]);
        }
        return $text;
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}