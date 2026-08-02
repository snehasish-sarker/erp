<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentSequences\IndexDocumentSequenceRequest;
use App\Http\Requests\DocumentSequences\StoreDocumentSequenceRequest;
use App\Http\Requests\DocumentSequences\UpdateDocumentSequenceRequest;
use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Services\Settings\DocumentNumberService;
use App\Services\Settings\DocumentSequenceService;
use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentSequenceController extends Controller
{
    public function __construct(
        private readonly DocumentSequenceService $sequenceService,
        private readonly DocumentNumberService $numberService,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexDocumentSequenceRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            DocumentSequence::class,
        );

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $scope = (string) ($validated['scope'] ?? '');

        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;

        $documentType = (string) (
            $validated['document_type'] ?? ''
        );

        $resetPolicy = (string) (
            $validated['reset_policy'] ?? ''
        );

        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');

        $direction = (string) (
            $validated['direction'] ?? 'asc'
        );

        $perPage = (int) ($validated['per_page'] ?? 25);

        $documentSequences = DocumentSequence::query()
            ->with('branch:id,name,code,status')
            ->withCount('allocations')
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'document_type',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'prefix',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'suffix',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
                                    'branch',
                                    static function (
                                        Builder $branchQuery,
                                    ) use ($search): void {
                                        $branchQuery
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    },
                                );
                        },
                    );
                },
            )
            ->when(
                $scope === 'company',
                static fn (Builder $query): Builder =>
                    $query->whereNull('branch_id'),
            )
            ->when(
                $scope === 'branch',
                static fn (Builder $query): Builder =>
                    $query->whereNotNull('branch_id'),
            )
            ->when(
                $branchId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('branch_id', $branchId),
            )
            ->when(
                $documentType !== '',
                static fn (Builder $query): Builder =>
                    $query->where(
                        'document_type',
                        $documentType,
                    ),
            )
            ->when(
                $resetPolicy !== '',
                static fn (Builder $query): Builder =>
                    $query->where(
                        'reset_policy',
                        $resetPolicy,
                    ),
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder =>
                    $query->where('status', $status),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'DocumentNumbering/Index',
            [
                'documentSequences' => [
                    'data' => $documentSequences
                        ->getCollection()
                        ->map(
                            fn (
                                DocumentSequence $sequence,
                            ): array => $this->sequenceData(
                                $sequence,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $documentSequences->currentPage(),

                        'last_page' =>
                            $documentSequences->lastPage(),

                        'per_page' =>
                            $documentSequences->perPage(),

                        'from' =>
                            $documentSequences->firstItem(),

                        'to' =>
                            $documentSequences->lastItem(),

                        'total' => $documentSequences->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'scope' => $scope,
                    'branch_id' => $branchId,
                    'document_type' => $documentType,
                    'reset_policy' => $resetPolicy,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'branchOptions' => $this->branchOptions(),

                'documentTypeOptions' =>
                    $this->documentTypeRegistry->options(),

                'resetPolicyOptions' =>
                    $this->resetPolicyOptions(),

                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            DocumentSequence::class,
        );

        return Inertia::render(
            'DocumentNumbering/Create',
            $this->formOptions(),
        );
    }

    public function store(
        StoreDocumentSequenceRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            DocumentSequence::class,
        );

        $documentSequence = $this->sequenceService->create(
            $request->validated(),
        );

        $documentSequence->load(
            'branch:id,name,code,status',
        );

        return $this->responseService->success(
            message: 'Document sequence created successfully.',
            data: $this->sequenceData($documentSequence),
            redirectTo: route('document-numbering.index'),
        );
    }

    public function edit(
        DocumentSequence $documentSequence,
    ): Response {
        Gate::authorize('update', $documentSequence);

        $documentSequence->load(
            'branch:id,name,code,status',
        );

        $documentSequence->loadCount('allocations');

        return Inertia::render(
            'DocumentNumbering/Edit',
            [
                ...$this->formOptions(),

                'documentSequence' =>
                    $this->sequenceData($documentSequence),
            ],
        );
    }

    public function update(
        UpdateDocumentSequenceRequest $request,
        DocumentSequence $documentSequence,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $documentSequence);

        $documentSequence = $this->sequenceService->update(
            documentSequence: $documentSequence,
            attributes: $request->validated(),
        );

        $documentSequence->load(
            'branch:id,name,code,status',
        );

        $documentSequence->loadCount('allocations');

        return $this->responseService->success(
            message: 'Document sequence updated successfully.',
            data: $this->sequenceData($documentSequence),
            redirectTo: route('document-numbering.index'),
        );
    }

    public function destroy(
        DocumentSequence $documentSequence,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $documentSequence);

        $this->sequenceService->delete(
            $documentSequence,
        );

        return $this->responseService->success(
            message: 'Document sequence deleted successfully.',
            redirectTo: route('document-numbering.index'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'branchOptions' => $this->branchOptions(),

            'documentTypeOptions' =>
                $this->documentTypeRegistry->options(),

            'resetPolicyOptions' =>
                $this->resetPolicyOptions(),

            'statusOptions' => $this->statusOptions(),

            'previewContext' =>
                $this->numberService->previewContext(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     branch_id: int|null,
     *     branch: array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         status: string
     *     }|null,
     *     name: string,
     *     document_type: string,
     *     document_type_label: string,
     *     prefix: string|null,
     *     suffix: string|null,
     *     current_number: int,
     *     number_padding: int,
     *     reset_policy: string,
     *     fiscal_year_start_month: int|null,
     *     last_reset_key: string|null,
     *     status: string,
     *     allocations_count: int,
     *     has_allocations: bool,
     *     preview: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function sequenceData(
        DocumentSequence $documentSequence,
    ): array {
        $branch = $documentSequence->branch;

        $allocationsCount = (int) (
            $documentSequence->allocations_count
            ?? $documentSequence->allocations()->count()
        );

        return [
            'id' => (int) $documentSequence->getKey(),
            'branch_id' => $documentSequence->branch_id,

            'branch' => $branch === null
                ? null
                : [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],

            'name' => $documentSequence->name,

            'document_type' =>
                $documentSequence->document_type,

            'document_type_label' =>
                $this->documentTypeRegistry->label(
                    $documentSequence->document_type,
                ),

            'prefix' => $documentSequence->prefix,
            'suffix' => $documentSequence->suffix,

            'current_number' => (int) (
                $documentSequence->current_number
            ),

            'number_padding' => (int) (
                $documentSequence->number_padding
            ),

            'reset_policy' =>
                $documentSequence->reset_policy,

            'fiscal_year_start_month' =>
                $documentSequence
                    ->fiscal_year_start_month,

            'last_reset_key' =>
                $documentSequence->last_reset_key,

            'status' => $documentSequence->status,
            'allocations_count' => $allocationsCount,
            'has_allocations' => $allocationsCount > 0,

            'preview' => $this->numberService->preview(
                $documentSequence,
            ),

            'created_at' =>
                $documentSequence->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $documentSequence->updated_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     status: string
     * }>
     */
    private function branchOptions(): array
    {
        return Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ])
            ->map(
                static fn (Branch $branch): array => [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function resetPolicyOptions(): array
    {
        return [
            [
                'value' => 'never',
                'label' => 'Never reset',
            ],
            [
                'value' => 'calendar_year',
                'label' => 'Reset each calendar year',
            ],
            [
                'value' => 'fiscal_year',
                'label' => 'Reset each fiscal year',
            ],
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function statusOptions(): array
    {
        return [
            [
                'value' => 'active',
                'label' => 'Active',
            ],
            [
                'value' => 'inactive',
                'label' => 'Inactive',
            ],
        ];
    }
}