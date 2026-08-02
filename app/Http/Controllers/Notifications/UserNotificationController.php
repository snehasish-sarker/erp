<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\IndexUserNotificationRequest;
use App\Http\Requests\Notifications\MarkAllUserNotificationsReadRequest;
use App\Http\Requests\Notifications\MarkUserNotificationReadRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\UserNotificationService;
use App\Support\Notifications\NotificationCategoryRegistry;
use App\Support\Notifications\UserNotificationPresenter;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UserNotificationController extends Controller
{
    public function __construct(
        private readonly UserNotificationService $notificationService,
        private readonly UserNotificationPresenter $presenter,
        private readonly NotificationCategoryRegistry $categoryRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexUserNotificationRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            UserNotification::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $category = (string) (
            $validated['category'] ?? ''
        );

        $severity = (string) (
            $validated['severity'] ?? ''
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

        $notifications = UserNotification::query()
            ->where(
                'recipient_user_id',
                $actor->getKey(),
            )
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
                                    'title',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'message',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'actor_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'actor_email',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'source_type',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'source_id',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $category !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'category',
                    $category,
                ),
            )
            ->when(
                $severity !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'severity',
                    $severity,
                ),
            )
            ->when(
                $status === 'unread',
                static fn (
                    Builder $query,
                ): Builder => $query->whereNull(
                    'read_at',
                ),
            )
            ->when(
                $status === 'read',
                static fn (
                    Builder $query,
                ): Builder => $query->whereNotNull(
                    'read_at',
                ),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Notifications/Index',
            [
                'notificationPage' => [
                    'data' => $notifications
                        ->getCollection()
                        ->map(
                            fn (
                                UserNotification $notification,
                            ): array => $this->presenter->present(
                                $notification,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $notifications->currentPage(),

                        'last_page' =>
                            $notifications->lastPage(),

                        'per_page' =>
                            $notifications->perPage(),

                        'from' =>
                            $notifications->firstItem(),

                        'to' =>
                            $notifications->lastItem(),

                        'total' =>
                            $notifications->total(),
                    ],
                ],

                'unreadCount' =>
                    $this->notificationService
                        ->unreadCount($actor),

                'filters' => [
                    'search' => $search,
                    'category' => $category,
                    'severity' => $severity,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'categoryOptions' =>
                    $this->categoryRegistry->options(),

                'severityOptions' =>
                    $this->severityOptions(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function markRead(
        MarkUserNotificationReadRequest $request,
        UserNotification $userNotification,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'markRead',
            $userNotification,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $userNotification =
            $this->notificationService
                ->markAsRead(
                    userNotification:
                        $userNotification,

                    actor: $actor,
                );

        return $this->responseService->success(
            message: 'Notification marked as read.',

            data: [
                'id' => (int) (
                    $userNotification->getKey()
                ),

                'is_read' => true,

                'read_at' =>
                    $userNotification
                        ->read_at
                        ?->toIso8601String(),
            ],
        );
    }

    public function markAllRead(
        MarkAllUserNotificationsReadRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'markAllRead',
            UserNotification::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $updatedCount =
            $this->notificationService
                ->markAllAsRead($actor);

        return $this->responseService->success(
            message: $updatedCount === 0
                ? 'There are no unread notifications.'
                : 'All notifications marked as read.',

            data: [
                'updated_count' => $updatedCount,
            ],
        );
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function severityOptions(): array
    {
        return [
            [
                'value' => 'info',
                'label' => 'Information',
            ],
            [
                'value' => 'success',
                'label' => 'Success',
            ],
            [
                'value' => 'warning',
                'label' => 'Warning',
            ],
            [
                'value' => 'error',
                'label' => 'Error',
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
                'value' => 'unread',
                'label' => 'Unread',
            ],
            [
                'value' => 'read',
                'label' => 'Read',
            ],
        ];
    }
}