<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\GenerateTenantSaasInvoiceRequest;
use App\Http\Requests\Platform\IndexSaasInvoiceRequest;
use App\Http\Requests\Platform\RecordSaasManualPaymentRequest;
use App\Models\PlatformAdmin;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\Tenant;
use App\Services\Saas\SaasBillingService;
use App\Services\Saas\SaasPaymentService;
use App\Support\Responses\CommonResponseService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSaasBillingController extends Controller
{
    public function __construct(
        private readonly SaasBillingService $billingService,
        private readonly SaasPaymentService $paymentService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(IndexSaasInvoiceRequest $request): Response
    {
        $this->platformAdmin();
        $validated = $request->validated();

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $sort = (string) $validated['sort'];
        $direction = (string) $validated['direction'];
        $perPage = (int) $validated['per_page'];

        $invoices = SaasInvoice::query()
            ->with(['tenant', 'plan'])
            ->when(
                $search !== null,
                static function (Builder $query) use ($search): Builder {
                    return $query->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('invoice_number', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'tenant',
                                    static function (Builder $tenantQuery) use ($search): void {
                                        $tenantQuery
                                            ->where('name', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    },
                                );
                        },
                    );
                },
            )
            ->when(
                $status !== null,
                static fn (Builder $query): Builder =>
                    $query->where('status', $status),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Platform/Billing/Invoices/Index',
            [
                'invoicePage' => [
                    'data' => $invoices->getCollection()
                        ->map(fn (SaasInvoice $invoice): array => $this->invoiceSummary($invoice))
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $invoices->currentPage(),
                        'last_page' => $invoices->lastPage(),
                        'per_page' => $invoices->perPage(),
                        'from' => $invoices->firstItem(),
                        'to' => $invoices->lastItem(),
                        'total' => $invoices->total(),
                        'previous_page_url' => $invoices->previousPageUrl(),
                        'next_page_url' => $invoices->nextPageUrl(),
                    ],
                ],
                'filters' => [
                    'search' => $search ?? '',
                    'status' => $status ?? '',
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
            ],
        );
    }

    public function show(SaasInvoice $saasInvoice): Response
    {
        $this->platformAdmin();

        $saasInvoice->load([
            'tenant',
            'plan',
            'subscription',
            'lines',
            'payments.recordedBy',
        ]);

        return Inertia::render(
            'Platform/Billing/Invoices/Show',
            ['invoice' => $this->invoiceDetails($saasInvoice)],
        );
    }

    public function generate(
        GenerateTenantSaasInvoiceRequest $request,
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        $admin = $this->platformAdmin();

        try {
            $invoice = $this->billingService->generateInvoice(
                tenant: $tenant,
                createdBy: $admin,
            );
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'SAAS_INVOICE_GENERATION_INVALID',
                redirectTo: route('platform.tenants.show', $tenant),
            );
        }

        return $this->responseService->success(
            message: 'SaaS invoice generated successfully.',
            data: ['invoice_id' => (int) $invoice->getKey()],
            redirectTo: route('platform.billing.invoices.show', $invoice),
            status: 201,
        );
    }

    public function recordManualPayment(
        RecordSaasManualPaymentRequest $request,
        SaasInvoice $saasInvoice,
    ): JsonResponse|RedirectResponse {
        $admin = $this->platformAdmin();
        $validated = $request->validated();

        $amountMinor = $this->toMinorUnits(
            value: (string) $validated['amount'],
            scale: (int) $saasInvoice->currency_scale,
        );

        try {
            $this->paymentService->recordSucceededPayment(
                invoice: $saasInvoice,
                amountMinor: $amountMinor,
                provider: 'manual',
                providerPaymentId: $validated['reference'] ?? null,
                recordedBy: $admin,
                metadata: [
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'SAAS_PAYMENT_INVALID',
                redirectTo: route('platform.billing.invoices.show', $saasInvoice),
            );
        }

        return $this->responseService->success(
            message: 'Manual payment recorded successfully.',
            redirectTo: route('platform.billing.invoices.show', $saasInvoice),
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function invoiceSummary(SaasInvoice $invoice): array
    {
        return [
            'id' => (int) $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'billing_cycle' => $invoice->billing_cycle,
            'currency_code' => $invoice->currency_code,
            'currency_scale' => (int) $invoice->currency_scale,
            'total_minor' => (int) $invoice->total_minor,
            'amount_paid_minor' => (int) $invoice->amount_paid_minor,
            'balance_due_minor' => (int) $invoice->balance_due_minor,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'tenant' => [
                'id' => (int) $invoice->tenant->getKey(),
                'name' => $invoice->tenant->name,
                'code' => $invoice->tenant->code,
            ],
            'plan' => [
                'id' => (int) $invoice->plan->getKey(),
                'name' => $invoice->plan->name,
                'code' => $invoice->plan->code,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function invoiceDetails(SaasInvoice $invoice): array
    {
        return [
            ...$this->invoiceSummary($invoice),
            'period_starts_at' => $invoice->period_starts_at?->toIso8601String(),
            'period_ends_at' => $invoice->period_ends_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'subtotal_minor' => (int) $invoice->subtotal_minor,
            'discount_minor' => (int) $invoice->discount_minor,
            'tax_minor' => (int) $invoice->tax_minor,
            'notes' => $invoice->notes,
            'lines' => $invoice->lines
                ->map(static fn ($line): array => [
                    'id' => (int) $line->getKey(),
                    'description' => $line->description,
                    'quantity' => (int) $line->quantity,
                    'unit_amount_minor' => (int) $line->unit_amount_minor,
                    'line_total_minor' => (int) $line->line_total_minor,
                ])
                ->values()
                ->all(),
            'payments' => $invoice->payments
                ->sortByDesc('id')
                ->map(static fn (SaasPayment $payment): array => [
                    'id' => (int) $payment->getKey(),
                    'provider' => $payment->provider,
                    'provider_payment_id' => $payment->provider_payment_id,
                    'status' => $payment->status,
                    'amount_minor' => (int) $payment->amount_minor,
                    'currency_code' => $payment->currency_code,
                    'currency_scale' => (int) $payment->currency_scale,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'recorded_by' => $payment->recordedBy === null
                        ? null
                        : [
                            'id' => (int) $payment->recordedBy->getKey(),
                            'name' => $payment->recordedBy->name,
                        ],
                ])
                ->values()
                ->all(),
        ];
    }

    private function toMinorUnits(string $value, int $scale): int
    {
        return (int) round((float) $value * (10 ** $scale));
    }
}
