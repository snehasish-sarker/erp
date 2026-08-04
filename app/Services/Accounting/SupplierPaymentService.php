<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierPaymentAccountingGateway;
use App\Events\Accounting\SupplierPaymentPosted;
use App\Events\Accounting\SupplierPaymentReversed;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOpenItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\SupplierPaymentMethodRegistry;
use App\Support\Accounting\SupplierPaymentStatusRegistry;
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

final class SupplierPaymentService
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
        private readonly SupplierPaymentStatusRegistry $statusRegistry,
        private readonly SupplierPaymentMethodRegistry $methodRegistry,
        private readonly SupplierPaymentAccountingGateway $accountingGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): SupplierPayment {
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
            ): SupplierPayment {
                $branch = $this->resolveBranch(
                    $normalized['branch_id'],
                    $actor,
                    true,
                );

                $supplier = $this->resolveSupplier(
                    $normalized['supplier_id'],
                    true,
                );

                $account = $this->resolvePaymentAccount(
                    $normalized['payment_account_id'],
                    $normalized['payment_method'],
                    true,
                );

                $allocations = $this->buildAllocations(
                    $normalized['allocations'],
                    $branch,
                    $supplier,
                    $normalized['currency_code'],
                    $normalized['exchange_rate'],
                    $tenantId,
                );

                $totals = $this->allocationTotals(
                    $normalized['total_amount'],
                    $allocations,
                );

                $payment = SupplierPayment::query()->create([
                    'branch_id' => $branch->getKey(),
                    'supplier_id' => $supplier->getKey(),
                    'payment_account_id' => $account->getKey(),
                    'document_number_allocation_id' => null,
                    'payment_number' => null,
                    'payment_date' =>
                        $normalized['payment_date'],
                    'posting_date' =>
                        $normalized['posting_date'],
                    'currency_code' =>
                        $normalized['currency_code'],
                    'exchange_rate' =>
                        $normalized['exchange_rate'],
                    'payment_method' =>
                        $normalized['payment_method'],
                    'payment_reference' =>
                        $normalized['payment_reference'],
                    'cheque_number' =>
                        $normalized['cheque_number'],
                    'cheque_date' =>
                        $normalized['cheque_date'],
                    'supplier_name' => $supplier->name,
                    'supplier_code' => $supplier->code,
                    'payment_account_code' => $account->code,
                    'payment_account_name' => $account->name,
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
                    $payment,
                    $allocations,
                );

                return $this->loadPayment(
                    $payment,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        SupplierPayment $supplierPayment,
        array $data,
        User $actor,
    ): SupplierPayment {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        $normalized = $this->normalizeInput(
            $data,
            $tenant,
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $normalized,
                $actor,
                $tenantId,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    false,
                );

                if (
                    !$this->statusRegistry->isEditable(
                        $payment->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only a draft Supplier Payment can be edited.',
                        ],
                    ]);
                }

                $branch = $this->resolveBranch(
                    $normalized['branch_id'],
                    $actor,
                    true,
                );

                $this->ensureNumberedIdentityUnchanged(
                    $payment,
                    (int) $branch->getKey(),
                    $normalized['payment_date'],
                );

                $supplier = $this->resolveSupplier(
                    $normalized['supplier_id'],
                    true,
                );

                $account = $this->resolvePaymentAccount(
                    $normalized['payment_account_id'],
                    $normalized['payment_method'],
                    true,
                );

                $allocations = $this->buildAllocations(
                    $normalized['allocations'],
                    $branch,
                    $supplier,
                    $normalized['currency_code'],
                    $normalized['exchange_rate'],
                    $tenantId,
                );

                $totals = $this->allocationTotals(
                    $normalized['total_amount'],
                    $allocations,
                );

                $this->deleteDraftAllocations(
                    $payment,
                );

                $payment->fill([
                    'branch_id' => $branch->getKey(),
                    'supplier_id' => $supplier->getKey(),
                    'payment_account_id' =>
                        $account->getKey(),
                    'payment_date' =>
                        $normalized['payment_date'],
                    'posting_date' =>
                        $normalized['posting_date'],
                    'currency_code' =>
                        $normalized['currency_code'],
                    'exchange_rate' =>
                        $normalized['exchange_rate'],
                    'payment_method' =>
                        $normalized['payment_method'],
                    'payment_reference' =>
                        $normalized['payment_reference'],
                    'cheque_number' =>
                        $normalized['cheque_number'],
                    'cheque_date' =>
                        $normalized['cheque_date'],
                    'supplier_name' => $supplier->name,
                    'supplier_code' => $supplier->code,
                    'payment_account_code' => $account->code,
                    'payment_account_name' => $account->name,
                    ...$totals,
                    'base_total_amount' => '0.000000',
                    'base_allocated_amount' => '0.000000',
                    'base_unallocated_amount' => '0.000000',
                    'notes' => $normalized['notes'],
                    'revision' =>
                        (int) $payment->revision + 1,
                ]);

                $payment->save();

                $this->createDraftAllocations(
                    $payment,
                    $allocations,
                );

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        SupplierPayment $supplierPayment,
        User $actor,
    ): void {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        DB::transaction(
            function () use (
                $supplierPayment,
                $actor,
            ): void {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    false,
                );

                if (!$payment->canBeDeleted()) {
                    throw ValidationException::withMessages([
                        'supplier_payment' => [
                            'Only an unnumbered, never-submitted draft Supplier Payment can be permanently deleted.',
                        ],
                    ]);
                }

                $this->deleteDraftAllocations(
                    $payment,
                );

                $payment->forceDelete();
            },
            attempts: 5,
        );
    }

    public function submit(
        SupplierPayment $supplierPayment,
        User $actor,
    ): SupplierPayment {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    true,
                );

                $this->ensureTransition(
                    $payment,
                    'submitted',
                );

                $this->validateCurrentPayment(
                    $payment,
                    $actor,
                    $tenantId,
                    true,
                    true,
                );

                if (!$payment->hasPaymentNumber()) {
                    $allocation =
                        $this->documentNumberService->allocate(
                            documentType:
                                'supplier_payment',

                            branchId:
                                (int) $payment->branch_id,

                            idempotencyKey:
                                $this->numberAllocationKey(
                                    $payment,
                                ),

                            allocatableType:
                                SupplierPayment::class,

                            allocatableId:
                                (int) $payment->getKey(),

                            allocatedAt:
                                $this->businessDateTime(
                                    $payment
                                        ->payment_date
                                        ->toDateString(),

                                    $tenant,
                                ),
                        );

                    $payment
                        ->document_number_allocation_id =
                            $allocation->getKey();

                    $payment->payment_number =
                        $allocation->number;
                }

                $payment->status = 'submitted';

                $payment->submitted_by_user_id =
                    $actor->getKey();

                $payment->submitted_at =
                    CarbonImmutable::now('UTC');

                $payment->save();

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        SupplierPayment $supplierPayment,
        User $actor,
    ): SupplierPayment {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $actor,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    false,
                );

                $this->ensureTransition(
                    $payment,
                    'draft',
                );

                $this->ensureDraftAllocationState(
                    $payment,
                );

                $payment->status = 'draft';
                $payment->submitted_by_user_id = null;
                $payment->submitted_at = null;

                $payment->revision =
                    (int) $payment->revision + 1;

                $payment->save();

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function approve(
        SupplierPayment $supplierPayment,
        User $actor,
    ): SupplierPayment {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $actor,
                $tenantId,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    true,
                );

                $this->ensureTransition(
                    $payment,
                    'approved',
                );

                if (!$payment->hasPaymentNumber()) {
                    throw new LogicException(
                        'A submitted Supplier Payment must retain its payment number before approval.',
                    );
                }

                $this->validateCurrentPayment(
                    $payment,
                    $actor,
                    $tenantId,
                    true,
                    true,
                );

                $payment->status = 'approved';

                $payment->approved_by_user_id =
                    $actor->getKey();

                $payment->approved_at =
                    CarbonImmutable::now('UTC');

                $payment->save();

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        SupplierPayment $supplierPayment,
        string $reason,
        User $actor,
    ): SupplierPayment {
        $tenantId = $this->activeTenantId();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        $reason = $this->requiredReason(
            $reason,
            'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $reason,
                $actor,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    false,
                );

                $this->ensureTransition(
                    $payment,
                    'cancelled',
                );

                $this->ensureDraftAllocationState(
                    $payment,
                );

                SupplierPaymentAllocation::query()
                    ->where(
                        'supplier_payment_id',
                        $payment->getKey(),
                    )
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' =>
                            CarbonImmutable::now('UTC'),
                    ]);

                $payment->status = 'cancelled';

                $payment->cancelled_by_user_id =
                    $actor->getKey();

                $payment->cancelled_at =
                    CarbonImmutable::now('UTC');

                $payment->cancellation_reason =
                    $reason;

                $payment->save();

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        SupplierPayment $supplierPayment,
        User $actor,
    ): SupplierPayment {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
            $tenantId,
        );

        return DB::transaction(
            function () use (
                $supplierPayment,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    true,
                );

                $this->ensureTransition(
                    $payment,
                    'posted',
                );

                if (!$payment->hasPaymentNumber()) {
                    throw new LogicException(
                        'The approved Supplier Payment does not retain its payment number.',
                    );
                }

                $this->validateCurrentPayment(
                    $payment,
                    $actor,
                    $tenantId,
                    false,
                    true,
                );

                $postingDate =
                    $this->businessDateTime(
                        $payment
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
                 * - Supplier ledger payment entry
                 * - Supplier payment credit open item
                 * - Invoice open-item allocations
                 * - Realized exchange gain or loss, when applicable
                 *
                 * The current provider binding intentionally throws until
                 * the real gateway is implemented.
                 */
                $reference =
                    $this->accountingGateway->post(
                        supplierPayment:
                            $payment,

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
                    $payment,
                );

                $payment->status = 'posted';

                $payment->posted_by_user_id =
                    $actor->getKey();

                $payment->posted_at =
                    CarbonImmutable::now('UTC');

                $payment->accounting_posting_reference =
                    $reference;

                $payment->save();

                SupplierPaymentPosted::dispatch(
                    tenantId: $tenantId,

                    supplierPaymentId:
                        (int) $payment->getKey(),

                    supplierId:
                        (int) $payment->supplier_id,

                    branchId:
                        (int) $payment->branch_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadPayment(
                    $payment->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        SupplierPayment $supplierPayment,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): SupplierPayment {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            $actor,
            $tenantId,
        );

        $this->ensurePaymentBelongsToTenant(
            $supplierPayment,
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
                $supplierPayment,
                $reversalDateString,
                $reason,
                $actor,
                $tenant,
                $tenantId,
            ): SupplierPayment {
                $payment = $this->lockPayment(
                    $supplierPayment,
                );

                $this->authorizePaymentBranch(
                    $actor,
                    $payment,
                    false,
                );

                $this->ensureTransition(
                    $payment,
                    'reversed',
                );

                if (
                    $reversalDateString
                    < $payment
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
                    $payment
                        ->accounting_posting_reference
                    === null
                    || trim(
                        $payment
                            ->accounting_posting_reference,
                    ) === ''
                ) {
                    throw new LogicException(
                        'The posted Supplier Payment does not retain its accounting posting reference.',
                    );
                }

                $this->validatePostedAllocationState(
                    $payment,
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
                        supplierPayment:
                            $payment,

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
                    $payment,
                );

                $payment->status = 'reversed';

                $payment->reversal_posting_date =
                    $reversalDateString;

                $payment->reversed_by_user_id =
                    $actor->getKey();

                $payment->reversed_at =
                    CarbonImmutable::now('UTC');

                $payment->reversal_reason =
                    $reason;

                $payment->accounting_reversal_reference =
                    $reference;

                $payment->save();

                SupplierPaymentReversed::dispatch(
                    tenantId: $tenantId,

                    supplierPaymentId:
                        (int) $payment->getKey(),

                    supplierId:
                        (int) $payment->supplier_id,

                    branchId:
                        (int) $payment->branch_id,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadPayment(
                    $payment->refresh(),
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
     *     supplier_id: int,
     *     payment_account_id: int,
     *     payment_date: string,
     *     posting_date: string,
     *     currency_code: string,
     *     exchange_rate: string,
     *     payment_method: string,
     *     payment_reference: string|null,
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
        $paymentDate =
            $this->normalizeDate(
                $data['payment_date'] ?? null,
                'payment_date',
                $tenant,
            );

        $postingDate =
            $this->normalizeDate(
                $data['posting_date'] ?? null,
                'posting_date',
                $tenant,
            );

        if ($postingDate < $paymentDate) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date cannot be before the payment date.',
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
                $data['payment_method']
                ?? ''
            ),
        );

        if (
            !$this->methodRegistry->exists(
                $method,
            )
        ) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'The selected Supplier Payment method is invalid.',
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
                        'A cheque number is required for cheque payments.',
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
                    'Supplier Payment allocations must be an array.',
                ],
            ]);
        }

        if (
            count($allocations)
            > self::MAXIMUM_ALLOCATIONS
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'A Supplier Payment cannot contain more than 500 invoice allocations.',
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

            'supplier_id' =>
                $this->requiredId(
                    $data['supplier_id'] ?? null,
                    'supplier_id',
                    'supplier',
                ),

            'payment_account_id' =>
                $this->requiredId(
                    $data['payment_account_id'] ?? null,
                    'payment_account_id',
                    'payment account',
                ),

            'payment_date' =>
                $paymentDate,

            'posting_date' =>
                $postingDate,

            'currency_code' =>
                $currencyCode,

            'exchange_rate' =>
                $exchangeRate->__toString(),

            'payment_method' =>
                $method,

            'payment_reference' =>
                $this->nullableString(
                    $data['payment_reference']
                        ?? null,

                    'payment_reference',
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
     *     supplier_open_item_id: int,
     *     supplier_invoice_id: int,
     *     invoice_document_number: string|null,
     *     invoice_due_date: string|null,
     *     currency_code: string,
     *     invoice_exchange_rate: string,
     *     payment_exchange_rate: string,
     *     amount: string
     * }>
     */
    private function buildAllocations(
        array $inputAllocations,
        Branch $branch,
        Supplier $supplier,
        string $currencyCode,
        string $paymentExchangeRate,
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
                        'Each Supplier Payment allocation must be an object.',
                    ],
                ]);
            }

            $openItemId =
                $this->requiredId(
                    $allocation[
                        'supplier_open_item_id'
                    ] ?? null,

                    "allocations.{$index}.supplier_open_item_id",

                    'supplier invoice open item',
                );

            if (isset($inputById[$openItemId])) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_open_item_id" => [
                        'The same Supplier Invoice open item cannot be allocated more than once.',
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
         * @var Collection<int, SupplierOpenItem> $openItems
         */
        $openItems =
            SupplierOpenItem::query()
                ->whereIn(
                    'id',
                    $openItemIds,
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SupplierOpenItem $item,
                    ): int => (int) $item->getKey(),
                );

        if (
            $openItems->count()
            !== count($openItemIds)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more selected Supplier Invoice open items are unavailable.',
                ],
            ]);
        }

        $invoiceMorphClass =
            (new SupplierInvoice())
                ->getMorphClass();

        $invoiceIds = [];

        foreach ($openItemIds as $openItemId) {
            $openItem =
                $openItems->get(
                    $openItemId,
                );

            if (
                !$openItem
                instanceof SupplierOpenItem
            ) {
                throw new LogicException(
                    'A selected Supplier Invoice open item could not be locked.',
                );
            }

            $input =
                $inputById[$openItemId];

            $this->validatePayableOpenItem(
                $openItem,
                $input['amount'],
                (int) $branch->getKey(),
                (int) $supplier->getKey(),
                $currencyCode,
                $tenantId,
                $invoiceMorphClass,
                "allocations.{$input['index']}.supplier_open_item_id",
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
         * @var Collection<int, SupplierInvoice> $invoices
         */
        $invoices =
            SupplierInvoice::query()
                ->whereIn(
                    'id',
                    $invoiceIds,
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SupplierInvoice $invoice,
                    ): int => (int) $invoice
                        ->getKey(),
                );

        if (
            $invoices->count()
            !== count($invoiceIds)
        ) {
            throw ValidationException::withMessages([
                'allocations' => [
                    'One or more Supplier Invoices linked to the selected open items are unavailable.',
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
                    'supplier_open_item_id'
                ];

            $openItem =
                $openItems->get(
                    $openItemId,
                );

            if (
                !$openItem
                instanceof SupplierOpenItem
            ) {
                throw new LogicException(
                    'A selected Supplier Invoice open item is unavailable.',
                );
            }

            $invoice =
                $invoices->get(
                    (int) $openItem->source_id,
                );

            if (
                !$invoice
                    instanceof SupplierInvoice
                || !$invoice->isPosted()
                || (int) $invoice->tenant_id
                    !== $tenantId
                || (int) $invoice->branch_id
                    !== (int) $branch->getKey()
                || (int) $invoice->supplier_id
                    !== (int) $supplier->getKey()
                || mb_strtoupper(
                    $invoice->currency_code,
                ) !== $currencyCode
            ) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_open_item_id" => [
                        'The selected open item must belong to a posted Supplier Invoice with the same tenant, branch, supplier, and currency as the payment.',
                    ],
                ]);
            }

            $built[] = [
                'supplier_open_item_id' =>
                    $openItemId,

                'supplier_invoice_id' =>
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

                'payment_exchange_rate' =>
                    $paymentExchangeRate,

                'amount' =>
                    $inputById[
                        $openItemId
                    ]['amount']
                        ->__toString(),
            ];
        }

        return $built;
    }

    private function validatePayableOpenItem(
        SupplierOpenItem $openItem,
        BigDecimal $amount,
        int $branchId,
        int $supplierId,
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
            || (int) $openItem->supplier_id
                !== $supplierId
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected open item is outside the payment tenant, branch, or supplier context.',
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
                    'The selected open item must be a posted Supplier Invoice payable.',
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
                    'The selected Supplier Invoice open item is not available for payment.',
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
                    'The selected open item currency does not match the payment currency.',
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
                    'The total Supplier Invoice allocations cannot exceed the Supplier Payment amount.',
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

    private function validateCurrentPayment(
        SupplierPayment $payment,
        User $actor,
        int $tenantId,
        bool $requireActiveSupplier,
        bool $requireActiveAccount,
    ): void {
        $branch =
            $this->resolveBranch(
                (int) $payment->branch_id,
                $actor,
                true,
            );

        $supplier =
            $this->resolveSupplier(
                (int) $payment->supplier_id,
                $requireActiveSupplier,
            );

        $this->resolvePaymentAccount(
            (int) $payment->payment_account_id,
            $payment->payment_method,
            $requireActiveAccount,
        );

        if (
            $payment
                ->posting_date
                ->toDateString()
            < $payment
                ->payment_date
                ->toDateString()
        ) {
            throw new LogicException(
                'The Supplier Payment posting date cannot be before its payment date.',
            );
        }

        $currencyCode =
            $this->normalizeCurrencyCode(
                $payment->currency_code,
            );

        $exchangeRate =
            $this->positiveDecimal(
                $payment->exchange_rate,
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
                'The base-currency Supplier Payment exchange rate must be 1.00000000.',
            );
        }

        if (
            $this->methodRegistry
                ->requiresChequeDetails(
                    $payment->payment_method,
                )
            && (
                $payment->cheque_number === null
                || trim(
                    $payment->cheque_number,
                ) === ''
                || $payment->cheque_date === null
            )
        ) {
            throw new LogicException(
                'The cheque Supplier Payment does not retain its cheque details.',
            );
        }

        $total =
            $this->positiveMoney(
                $payment->total_amount,
                'total_amount',
            );

        $storedAllocated =
            $this->nonNegativeMoney(
                $payment->allocated_amount,
                'allocated_amount',
            );

        $storedUnallocated =
            $this->nonNegativeMoney(
                $payment->unallocated_amount,
                'unallocated_amount',
            );

        if (
            !$storedAllocated
                ->plus($storedUnallocated)
                ->isEqualTo($total)
        ) {
            throw new LogicException(
                'The Supplier Payment allocation totals do not equal its total amount.',
            );
        }

        /**
         * @var Collection<int, SupplierPaymentAllocation> $intent
         */
        $intent =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        $input = [];

        foreach ($intent as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->supplier_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'An unposted Supplier Payment contains a non-draft allocation state.',
                );
            }

            $input[] = [
                'supplier_open_item_id' =>
                    $allocation
                        ->supplier_open_item_id,

                'amount' =>
                    $allocation->amount,
            ];
        }

        $rebuilt =
            $this->buildAllocations(
                $input,
                $branch,
                $supplier,
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
                'The Supplier Payment allocation intent no longer matches its stored totals.',
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
                    ->supplier_invoice_id
                    !== $rebuiltAllocation[
                        'supplier_invoice_id'
                    ]
                || $allocation->currency_code
                    !== $rebuiltAllocation[
                        'currency_code'
                    ]
                || (string) $allocation->amount
                    !== $rebuiltAllocation['amount']
            ) {
                throw new LogicException(
                    'A Supplier Payment allocation snapshot no longer matches its payable open item.',
                );
            }
        }
    }

    private function validateGatewayPostingResult(
        SupplierPayment $payment,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation> $allocations
         */
        $allocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isApplied()
                || $allocation
                    ->supplier_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The accounting gateway did not apply every payment allocation atomically.',
                );
            }
        }

        $openItem =
            $this->lockPaymentOpenItem(
                $payment,
            );

        if (
            (string) $openItem->original_amount
                !== (string) $payment->total_amount
            || (string) $openItem->allocated_amount
                !== (string) $payment->allocated_amount
            || (string) $openItem->outstanding_amount
                !== (string) $payment->unallocated_amount
        ) {
            throw new LogicException(
                'The supplier payment open item does not match the payment totals.',
            );
        }
    }

    private function validatePostedAllocationState(
        SupplierPayment $payment,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation> $allocations
         */
        $allocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isApplied()
                || $allocation
                    ->supplier_open_item_allocation_id
                    === null
            ) {
                throw new LogicException(
                    'The posted Supplier Payment contains an incomplete allocation state.',
                );
            }
        }

        $this->lockPaymentOpenItem(
            $payment,
        );
    }

    private function validateGatewayReversalResult(
        SupplierPayment $payment,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation> $allocations
         */
        $allocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (!$allocation->isReversed()) {
                throw new LogicException(
                    'The accounting gateway did not reverse every payment allocation atomically.',
                );
            }
        }

        if (
            !$this->lockPaymentOpenItem(
                $payment,
            )->isReversed()
        ) {
            throw new LogicException(
                'The accounting gateway did not reverse the supplier payment open item.',
            );
        }
    }

    private function lockPaymentOpenItem(
        SupplierPayment $payment,
    ): SupplierOpenItem {
        $openItem =
            SupplierOpenItem::query()
                ->where(
                    'source_type',
                    $payment->getMorphClass(),
                )
                ->where(
                    'source_id',
                    $payment->getKey(),
                )
                ->where(
                    'item_type',
                    'payment',
                )
                ->lockForUpdate()
                ->first();

        if (
            !$openItem
            instanceof SupplierOpenItem
        ) {
            throw new LogicException(
                'The required supplier payment open item is missing.',
            );
        }

        return $openItem;
    }

    /**
     * @param list<array{
     *     supplier_open_item_id: int,
     *     supplier_invoice_id: int,
     *     invoice_document_number: string|null,
     *     invoice_due_date: string|null,
     *     currency_code: string,
     *     invoice_exchange_rate: string,
     *     payment_exchange_rate: string,
     *     amount: string
     * }> $allocations
     */
    private function createDraftAllocations(
        SupplierPayment $payment,
        array $allocations,
    ): void {
        foreach (
            $allocations
            as $index => $allocation
        ) {
            SupplierPaymentAllocation::query()
                ->create([
                    'supplier_payment_id' =>
                        $payment->getKey(),

                    'supplier_open_item_id' =>
                        $allocation[
                            'supplier_open_item_id'
                        ],

                    'supplier_invoice_id' =>
                        $allocation[
                            'supplier_invoice_id'
                        ],

                    'supplier_open_item_allocation_id' =>
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

                    'payment_exchange_rate' =>
                        $allocation[
                            'payment_exchange_rate'
                        ],

                    'amount' =>
                        $allocation['amount'],

                    'payable_base_amount' =>
                        '0.000000',

                    'credit_base_amount' =>
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
        SupplierPayment $payment,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation> $allocations
         */
        $allocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->supplier_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'Only draft Supplier Payment allocations can be replaced or deleted.',
                );
            }

            $allocation->delete();
        }
    }

    private function ensureDraftAllocationState(
        SupplierPayment $payment,
    ): void {
        /**
         * @var Collection<int, SupplierPaymentAllocation> $allocations
         */
        $allocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        foreach ($allocations as $allocation) {
            if (
                !$allocation->isDraft()
                || $allocation
                    ->supplier_open_item_allocation_id
                    !== null
            ) {
                throw new LogicException(
                    'The Supplier Payment already contains an accounting allocation.',
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

    private function resolveSupplier(
        int $supplierId,
        bool $requireActive,
    ): Supplier {
        $supplier =
            Supplier::query()
                ->whereKey($supplierId)
                ->lockForUpdate()
                ->first();

        if (!$supplier instanceof Supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The selected supplier is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$supplier->isActive()
        ) {
            throw ValidationException::withMessages([
                'supplier_id' => [
                    'The selected supplier is inactive.',
                ],
            ]);
        }

        return $supplier;
    }

    private function resolvePaymentAccount(
        int $accountId,
        string $paymentMethod,
        bool $requireActive,
    ): Account {
        $account =
            Account::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'The selected cash or bank account is unavailable.',
                ],
            ]);
        }

        if (
            $requireActive
            && !$account->isActive()
        ) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'The selected cash or bank account is inactive.',
                ],
            ]);
        }

        if (!$account->isPostingAccount()) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    'A Supplier Payment requires a posting account, not a group account.',
                ],
            ]);
        }

        $controlType =
            $this->methodRegistry
                ->accountControlType(
                    $paymentMethod,
                );

        if (
            $account->account_type !== 'asset'
            || $account->account_subtype
                !== $controlType
            || $account->control_type
                !== $controlType
        ) {
            throw ValidationException::withMessages([
                'payment_account_id' => [
                    "The selected method requires an active {$controlType} posting account.",
                ],
            ]);
        }

        return $account;
    }

    private function lockPayment(
        SupplierPayment $supplierPayment,
    ): SupplierPayment {
        return SupplierPayment::query()
            ->whereKey(
                $supplierPayment->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureTransition(
        SupplierPayment $payment,
        string $nextStatus,
    ): void {
        if (
            $this->statusRegistry
                ->canTransition(
                    $payment->status,
                    $nextStatus,
                )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                "The Supplier Payment cannot move from {$payment->status} to {$nextStatus}.",
            ],
        ]);
    }

    private function ensureNumberedIdentityUnchanged(
        SupplierPayment $payment,
        int $branchId,
        string $paymentDate,
    ): void {
        if (!$payment->hasPaymentNumber()) {
            return;
        }

        if (
            (int) $payment->branch_id
                !== $branchId
            || $payment
                ->payment_date
                ->toDateString()
                !== $paymentDate
        ) {
            throw ValidationException::withMessages([
                'payment_date' => [
                    'The branch and payment date cannot change after number allocation.',
                ],
            ]);
        }
    }

    private function authorizePaymentBranch(
        User $actor,
        SupplierPayment $payment,
        bool $requireActive,
    ): void {
        $branch =
            Branch::query()
                ->whereKey(
                    $payment->branch_id,
                )
                ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function loadPayment(
        SupplierPayment $payment,
    ): SupplierPayment {
        return $payment->load([
            'branch',
            'supplier',
            'paymentAccount',
            'documentNumberAllocation',
            'allocations.supplierInvoice',
            'allocations.supplierOpenItem',
            'allocations.supplierOpenItemAllocation',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'postedBy',
            'reversedBy',
            'cancelledBy',
            'journalEntries.lines.account',
            'supplierLedgerEntries.openItem',
            'supplierOpenItems',
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
        SupplierPayment $payment,
    ): string {
        return "supplier-payment:{$payment->getKey()}";
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

    private function ensurePaymentBelongsToTenant(
        SupplierPayment $payment,
        int $tenantId,
    ): void {
        if (
            (int) $payment->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The Supplier Payment does not belong to the active tenant.',
            );
        }
    }
}