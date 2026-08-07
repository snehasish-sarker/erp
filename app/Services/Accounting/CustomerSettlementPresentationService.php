<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerOpenItem;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;

final class CustomerSettlementPresentationService
{
    public function __construct(private readonly BranchAccessService $branchAccessService,)
    {
    }
    /** @return list<array<string, mixed>> */
    public function branches(User $actor, bool $activeOnly = true): array
    {
        return $this->branchAccessService->accessibleBranches($actor, $activeOnly)->map(static fn(Branch $branch): array => ['id' => (int) $branch->getKey(), 'name' => $branch->name, 'code' => $branch->code, 'status' => $branch->status,])->values()->all();
    }
    /** @return list<array<string, mixed>> */
    public function customers(): array
    {
        return Customer::query()->where('status', 'active')->orderBy('name')->limit(2000)->get(['id', 'name', 'code', 'credit_limit'])->map(static fn(Customer $customer): array => ['id' => (int) $customer->getKey(), 'name' => $customer->name, 'code' => $customer->code, 'credit_limit' => (string) $customer->credit_limit,])->values()->all();
    }
    /** @return list<array<string, mixed>> */
    public function cashAndBankAccounts(): array
    {
        return Account::query()->where('status', 'active')->where('is_group', false)->whereIn('control_type', ['cash', 'bank'])->orderBy('code')->get(['id', 'code', 'name', 'control_type'])->map(static fn(Account $account): array => ['id' => (int) $account->getKey(), 'code' => $account->code, 'name' => $account->name, 'control_type' => $account->control_type,])->values()->all();
    }
    /** @return list<array<string, mixed>> */
    public function adjustmentAccounts(): array
    {
        return Account::query()->where('status', 'active')->where('is_group', false)->where('allow_manual_posting', true)->whereNotIn('system_key', ['accounts_receivable_control', 'customer_advances'])->orderBy('code')->limit(2000)->get(['id', 'code', 'name', 'account_type', 'account_subtype'])->map(static fn(Account $account): array => ['id' => (int) $account->getKey(), 'code' => $account->code, 'name' => $account->name, 'account_type' => $account->account_type, 'account_subtype' => $account->account_subtype,])->values()->all();
    }
    /** @return list<array<string, mixed>> */
    public function openReceivables(User $actor, ? int $branchId = null, ? int $customerId = null, ? string $currency = null): array
    {
        return $this->openItems($actor, ['invoice', 'adjustment_debit'], $branchId, $customerId, $currency);
    }
    /** @return list<array<string, mixed>> */
    public function openCredits(User $actor, ? int $branchId = null, ? int $customerId = null, ? string $currency = null): array
    {
        return $this->openItems($actor, ['credit', 'receipt', 'adjustment_credit'], $branchId, $customerId, $currency);
    }
    /** @param list<string> $itemTypes @return list<array<string, mixed>> */
    private function openItems(User $actor, array $itemTypes, ? int $branchId, ? int $customerId, ? string $currency): array
    {
        $query = CustomerOpenItem::query()->with(['branch:id,name,code', 'customer:id,name,code'])->whereIn('item_type', $itemTypes)->whereIn('status', ['open', 'partially_settled'])->where('outstanding_amount', '>', 0)->when($branchId !== null, static fn(Builder $q): Builder => $q->where('branch_id', $branchId))->when($customerId !== null, static fn(Builder $q): Builder => $q->where('customer_id', $customerId))->when($currency !== null && $currency !== '', static fn(Builder $q): Builder => $q->where('currency_code', strtoupper($currency)))->orderBy('posting_date')->orderBy('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'customer_open_items.branch_id');
        return $query->limit(3000)->get()->map(static fn(CustomerOpenItem $item): array => ['id' => (int) $item->getKey(), 'branch_id' => (int) $item->branch_id, 'customer_id' => (int) $item->customer_id, 'item_type' => $item->item_type, 'document_number' => $item->document_number, 'document_date' => $item->document_date?->format('Y-m-d'), 'posting_date' => $item->posting_date?->format('Y-m-d'), 'due_date' => $item->due_date?->format('Y-m-d'), 'currency_code' => $item->currency_code, 'exchange_rate' => (string) $item->exchange_rate, 'original_amount' => (string) $item->original_amount, 'allocated_amount' => (string) $item->allocated_amount, 'outstanding_amount' => (string) $item->outstanding_amount, 'base_outstanding_amount' => (string) $item->base_outstanding_amount, 'status' => $item->status, 'branch' => $item->branch ? ['id' => (int) $item->branch->getKey(), 'name' => $item->branch->name, 'code' => $item->branch->code]: null, 'customer' => $item->customer ? ['id' => (int) $item->customer->getKey(), 'name' => $item->customer->name, 'code' => $item->customer->code]: null,])->values()->all();
    }
}