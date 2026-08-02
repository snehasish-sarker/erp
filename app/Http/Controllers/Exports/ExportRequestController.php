<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exports\CancelExportRequest;
use App\Http\Requests\Exports\IndexExportRequest;
use App\Http\Requests\Exports\StoreExportRequest;
use App\Models\ExportRequest;
use App\Models\TenantFile;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\Files\TenantFileStorageService;
use App\Support\Exports\ExportRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportRequestController extends Controller
{
    public function __construct(
        private readonly ExportRequestService $exportRequestService,
        private readonly ExportRegistry $exportRegistry,
        private readonly TenantFileStorageService $fileStorageService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexExportRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            ExportRequest::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $exportType = (string) (
            $validated['export_type'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'created_at'
        );

        $direction = (string) (
            $validated['direction'] ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $exportRequests = ExportRequest::query()
            ->with([
                'requester:id,name,email',
                'file:id,status,deleted_at',
            ])
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
                                    'request_key',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
                                    'requester',
                                    static function (
                                        Builder $requesterQuery,
                                    ) use ($search): void {
                                        $requesterQuery
                                            ->withTrashed()
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'email',
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
                $exportType !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'export_type',
                    $exportType,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        return Inertia::render(
            'Exports/Index',
            [
                'exportRequests' => [
                    'data' => $exportRequests
                        ->getCollection()
                        ->map(
                            fn (
                                ExportRequest $exportRequest,
                            ): array => $this->exportData(
                                $exportRequest,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $exportRequests->currentPage(),

                        'last_page' =>
                            $exportRequests->lastPage(),

                        'per_page' =>
                            $exportRequests->perPage(),

                        'from' =>
                            $exportRequests->firstItem(),

                        'to' =>
                            $exportRequests->lastItem(),

                        'total' =>
                            $exportRequests->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'export_type' => $exportType,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'exportOptions' =>
                    $this->exportRegistry
                        ->optionsFor($actor),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreExportRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            ExportRequest::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $validated = $request->validated();

        $exportRequest =
            $this->exportRequestService->request(
                requester: $actor,
                exportType: $validated['export_type'],
                filters: is_array(
                    $validated['filters'] ?? null,
                )
                    ? $validated['filters']
                    : [],
                format: $validated['format'],
            );

        return $this->responseService->success(
            message: 'Export request queued successfully.',

            data: [
                'id' => (int) $exportRequest->getKey(),
                'status' => $exportRequest->status,
            ],

            redirectTo: route('exports.index'),
        );
    }

    public function download(
        ExportRequest $exportRequest,
    ): StreamedResponse {
        Gate::authorize(
            'download',
            $exportRequest,
        );

        $exportRequest->load('file');

        $tenantFile = $exportRequest->file;

        if (
            !$tenantFile instanceof TenantFile
            || !$tenantFile->isActive()
        ) {
            throw ValidationException::withMessages([
                'export' => [
                    'The export file is no longer available.',
                ],
            ]);
        }

        return $this->fileStorageService->download(
            $tenantFile,
        );
    }

    public function cancel(
        CancelExportRequest $request,
        ExportRequest $exportRequest,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $exportRequest,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $exportRequest =
            $this->exportRequestService->cancel(
                exportRequest: $exportRequest,
                actor: $actor,
            );

        return $this->responseService->success(
            message: 'Export request cancelled successfully.',

            data: [
                'id' => (int) $exportRequest->getKey(),
                'status' => $exportRequest->status,
            ],

            redirectTo: route('exports.index'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function exportData(
        ExportRequest $exportRequest,
    ): array {
        $requester = $exportRequest->requester;
        $file = $exportRequest->file;

        $definitionLabel =
            $this->exportRegistry->exists(
                $exportRequest->export_type,
            )
                ? $this->exportRegistry
                    ->get(
                        $exportRequest->export_type,
                    )
                    ->label()
                : $exportRequest->export_type;

        $fileIsAvailable =
            $file instanceof TenantFile
            && $file->isActive();

        $canDownload =
            $fileIsAvailable
            && Gate::allows(
                'download',
                $exportRequest,
            );

        $canCancel = Gate::allows(
            'cancel',
            $exportRequest,
        );

        return [
            'id' => (int) $exportRequest->getKey(),

            'request_key' =>
                $exportRequest->request_key,

            'name' => $exportRequest->name,

            'export_type' =>
                $exportRequest->export_type,

            'export_type_label' =>
                $definitionLabel,

            'format' => $exportRequest->format,
            'filters' => $exportRequest->filters ?? [],
            'status' => $exportRequest->status,

            'progress_percent' => (int) (
                $exportRequest->progress_percent
            ),

            'rows_exported' => (int) (
                $exportRequest->rows_exported
            ),

            'error_code' =>
                $exportRequest->error_code,

            'error_message' =>
                $exportRequest->error_message,

            'requester' => $requester === null
                ? null
                : [
                    'id' => (int) $requester->getKey(),
                    'name' => $requester->name,
                    'email' => $requester->email,
                ],

            'can_download' => $canDownload,
            'can_cancel' => $canCancel,

            'download_url' => $canDownload
                ? route(
                    'exports.download',
                    $exportRequest,
                )
                : null,

            'queued_at' =>
                $exportRequest
                    ->queued_at
                    ?->toIso8601String(),

            'started_at' =>
                $exportRequest
                    ->started_at
                    ?->toIso8601String(),

            'completed_at' =>
                $exportRequest
                    ->completed_at
                    ?->toIso8601String(),

            'failed_at' =>
                $exportRequest
                    ->failed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $exportRequest
                    ->cancelled_at
                    ?->toIso8601String(),

            'expires_at' =>
                $exportRequest
                    ->expires_at
                    ?->toIso8601String(),

            'created_at' =>
                $exportRequest
                    ->created_at
                    ?->toIso8601String(),
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
                'value' => 'queued',
                'label' => 'Queued',
            ],
            [
                'value' => 'processing',
                'label' => 'Processing',
            ],
            [
                'value' => 'completed',
                'label' => 'Completed',
            ],
            [
                'value' => 'failed',
                'label' => 'Failed',
            ],
            [
                'value' => 'cancelled',
                'label' => 'Cancelled',
            ],
            [
                'value' => 'expired',
                'label' => 'Expired',
            ],
        ];
    }
}