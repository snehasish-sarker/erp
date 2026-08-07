<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CustomerOpenItem;
use App\Models\User;
use App\Services\Accounting\CustomerSettlementPresentationService;
use App\Services\Organisation\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerCreditBalanceController extends Controller
{
    public function __construct(private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementPresentationService $presentation,)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('customer_credits.view') === true, 403);
        $actor = $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'branch_id' => ['nullable', 'integer', 'min:1'], 'customer_id' => ['nullable', 'integer', 'min:1'], 'currency_code' => ['nullable', 'string', 'size:3'], 'item_type' => ['nullable', 'in:credit,receipt,adjustment_credit'], 'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],]);
        $search = trim((string)($filters['search'] ?? ''));
        $query = CustomerOpenItem::query()->with(['branch:id,name,code', 'customer:id,name,code'])->whereIn('item_type', ['credit', 'receipt', 'adjustment_credit'])->whereIn('status', ['open', 'partially_settled'])->where('outstanding_amount', '>', 0)->when($search !== '', static function(Builder $query) use($search): void
        {
            $query->where(static function(Builder $nested) use($search): void
            {
                $nested->where('document_number', 'like', "%{$search}%")->orWhereHas('customer', static fn(Builder $customer): Builder => $customer->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }
            );
        }
        )->when(isset ($filters['branch_id']), static fn(Builder $q): Builder => $q->where('branch_id', (int) $filters['branch_id']))->when(isset ($filters['customer_id']), static fn(Builder $q): Builder => $q->where('customer_id', (int) $filters['customer_id']))->when(isset ($filters['currency_code']), static fn(Builder $q): Builder => $q->where('currency_code', strtoupper((string) $filters['currency_code'])))->when(isset ($filters['item_type']), static fn(Builder $q): Builder => $q->where('item_type', (string) $filters['item_type']))->orderBy('posting_date')->orderBy('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'customer_open_items.branch_id');
        $summaryQuery = clone $query;
        $paginator = $query->paginate((int)($filters['per_page'] ?? 15))->withQueryString();
        $summary = $summaryQuery
            ->reorder()
            ->selectRaw('currency_code, SUM(outstanding_amount) AS outstanding_amount, SUM(base_outstanding_amount) AS base_outstanding_amount, COUNT(*) AS item_count')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();
        return Inertia::render('CustomerCredits/Index', ['credits' => ['data' => $paginator->getCollection()->map(fn(CustomerOpenItem $item): array => $this->item($item))->values()->all(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()],], 'summary' => $summary->map(static fn($row): array => ['currency_code' => $row->currency_code, 'outstanding_amount' => (string) $row->outstanding_amount, 'base_outstanding_amount' => (string) $row->base_outstanding_amount, 'item_count' => (int) $row->item_count])->values()->all(), 'filters' => ['search' => $search, 'branch_id' => isset ($filters['branch_id']) ? (int) $filters['branch_id']: null, 'customer_id' => isset ($filters['customer_id']) ? (int) $filters['customer_id']: null, 'currency_code' => strtoupper((string)($filters['currency_code'] ?? '')), 'item_type' => (string)($filters['item_type'] ?? ''), 'per_page' => (int)($filters['per_page'] ?? 15)], 'branches' => $this->presentation->branches($actor, false), 'customers' => $this->presentation->customers(), 'can' => ['apply' => $actor->can('customer_credit_applications.create'), 'refund' => $actor->can('customer_refunds.create'),],]);
    }
    /** @return array<string, mixed> */
    private function item(CustomerOpenItem $item): array
    {
        return['id' => (int) $item->getKey(), 'item_type' => $item->item_type, 'document_number' => $item->document_number, 'document_date' => $item->document_date?->format('Y-m-d'), 'posting_date' => $item->posting_date?->format('Y-m-d'), 'currency_code' => $item->currency_code, 'exchange_rate' => (string) $item->exchange_rate, 'original_amount' => (string) $item->original_amount, 'allocated_amount' => (string) $item->allocated_amount, 'outstanding_amount' => (string) $item->outstanding_amount, 'base_outstanding_amount' => (string) $item->base_outstanding_amount, 'status' => $item->status, 'branch' => $item->branch ? ['id' => (int) $item->branch->getKey(), 'name' => $item->branch->name, 'code' => $item->branch->code]: null, 'customer' => $item->customer ? ['id' => (int) $item->customer->getKey(), 'name' => $item->customer->name, 'code' => $item->customer->code]: null,];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
