<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerReceiptAccountingGateway;
use App\Events\Accounting\CustomerReceiptPosted;
use App\Events\Accounting\CustomerReceiptReversed;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\CustomerOpenItem;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\CustomerReceiptMethodRegistry;
use App\Support\Accounting\CustomerReceiptStatusRegistry;
use App\Support\Tenancy\TenantContext;
use ArithmeticException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerReceiptService
{
    private const MONEY_SCALE = 6;

    private const RATE_SCALE = 8;

    private const MAXIMUM_AMOUNT =
        '99999999999999.999999';

    private const MAXIMUM_ALLOCATIONS = 500;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly CustomerReceiptStatusRegistry $statusRegistry,
        private readonly CustomerReceiptMethodRegistry $methodRegistry,
        private readonly CustomerReceiptAccountingGateway $accountingGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): CustomerReceipt {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $normalized = $this->normalizeInput(
            $data,
            $tenant,
        );

        return DB::transaction(
            function () use (
                $normalized,
                $actor,
                $tenantId,
            ): CustomerReceipt {
                $branch = $this->resolveBranch(
                    $normalized['branch_id'],
                    $actor,
                    true,
                );

                $customer = $this->resolveCustomer(
                    $normalized['customer_id'],
                    true,
                );

                $account = $this->resolveReceiptAccount(
                    $normalized['receipt_account_id'],
                    $normalized['receipt_method'],
                    true,
                );

                $allocations = $this->buildAllocations(
                    $normalized['allocations'],
                    $branch,
                    $customer,
                    $normalized['currency_code'],
                    $normalized['exchange_rate'],
                    $tenantId,
                );

                $totals = $this->allocationTotals(
                    $normalized['total_amount'],
                    $allocations,
                );

                $receipt = CustomerReceipt::query()->create([
                    'branch_id' => $branch->getKey(),
                    'customer_id' => $customer->getKey(),
                    'receipt_account_id' => $account->getKey(),
                    'document_number_allocation_id' => null,
                    'receipt_number' => null,
                    'receipt_date' =>
                        $normalized['receipt_date'],
                    'posting_date' =>
                        $normalized['posting_date'],
                    'currency_code' =>
                        $normalized['currency_code'],
                    'exchange_rate' =>
                        $normalized['exchange_rate'],
                    'receipt_method' =>
                        $normalized['receipt_method'],
                    'receipt_reference' =>
                        $normalized['receipt_reference'],
                    'cheque_number' =>
                        $normalized['cheque_number'],
                    'cheque_date' =>
                        $normalized['cheque_date'],
                    'customer_name' => $customer->name,
                    'customer_code' => $customer->code,
                    'receipt_account_code' => $account->code,
                    'receipt_account_name' => $account->name,
                    'status' => 'draft',
                    ...$totals,
                    'base_total_amount' => '0.000000',
                    'base_allocated_amount' => '0.000000',
                    'base_unallocated_amount' => '0.000000',
                    'notes' => $normalized['notes'],
                    'revision' => 1,
                    'created_by_user_id' =>
                        $actor->getKey(),
                ]);

                $this->createDraftAllocations(
                    $receipt,
                    $allocations,
                );

                return $this->loadReceipt(
                    $receipt,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        CustomerReceipt $customerReceipt,
        array $data,
        User $actor,
    ): CustomerReceipt {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        $normalized = $this->normalizeInput(
            $data,
            $tenant,
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $normalized,
                $actor,
                $tenantId,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    false,
                );

                if (
                    !$this->statusRegistry->isEditable(
                        $receipt->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Customer Receipt can be edited.',
                        ],
                    ]);
                }

                $branch = $this->resolveBranch(
                    $normalized['branch_id'],
                    $actor,
                    true,
                );

                $this->ensureNumberedIdentityUnchanged(
                    $receipt,
                    (int) $branch->getKey(),
                    $normalized['receipt_date'],
                );

                $customer = $this->resolveCustomer(
                    $normalized['customer_id'],
                    true,
                );

                $account = $this->resolveReceiptAccount(
                    $normalized['receipt_account_id'],
                    $normalized['receipt_method'],
                    true,
                );

                $allocations = $this->buildAllocations(
                    $normalized['allocations'],
                    $branch,
                    $customer,
                    $normalized['currency_code'],
                    $normalized['exchange_rate'],
                    $tenantId,
                );

                $totals = $this->allocationTotals(
                    $normalized['total_amount'],
                    $allocations,
                );

                $this->deleteDraftAllocations(
                    $receipt,
                );

                $receipt->fill([
                    'branch_id' => $branch->getKey(),
                    'customer_id' => $customer->getKey(),
                    'receipt_account_id' =>
                        $account->getKey(),
                    'receipt_date' =>
                        $normalized['receipt_date'],
                    'posting_date' =>
                        $normalized['posting_date'],
                    'currency_code' =>
                        $normalized['currency_code'],
                    'exchange_rate' =>
                        $normalized['exchange_rate'],
                    'receipt_method' =>
                        $normalized['receipt_method'],
                    'receipt_reference' =>
                        $normalized['receipt_reference'],
                    'cheque_number' =>
                        $normalized['cheque_number'],
                    'cheque_date' =>
                        $normalized['cheque_date'],
                    'customer_name' => $customer->name,
                    'customer_code' => $customer->code,
                    'receipt_account_code' => $account->code,
                    'receipt_account_name' => $account->name,
                    ...$totals,
                    'base_total_amount' => '0.000000',
                    'base_allocated_amount' => '0.000000',
                    'base_unallocated_amount' => '0.000000',
                    'notes' => $normalized['notes'],
                    'revision' =>
                        (int) $receipt->revision + 1,
                ]);

                $receipt->save();

                $this->createDraftAllocations(
                    $receipt,
                    $allocations,
                );

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        DB::transaction(
            function () use (
                $customerReceipt,
                $actor,
            ): void {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    false,
                );

                if (!$receipt->canBeDeleted()) {
                    throw ValidationException::withMessages([
                        'customer_receipt' => [
                            'Only an unnumbered, never-submitted draft Customer Receipt can be permanently deleted.',
                        ],
                    ]);
                }

                $this->deleteDraftAllocations(
                    $receipt,
                );

                $receipt->forceDelete();
            },
            attempts: 5,
        );
    }

    public function submit(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): CustomerReceipt {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $actor,
                $tenant,
                $tenantId,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    true,
                );

                $this->ensureTransition(
                    $receipt,
                    'submitted',
                );

                $this->validateCurrentReceipt(
                    $receipt,
                    $actor,
                    $tenantId,
                    true,
                    true,
                );

                if (!$receipt->hasReceiptNumber()) {
                    $allocation =
                        $this->documentNumberService->allocate(
                            documentType:
                                'customer_receipt',

                            branchId:
                                (int) $receipt->branch_id,

                            idempotencyKey:
                                $this->numberAllocationKey(
                                    $receipt,
                                ),

                            allocatableType:
                                CustomerReceipt::class,

                            allocatableId:
                                (int) $receipt->getKey(),

                            allocatedAt:
                                $this->businessDateTime(
                                    $receipt
                                        ->receipt_date
                                        ->toDateString(),

                                    $tenant,
                                ),
                        );

                    $receipt
                        ->document_number_allocation_id =
                            $allocation->getKey();

                    $receipt->receipt_number =
                        $allocation->number;
                }

                $receipt->status = 'submitted';

                $receipt->submitted_by_user_id =
                    $actor->getKey();

                $receipt->submitted_at =
                    CarbonImmutable::now('UTC');

                $receipt->save();

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): CustomerReceipt {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $actor,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    false,
                );

                $this->ensureTransition(
                    $receipt,
                    'draft',
                );

                $this->ensureDraftAllocationState(
                    $receipt,
                );

                $receipt->status = 'draft';
                $receipt->submitted_by_user_id = null;
                $receipt->submitted_at = null;

                $receipt->revision =
                    (int) $receipt->revision + 1;

                $receipt->save();

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function approve(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): CustomerReceipt {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $actor,
                $tenantId,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    true,
                );

                $this->ensureTransition(
                    $receipt,
                    'approved',
                );

                if (!$receipt->hasReceiptNumber()) {
                    throw new LogicException(
                        'A submitted Customer Receipt must retain its receipt number before approval.',
                    );
                }

                $this->validateCurrentReceipt(
                    $receipt,
                    $actor,
                    $tenantId,
                    true,
                    true,
                );

                $receipt->status = 'approved';

                $receipt->approved_by_user_id =
                    $actor->getKey();

                $receipt->approved_at =
                    CarbonImmutable::now('UTC');

                $receipt->save();

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        CustomerReceipt $customerReceipt,
        string $reason,
        User $actor,
    ): CustomerReceipt {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        $reason = $this->requiredReason(
            $reason,
            'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $reason,
                $actor,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    false,
                );

                $this->ensureTransition(
                    $receipt,
                    'cancelled',
                );

                $this->ensureDraftAllocationState(
                    $receipt,
                );

                CustomerReceiptAllocation::query()
                    ->where(
                        'customer_receipt_id',
                        $receipt->getKey(),
                    )
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' =>
                            CarbonImmutable::now('UTC'),
                    ]);

                $receipt->status = 'cancelled';

                $receipt->cancelled_by_user_id =
                    $actor->getKey();

                $receipt->cancelled_at =
                    CarbonImmutable::now('UTC');

                $receipt->cancellation_reason =
                    $reason;

                $receipt->save();

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): CustomerReceipt {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $actor,
                $tenant,
                $tenantId,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    true,
                );

                $this->ensureTransition(
                    $receipt,
                    'posted',
                );

                if (!$receipt->hasReceiptNumber()) {
                    throw new LogicException(
                        'The approved Customer Receipt does not retain its receipt number.',
                    );
                }

                $this->validateCurrentReceipt(
                    $receipt,
                    $actor,
                    $tenantId,
                    false,
                    true,
                );

                $postingDate =
                    $this->businessDateTime(
                        $receipt
                            ->posting_date
                            ->toDateString(),

                        $tenant,
                    );

                $period =
                    $this->accountingPeriodService
                        ->lockOpenPeriod(
                            $postingDate,
                        );

                /*
                 * The configured gateway must create all of the following
                 * inside this existing transaction:
                 *
                 * - Balanced General Ledger journal
                 * - Customer ledger receipt entry
                 * - Customer receipt credit open item
                 * - Invoice open-item allocations
                 * - Realized exchange gain or loss, when applicable
                 *
                 */
                $reference =
                    $this->accountingGateway->post(
                        customerReceipt:
                            $receipt,

                        accountingPeriod:
                            $period,

                        actor:
                            $actor,
                    );

                $reference =
                    $this->requiredAccountingReference(
                        $reference,
                    );

                $this->validateGatewayPostingResult(
                    $receipt,
                );

                $receipt->status = 'posted';

                $receipt->posted_by_user_id =
                    $actor->getKey();

                $receipt->posted_at =
                    CarbonImmutable::now('UTC');

                $receipt->accounting_posting_reference =
                    $reference;

                $receipt->save();

                CustomerReceiptPosted::dispatch(
                    tenantId: $tenantId,

                    customerReceiptId:
                        (int) $receipt->getKey(),

                    customerId:
                        (int) $receipt->customer_id,

                    branchId:
                        (int) $receipt->branch_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        CustomerReceipt $customerReceipt,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): CustomerReceipt {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensureReceiptBelongsToTenant(
            $customerReceipt,
            $tenantId,
        );

        $reversalDateString =
            $this->normalizeDate(
                $reversalPostingDate,
                'reversal_posting_date',
                $tenant,
            );

        $reason = $this->requiredReason(
            $reason,
            'reversal_reason',
        );

        return DB::transaction(
            function () use (
                $customerReceipt,
                $reversalDateString,
                $reason,
                $actor,
                $tenant,
                $tenantId,
            ): CustomerReceipt {
                $receipt = $this->lockReceipt(
                    $customerReceipt,
                );

                $this->authorizeReceiptBranch(
                    $actor,
                    $receipt,
                    false,
                );

                $this->ensureTransition(
                    $receipt,
                    'reversed',
                );

                if (
                    $reversalDateString
                    < $receipt
                        ->posting_date
                        ->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'reversal_posting_date' => [
                            'The reversal posting date cannot be before the original posting date.',
                        ],
                    ]);
                }

                if (
                    $receipt
                        ->accounting_posting_reference
                    === null
                    || trim(
                        $receipt
                            ->accounting_posting_reference,
                    ) === ''
                ) {
                    throw new LogicException(
                        'The posted Customer Receipt does not retain its accounting posting reference.',
                    );
                }

                $this->validatePostedAllocationState(
                    $receipt,
                );

                $reversalDate =
                    $this->businessDateTime(
                        $reversalDateString,
                        $tenant,
                    );

                $period =
                    $this->accountingPeriodService
                        ->lockOpenPeriod(
                            $reversalDate,
                        );

                $reference =
                    $this->accountingGateway->reverse(
                        customerReceipt:
                            $receipt,

                        accountingPeriod:
                            $period,

                        reversalPostingDate:
                            $reversalDate,

                        reason:
                            $reason,

                        actor:
                            $actor,
                    );

                $reference =
                    $this->requiredAccountingReference(
                        $reference,
                    );

                $this->validateGatewayReversalResult(
                    $receipt,
                );

                $receipt->status = 'reversed';

                $receipt->reversal_posting_date =
                    $reversalDateString;

                $receipt->reversed_by_user_id =
                    $actor->getKey();

                $receipt->reversed_at =
                    CarbonImmutable::now('UTC');

                $receipt->reversal_reason =
                    $reason;

                $receipt->accounting_reversal_reference =
                    $reference;

                $receipt->save();

                CustomerReceiptReversed::dispatch(
                    tenantId: $tenantId,

                    customerReceiptId:
                        (int) $receipt->getKey(),

                    customerId:
                        (int) $receipt->customer_id,

                    branchId:
                        (int) $receipt->branch_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadReceipt(
                    $receipt->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     branch_id: int,
     *     customer_id: int,
     *     receipt_account_id: int,
     *     receipt_date: string,
     *     posting_date: string,
     *     currency_code: string,
     *     exchange_rate: string,
     *     receipt_method: string,
     *     receipt_reference: string|null,
     *     cheque_number: string|null,
     *     cheque_date: string|null,
     *     total_amount: string,
     *     notes: string|null,
     *     allocations: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $receiptDate =
            $this->normalizeDate(
                $data['receipt_date'] ?? null,
                'receipt_date',
                $tenant,
            );

        $postingDate =
            $this->normalizeDate(
                $data['posting_date'] ?? null,
                'posting_date',
                $tenant,
            );

        if ($postingDate < $receiptDate) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date cannot be before the receipt date.',
                ],
            ]);
        }

        $currencyCode =
            $this->normalizeCurrencyCode(
                $data['currency_code'] ?? null,
            );

        $exchangeRate =
            $this->positiveDecimal(
                $data['exchange_rate'] ?? null,
                self::RATE_SCALE,
                'exchange_rate',
                'exchange rate',
            );

        if (
            $currencyCode
            === mb_strtoupper(
                (string) $tenant->currency_code,
            )
            && !$exchangeRate->isEqualTo(
                BigDecimal::one()
                    ->toScale(
                        self::RATE_SCALE,
                    ),
            )
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be 1.00000000 for the tenant base currency.',
                ],
            ]);
        }

        $method = trim(
            (string) (
                $data['receipt_method']
                ?? ''
            ),
        );

        if (
            !$this->methodRegistry->exists(
                $method,
            )
        ) {
            throw ValidationException::withMessages([
                'receipt_method' => [
                    'The selected Customer Receipt method is invalid.',
                ],
            ]);
        }

        $chequeNumber =
            $this->nullableString(
                $data['cheque_number'] ?? null,
                'cheque_number',
                100,
            );

        $chequeDate = null;

        if (
            $this->methodRegistry
                ->requiresChequeDetails(
                    $method,
                )
        ) {
            if ($chequeNumber === null) {
                throw ValidationException::withMessages([
                    'cheque_number' => [
                        'A cheque number is required for cheque receipts.',
                    ],
                ]);
            }

            $chequeDate =
                $this->normalizeDate(
                    $data['cheque_date'] ?? null,
                    'cheque_date',
                    $tenant,
                );
        }

        $allocations =
            $data['allocations'] ?? [];

        if (!is_array($allocations)) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'Customer Receipt allocations must be an array.',
                ],
            ]);
        }

        if (
            count($allocations)
            > self::MAXIMUM_ALLOCATIONS
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'A Customer Receipt cannot contain more than 500 invoice allocations.',
                ],
            ]);
        }

        /** @var list<array<string, mixed>> $allocations */
        return [
            'branch_id' =>
                $this->requiredId(
                    $data['branch_id'] ?? null,
                    'branch_id',
                    'branch',
                ),

            'customer_id' =>
                $this->requiredId(
                    $data['customer_id'] ?? null,
                    'customer_id',
                    'customer',
                ),

            'receipt_account_id' =>
                $this->requiredId(
                    $data['receipt_account_id'] ?? null,
                    'receipt_account_id',
                    'receipt account',
                ),

            'receipt_date' =>
                $receiptDate,

            'posting_date' =>
                $postingDate,

            'currency_code' =>
                $currencyCode,

            'exchange_rate' =>
                $exchangeRate->__toString(),

            'receipt_method' =>
                $method,

            'receipt_reference' =>
                $this->nullableString(
                    $data['receipt_reference']
                        ?? null,

                    'receipt_reference',
                    160,
                ),

            'cheque_number' =>
                $chequeNumber,

            'cheque_date' =>
                $chequeDate,

            'total_amount' =>
                $this->positiveMoney(
                    $data['total_amount']
                        ?? null,

                    'total_amount',
                )->__toString(),

            'notes' =>
                $this->nullableString(
                    $data['notes'] ?? null,
                    'notes',
                    5000,
                ),

            'allocations' =>
                $allocations,
        ];
    }

    /**
     * @param list<array<string, mixed>> $inputAllocations
     *
     * @return list<array{
     *     customer_open_item_id: int,
     *     sales_invoice_id: int,
     *     invoice_document_number: string|null,
     *     invoice_due_date: string|null,
     *     currency_code: string,
     *     invoice_exchange_rate: string,
     *     receipt_exchange_rate: string,
     *     amount: string
     * }>
     */
    private function buildAllocations(
        array $inputAllocations,
        Branch $branch,
        Customer $customer,
        string $currencyCode,
        string $receiptExchangeRate,
        int $tenantId,
    ): array {
        if ($inputAllocations === []) {
            return [];
        }

        /**
         * @var array<
         *     int,
         *     array{
         *         index: int,
         *         amount: BigDecimal
         *     }
         * > $inputById
         */
        $inputById = [];

        foreach (
            $inputAllocations
            as $index => $allocation
        ) {
            if (!is_array($allocation)) {
                throw ValidationException::withMessages([
                    "allocations.{$index}" => [
                        'Each Customer Receipt allocation must be an object.',
                    ],
                ]);
            }

            $openItemId =
                $this->requiredId(
                    $allocation[
                        'customer_open_item_id'
                    ] ?? null,

                    "allocations.{$index}.customer_open_item_id",

                    'Sales Invoice open item',
                );

            if (isset($inputById[$openItemId])) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.customer_open_item_id" => [
                        'The same Sales Invoice open item cannot be allocated more than once.',
                    ],
                ]);
            }

            $inputById[$openItemId] = [
                'index' => $index,

                'amount' =>
                    $this->positiveMoney(
                        $allocation['amount']
                            ?? null,

                        "allocations.{$index}.amount",
                    ),
            ];
        }

        $openItemIds =
            array_keys($inputById);

        sort(
            $openItemIds,
            SORT_NUMERIC,
        );

        /**
         * @var Collection<int, CustomerOpenItem> $openItems
         */
        $openItems =
            CustomerOpenItem::query()
                ->whereIn(
                    'id',
                    $openItemIds,
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        CustomerOpenItem $item,
                    ): int => (int) $item->getKey(),
                );

        if (
            $openItems->count()
            !== count($openItemIds)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more selected Sales Invoice open items are unavailable.',
                ],
            ]);
        }

        $invoiceMorphClass =
            (new SalesInvoice())
                ->getMorphClass();

        $invoiceIds = [];

        foreach ($openItemIds as $openItemId) {
            $openItem =
                $openItems->get(
                    $openItemId,
                );

            if (
                !$openItem
                instanceof CustomerOpenItem
            ) {
                throw new LogicException(
                    'A selected Sales Invoice open item could not be locked.',
                );
            }

            $input =
                $inputById[$openItemId];

            $this->validateReceivableOpenItem(
                $openItem,
                $input['amount'],
                (int) $branch->getKey(),
                (int) $customer->getKey(),
                $currencyCode,
                $tenantId,
                $invoiceMorphClass,
                "allocations.{$input['index']}.customer_open_item_id",
            );

            $invoiceIds[
                (int) $openItem->source_id
            ] = true;
        }

        $invoiceIds =
            array_keys($invoiceIds);

        sort(
            $invoiceIds,
            SORT_NUMERIC,
        );

        /**
         * @var Collection<int, SalesInvoice> $invoices
         */
        $invoices =
            SalesInvoice::query()
                ->whereIn(
                    'id',
                    $invoiceIds,
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SalesInvoice $invoice,
                    ): int => (int) $invoice
                        ->getKey(),
                );

        if (
            $invoices->count()
            !== count($invoiceIds)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Sales Invoices linked to the selected open items are unavailable.',
                ],
            ]);
        }

        $built = [];

        foreach (
            $inputAllocations
            as $index => $allocation
        ) {
            $openItemId =
                (int) $allocation[
                    'customer_open_item_id'
                ];

            $openItem =
                $openItems->get(
                    $openItemId,
                );

            if (
                !$openItem
                instanceof CustomerOpenItem
            ) {
                throw new LogicException(
                    'A selected Sales Invoice open item is unavailable.',
                );
            }

            $invoice =
                $invoices->get(
                    (int) $openItem->source_id,
                );

            if (
                !$invoice
                    instanceof SalesInvoice
                || !$invoice->isPosted()
                || (int) $invoice->tenant_id
                    !== $tenantId
                || (int) $invoice->branch_id
                    !== (int) $branch->getKey()
                || (int) $invoice->customer_id
                    !== (int) $customer->getKey()
                || mb_strtoupper(
                    $invoice->currency_code,
                ) !== $currencyCode
            ) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.customer_open_item_id" => [
                        'The selected open item must belong to a posted Sales Invoice with the same tenant, branch, customer, and currency as the receipt.',
                    ],
                ]);
            }

            $built[] = [
                'customer_open_item_id' =>
                    $openItemId,

                'sales_invoice_id' =>
                    (int) $invoice->getKey(),

                'invoice_document_number' =>
                    $openItem->document_number,

                'invoice_due_date' =>
                    $openItem
                        ->due_date
                        ?->toDateString(),

                'currency_code' =>
                    $currencyCode,

                'invoice_exchange_rate' =>
                    $this->positiveDecimal(
                        $openItem->exchange_rate,
                        self::RATE_SCALE,

                        "allocations.{$index}.invoice_exchange_rate",

                        'invoice exchange rate',
                    )->__toString(),

                'receipt_exchange_rate' =>
                    $receiptExchangeRate,

                'amount' =>
                    $inputById[
                        $openItemId
                    ]['amount']
                        ->__toString(),
            ];
        }

        return $built;
    }

    private function validateReceivableOpenItem(
        CustomerOpenItem $openItem,
        BigDecimal $amount,
        int $branchId,
        int $customerId,
        string $currencyCode,
        int $tenantId,
        string $invoiceMorphClass,
        string $field,
    ): void {
        if (
            (int) $openItem->tenant_id
                !== $tenantId
            || (int) $openItem->branch_id
                !== $branchId
            || (int) $openItem->customer_id
                !== $customerId
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected open item is outside the receipt tenant, branch, or customer context.',
                ],
            ]);
        }

        if (
            !$openItem->isInvoice()
            || $openItem->source_type
                !== $invoiceMorphClass
            || $openItem->source_id === null
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected open item must be a posted Sales Invoice receivable.',
                ],
            ]);
        }

        if (
            !in_array(
                $openItem->status,
                [
                    'open',
                    'partially_settled',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected Sales Invoice open item is not available for receipt.',
                ],
            ]);
        }

        if (
            mb_strtoupper(
                $openItem->currency_code,
            ) !== $currencyCode
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected open item currency does not match the receipt currency.',
                ],
            ]);
        }

        $outstanding =
            $this->nonNegativeMoney(
                $openItem->outstanding_amount,
                $field,
            );

        if (
            $amount->isGreaterThan(
                $outstanding,
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    sprintf(
                        'The allocation exceeds the open item outstanding amount of %s.',
                        $outstanding->__toString(),
                    ),
                ],
            ]);
        }
    }

    /**
     * @param list<array{amount: string}> $allocations
     *
     * @return array{
     *     total_amount: string,
     *     allocated_amount: string,
     *     unallocated_amount: string
     * }
     */
    private function allocationTotals(
        string $totalAmount,
        array $allocations,
    ): array {
        $total =
            $this->positiveMoney(
                $totalAmount,
                'total_amount',
            );

        $allocated =
            BigDecimal::zero()
                ->toScale(
                    self::MONEY_SCALE,
                );

        foreach ($allocations as $allocation) {
            $allocated =
                $allocated->plus(
                    $this->positiveMoney(
                        $allocation['amount'],
                        'allocations.amount',
                    ),
                );
        }

        if ($allocated->isGreaterThan($total)) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'The total Sales Invoice allocations cannot exceed the Customer Receipt amount.',
                ],
            ]);
        }

        return [
            'total_amount' =>
                $total->__toString(),

            'allocated_amount' =>
                $allocated->__toString(),

            'unallocated_amount' =>
                $total
                    ->minus($allocated)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),
        ];
    }

    private function validateCurrentReceipt(
        CustomerReceipt $receipt,
        User $actor,
        int $tenantId,
        bool $requireActiveCustomer,
        bool $requireActiveAccount,
    ): void {
        $branch =
            $this->resolveBranch(
                (int) $receipt->branch_id,
                $actor,
                true,
            );

        $customer =
            $this->resolveCustomer(
                (int) $receipt->customer_id,
                $requireActiveCustomer,
            );

        $this->resolveReceiptAccount(
            (int) $receipt->receipt_account_id,
            $receipt->receipt_method,
            $requireActiveAccount,
        );

        if (
            $receipt
                ->posting_date
                ->toDateString()
            < $receipt
                ->receipt_date
                ->toDateString()
        ) {
            throw new LogicException(
                'The Customer Receipt posting date cannot be before its receipt date.',
            );
        }

        $currencyCode =
            $this->normalizeCurrencyCode(
                $receipt->currency_code,
            );

        $exchangeRate =
            $this->positiveDecimal(
                $receipt->exchange_rate,
                self::RATE_SCALE,
                'exchange_rate',
                'exchange rate',
            );

        $tenant =
            $this->tenantContext->tenant();

        if (
            $currencyCode
            === mb_strtoupper(
                (string) $tenant->currency_code,
            )
            && !$exchangeRate->isEqualTo(
                BigDecimal::one()
                    ->toScale(
                        self::RATE_SCALE,
                    ),
            )
        ) {
            throw new LogicException(
                'The base-currency Customer Receipt exchange rate must be 1.00000000.',
            );
        }

        if (
            $this->methodRegistry
                ->requiresChequeDetails(
                    $receipt->receipt_method,
                )
            && (
                $receipt->cheque_number === null
                || trim(
                    $receipt->cheque_number,
                ) === ''
                || $receipt->cheque_date === null
            )
        ) {
            throw new LogicException(
                'The cheque Customer Receipt does not retain its cheque details.',
            );
        }

        $total =
            $this->positiveMoney(
                $receipt->total_amount,
                'total_amount',
            );

        $storedAllocated =
            $this->nonNegativeMoney(
                $receipt->allocated_amount,
                'allocated_amount',
            );

        $storedUnallocated =
            $this->nonNegativeMoney(
                $receipt->unallocated_amount,
                'unallocated_amount',
            );

        if (
            !$storedAllocated
                ->plus($storedUnallocated)
                ->isEqualTo($total)
        ) {
            throw new LogicException(
                'The Customer Receipt allocation totals do not equal its total amount.',
            );
        }

        /**
         * @var Collection<int, CustomerReceiptAllocation> $intent
         */
        $intent =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        $input = [];

        foreach ($intent as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->customer_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'An unposted Customer Receipt contains a non-draft allocation state.',
                );
            }

            $input[] = [
                'customer_open_item_id' =>
                    $allocation
                        ->customer_open_item_id,

                'amount' =>
                    $allocation->amount,
            ];
        }

        $rebuilt =
            $this->buildAllocations(
                $input,
                $branch,
                $customer,
                $currencyCode,
                $exchangeRate->__toString(),
                $tenantId,
            );

        $totals =
            $this->allocationTotals(
                $total->__toString(),
                $rebuilt,
            );

        if (
            $totals['allocated_amount']
                !== $storedAllocated->__toString()
            || $totals['unallocated_amount']
                !== $storedUnallocated->__toString()
        ) {
            throw new LogicException(
                'The Customer Receipt allocation intent no longer matches its stored totals.',
            );
        }

        foreach (
            $intent
            as $index => $allocation
        ) {
            $rebuiltAllocation =
                $rebuilt[$index] ?? null;

            if (
                !is_array($rebuiltAllocation)
                || (int) $allocation
                    ->sales_invoice_id
                    !== $rebuiltAllocation[
                        'sales_invoice_id'
                    ]
                || $allocation->currency_code
                    !== $rebuiltAllocation[
                        'currency_code'
                    ]
                || (string) $allocation->amount
                    !== $rebuiltAllocation['amount']
            ) {
                throw new LogicException(
                    'A Customer Receipt allocation snapshot no longer matches its receivable open item.',
                );
            }
        }
    }

    private function validateGatewayPostingResult(
        CustomerReceipt $receipt,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation> $allocations
         */
        $allocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isApplied()
                || $allocation
                    ->customer_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The accounting gateway did not apply every receipt allocation atomically.',
                );
            }
        }

        $openItem =
            $this->lockReceiptOpenItem(
                $receipt,
            );

        if (
            (string) $openItem->original_amount
                !== (string) $receipt->total_amount
            || (string) $openItem->allocated_amount
                !== (string) $receipt->allocated_amount
            || (string) $openItem->outstanding_amount
                !== (string) $receipt->unallocated_amount
        ) {
            throw new LogicException(
                'The customer receipt open item does not match the receipt totals.',
            );
        }
    }

    private function validatePostedAllocationState(
        CustomerReceipt $receipt,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation> $allocations
         */
        $allocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isApplied()
                || $allocation
                    ->customer_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The posted Customer Receipt contains an incomplete allocation state.',
                );
            }
        }

        $this->lockReceiptOpenItem(
            $receipt,
        );
    }

    private function validateGatewayReversalResult(
        CustomerReceipt $receipt,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation> $allocations
         */
        $allocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (!$allocation->isReversed()) {
                throw new LogicException(
                    'The accounting gateway did not reverse every receipt allocation atomically.',
                );
            }
        }

        if (
            !$this->lockReceiptOpenItem(
                $receipt,
            )->isReversed()
        ) {
            throw new LogicException(
                'The accounting gateway did not reverse the customer receipt open item.',
            );
        }
    }

    private function lockReceiptOpenItem(
        CustomerReceipt $receipt,
    ): CustomerOpenItem {
        $openItem =
            CustomerOpenItem::query()
                ->where(
                    'source_type',
                    $receipt->getMorphClass(),
                )
                ->where(
                    'source_id',
                    $receipt->getKey(),
                )
                ->where(
                    'item_type',
                    'receipt',
                )
                ->lockForUpdate()
                ->first();

        if (
            !$openItem
            instanceof CustomerOpenItem
        ) {
            throw new LogicException(
                'The required customer receipt open item is missing.',
            );
        }

        return $openItem;
    }

    /**
     * @param list<array{
     *     customer_open_item_id: int,
     *     sales_invoice_id: int,
     *     invoice_document_number: string|null,
     *     invoice_due_date: string|null,
     *     currency_code: string,
     *     invoice_exchange_rate: string,
     *     receipt_exchange_rate: string,
     *     amount: string
     * }> $allocations
     */
    private function createDraftAllocations(
        CustomerReceipt $receipt,
        array $allocations,
    ): void {
        foreach (
            $allocations
            as $index => $allocation
        ) {
            CustomerReceiptAllocation::query()
                ->create([
                    'customer_receipt_id' =>
                        $receipt->getKey(),

                    'customer_open_item_id' =>
                        $allocation[
                            'customer_open_item_id'
                        ],

                    'sales_invoice_id' =>
                        $allocation[
                            'sales_invoice_id'
                        ],

                    'customer_open_item_allocation_id' =>
                        null,

                    'line_number' =>
                        $index + 1,

                    'invoice_document_number' =>
                        $allocation[
                            'invoice_document_number'
                        ],

                    'invoice_due_date' =>
                        $allocation[
                            'invoice_due_date'
                        ],

                    'currency_code' =>
                        $allocation[
                            'currency_code'
                        ],

                    'invoice_exchange_rate' =>
                        $allocation[
                            'invoice_exchange_rate'
                        ],

                    'receipt_exchange_rate' =>
                        $allocation[
                            'receipt_exchange_rate'
                        ],

                    'amount' =>
                        $allocation['amount'],

                    'receivable_base_amount' =>
                        '0.000000',

                    'receipt_base_amount' =>
                        '0.000000',

                    'exchange_difference_amount' =>
                        '0.000000',

                    'status' =>
                        'draft',

                    'applied_at' =>
                        null,

                    'reversed_at' =>
                        null,
                ]);
        }
    }

    private function deleteDraftAllocations(
        CustomerReceipt $receipt,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation> $allocations
         */
        $allocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->customer_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'Only draft Customer Receipt allocations can be replaced or deleted.',
                );
            }

            $allocation->delete();
        }
    }

    private function ensureDraftAllocationState(
        CustomerReceipt $receipt,
    ): void {
        /**
         * @var Collection<int, CustomerReceiptAllocation> $allocations
         */
        $allocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->customer_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'The Customer Receipt already contains an accounting allocation.',
                );
            }
        }
    }

    private function resolveBranch(
        int $branchId,
        User $actor,
        bool $requireActive,
    ): Branch {
        $branch =
            $this->branchAccessService
                ->findAccessibleBranch(
                    user: $actor,
                    branchId: $branchId,
                    requireActive: $requireActive,
                );

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is unavailable or outside your access scope.',
                ],
            ]);
        }

        return $branch;
    }

    private function resolveCustomer(
        int $customerId,
        bool $requireActive,
    ): Customer {
        $customer =
            Customer::query()
                ->whereKey($customerId)
                ->lockForUpdate()
                ->first();

        if (!$customer instanceof Customer) {
            throw ValidationException::withMessages([
                'customer_id' => [
                    'The selected customer is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$customer->isActive()
        ) {
            throw ValidationException::withMessages([
                'customer_id' => [
                    'The selected customer is inactive.',
                ],
            ]);
        }

        return $customer;
    }

    private function resolveReceiptAccount(
        int $accountId,
        string $receiptMethod,
        bool $requireActive,
    ): Account {
        $account =
            Account::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'The selected cash or bank account is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$account->isActive()
        ) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'The selected cash or bank account is inactive.',
                ],
            ]);
        }

        if (!$account->isPostingAccount()) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    'A Customer Receipt requires a posting account, not a group account.',
                ],
            ]);
        }

        $controlType =
            $this->methodRegistry
                ->accountControlType(
                    $receiptMethod,
                );

        if (
            $account->account_type !== 'asset'
            || $account->account_subtype
                !== $controlType
            || $account->control_type
                !== $controlType
        ) {
            throw ValidationException::withMessages([
                'receipt_account_id' => [
                    "The selected method requires an active {$controlType} posting account.",
                ],
            ]);
        }

        return $account;
    }

    private function lockReceipt(
        CustomerReceipt $customerReceipt,
    ): CustomerReceipt {
        return CustomerReceipt::query()
            ->whereKey(
                $customerReceipt->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureTransition(
        CustomerReceipt $receipt,
        string $nextStatus,
    ): void {
        if (
            $this->statusRegistry
                ->canTransition(
                    $receipt->status,
                    $nextStatus,
                )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                "The Customer Receipt cannot move from {$receipt->status} to {$nextStatus}.",
            ],
        ]);
    }

    private function ensureNumberedIdentityUnchanged(
        CustomerReceipt $receipt,
        int $branchId,
        string $receiptDate,
    ): void {
        if (!$receipt->hasReceiptNumber()) {
            return;
        }

        if (
            (int) $receipt->branch_id
                !== $branchId
            || $receipt
                ->receipt_date
                ->toDateString()
                !== $receiptDate
        ) {
            throw ValidationException::withMessages([
                'receipt_date' => [
                    'The branch and receipt date cannot change after number allocation.',
                ],
            ]);
        }
    }

    private function authorizeReceiptBranch(
        User $actor,
        CustomerReceipt $receipt,
        bool $requireActive,
    ): void {
        $branch =
            Branch::query()
                ->whereKey(
                    $receipt->branch_id,
                )
                ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function loadReceipt(
        CustomerReceipt $receipt,
    ): CustomerReceipt {
        return $receipt->load([
            'branch',
            'customer',
            'receiptAccount',
            'documentNumberAllocation',
            'allocations.salesInvoice',
            'allocations.customerOpenItem',
            'allocations.customerOpenItemAllocation',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'postedBy',
            'reversedBy',
            'cancelledBy',
            'journalEntries.lines.account',
            'customerLedgerEntries.openItem',
            'customerOpenItems',
        ]);
    }

    private function requiredId(
        mixed $value,
        string $field,
        string $label,
    ): int {
        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($id === false) {
            throw ValidationException::withMessages([
                $field => [
                    "The selected {$label} is invalid.",
                ],
            ]);
        }

        return (int) $id;
    }

    private function normalizeCurrencyCode(
        mixed $value,
    ): string {
        $currencyCode =
            mb_strtoupper(
                trim((string) $value),
            );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency code must contain exactly three letters.',
                ],
            ]);
        }

        return $currencyCode;
    }

    private function normalizeDate(
        mixed $value,
        string $field,
        Tenant $tenant,
    ): string {
        if (
            $value === null
            || (
                !$value instanceof DateTimeInterface
                && trim((string) $value) === ''
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date is required.',
                ],
            ]);
        }

        try {
            return $value
                instanceof DateTimeInterface
                    ? CarbonImmutable::instance(
                        $value,
                    )
                        ->setTimezone(
                            $tenant->timezone,
                        )
                        ->toDateString()

                    : CarbonImmutable::parse(
                        (string) $value,
                        $tenant->timezone,
                    )->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must be valid.',
                ],
            ]);
        }
    }

    private function businessDateTime(
        string $date,
        Tenant $tenant,
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $date,
            $tenant->timezone,
        )->startOfDay();
    }

    private function positiveMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $decimal =
            $this->decimal(
                $value,
                self::MONEY_SCALE,
                $field,
                'amount',
            );

        if (!$decimal->isPositive()) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount must be greater than zero.',
                ],
            ]);
        }

        return $decimal;
    }

    private function nonNegativeMoney(
        mixed $value,
        string $field,
    ): BigDecimal {
        $decimal =
            $this->decimal(
                $value,
                self::MONEY_SCALE,
                $field,
                'amount',
            );

        if ($decimal->isNegative()) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount cannot be negative.',
                ],
            ]);
        }

        return $decimal;
    }

    private function positiveDecimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
    ): BigDecimal {
        $decimal =
            $this->decimal(
                $value,
                $scale,
                $field,
                $label,
            );

        if (!$decimal->isPositive()) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be greater than zero.",
                ],
            ]);
        }

        return $decimal;
    }

    private function decimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
    ): BigDecimal {
        try {
            $decimal =
                BigDecimal::of(
                    is_string($value)
                        ? trim($value)
                        : (string) $value,
                )
                    ->toScale(
                        $scale,
                        RoundingMode::UNNECESSARY,
                    );
        } catch (
            ArithmeticException
            | \InvalidArgumentException
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be valid with no more than {$scale} decimal places.",
                ],
            ]);
        }

        if (
            $scale === self::MONEY_SCALE
            && $decimal
                ->abs()
                ->isGreaterThan(
                    BigDecimal::of(
                        self::MAXIMUM_AMOUNT,
                    ),
                )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The amount exceeds the supported maximum.',
                ],
            ]);
        }

        return $decimal;
    }

    private function nullableString(
        mixed $value,
        string $field,
        int $maximumLength,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The value must be text.',
                ],
            ]);
        }

        $value = trim(
            (string) $value,
        );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maximumLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function requiredReason(
        string $reason,
        string $field,
    ): string {
        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'A reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return $reason;
    }

    private function requiredAccountingReference(
        string $reference,
    ): string {
        $reference = trim($reference);

        if (
            $reference === ''
            || mb_strlen($reference) > 190
        ) {
            throw new LogicException(
                'The accounting gateway returned an invalid reference.',
            );
        }

        return $reference;
    }

    private function numberAllocationKey(
        CustomerReceipt $receipt,
    ): string {
        return "customer-receipt:{$receipt->getKey()}";
    }

    private function activeTenantId(): int
    {
        return (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $actor->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The actor does not belong to the active tenant.',
            );
        }
    }

    private function ensureReceiptBelongsToTenant(
        CustomerReceipt $receipt,
        int $tenantId,
    ): void {
        if (
            (int) $receipt->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The Customer Receipt does not belong to the active tenant.',
            );
        }
    }
}