<?php

declare(strict_types=1);

namespace App\Services\Auditing;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AuditLogService
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_ATTRIBUTES = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'password_confirmation',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'secret',
        'token',
        'otp',
        'recovery_code',
        'private_key',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function recordCreated(Model $subject): void
    {
        $this->record(
            subject: $subject,
            event: 'created',
            newValues: $subject->getAttributes(),
        );
    }

    public function recordUpdated(Model $subject): void
    {
        $changes = $subject->getChanges();
        $oldValues = [];

        foreach (array_keys($changes) as $attribute) {
            $oldValues[$attribute] = $subject->getOriginal(
                $attribute,
            );
        }

        $this->record(
            subject: $subject,
            event: 'updated',
            oldValues: $oldValues,
            newValues: $changes,
            skipWhenEmpty: true,
        );
    }

    public function recordDeleted(Model $subject): void
    {
        $this->record(
            subject: $subject,
            event: 'deleted',
            oldValues: $subject->getOriginal(),
        );
    }

    public function recordRestored(Model $subject): void
    {
        $this->record(
            subject: $subject,
            event: 'restored',
            newValues: $subject->getAttributes(),
        );
    }

    /**
     * Use this for workflows that are not represented by normal model
     * attributes, such as posting, approval, reversal, password resets,
     * permission changes, and stock-ledger operations.
     *
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    public function recordCustomEvent(
        Model $subject,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): void {
        $this->record(
            subject: $subject,
            event: $event,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function record(
        Model $subject,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        bool $skipWhenEmpty = false,
    ): void {
        if ($subject instanceof AuditLog) {
            return;
        }

        $tenantId = $this->resolveTenantId($subject);

        if ($tenantId === null) {
            return;
        }

        /*
         * Prevent demo/database seed operations from producing misleading
         * audit records. Console jobs may still audit when they establish
         * TenantContext before executing.
         */
        if (
            app()->runningInConsole()
            && !$this->tenantContext->hasTenant()
        ) {
            return;
        }

        $oldValues = $this->sanitize(
            subject: $subject,
            values: $oldValues,
        );

        $newValues = $this->sanitize(
            subject: $subject,
            values: $newValues,
        );

        if (
            $skipWhenEmpty
            && $oldValues === []
            && $newValues === []
        ) {
            return;
        }

        $actor = Auth::user();

        if (
            !$actor instanceof User
            || (int) $actor->tenant_id !== $tenantId
        ) {
            $actor = null;
        }

        $requestContext = $this->requestContext();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),

            'subject_id' => $subject->getKey() === null
                ? null
                : (int) $subject->getKey(),

            'subject_label' => $this->subjectLabel($subject),

            'old_values' => $oldValues === []
                ? null
                : $oldValues,

            'new_values' => $newValues === []
                ? null
                : $newValues,

            'metadata' => $metadata === []
                ? null
                : $this->normalizeArray($metadata),

            'request_id' => $requestContext['request_id'],
            'route_name' => $requestContext['route_name'],
            'http_method' => $requestContext['http_method'],
            'ip_address' => $requestContext['ip_address'],
            'url' => $requestContext['url'],
            'user_agent' => $requestContext['user_agent'],
            'created_at' => now(),
        ]);
    }

    private function resolveTenantId(Model $subject): ?int
    {
        if ($subject instanceof Tenant) {
            return $subject->getKey() === null
                ? null
                : (int) $subject->getKey();
        }

        $subjectTenantId = $subject->getAttribute(
            'tenant_id',
        );

        if ($subjectTenantId !== null) {
            return (int) $subjectTenantId;
        }

        return $this->tenantContext->id();
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitize(
        Model $subject,
        array $values,
    ): array {
        $excludedAttributes = self::EXCLUDED_ATTRIBUTES;

        if (method_exists(
            $subject,
            'auditExcludedAttributes',
        )) {
            /** @var list<string> $modelExcludedAttributes */
            $modelExcludedAttributes = $subject
                ->auditExcludedAttributes();

            $excludedAttributes = array_values(
                array_unique([
                    ...$excludedAttributes,
                    ...$modelExcludedAttributes,
                ]),
            );
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            $attribute = (string) $key;

            if (
                in_array(
                    $attribute,
                    $excludedAttributes,
                    true,
                )
                || $this->isSensitiveKey($attribute)
            ) {
                continue;
            }

            $sanitized[$attribute] = $this->normalizeValue(
                $value,
            );
        }

        ksort($sanitized);

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (Str::contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = (string) $key;

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            $normalized[$normalizedKey] =
                $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : get_debug_type($value);
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    private function subjectLabel(Model $subject): string
    {
        foreach (
            [
                'name',
                'code',
                'email',
                'number',
            ] as $attribute
        ) {
            $value = $subject->getAttribute($attribute);

            if (
                is_string($value)
                && trim($value) !== ''
            ) {
                return $value;
            }
        }

        $subjectName = class_basename($subject);
        $subjectId = $subject->getKey();

        return $subjectId === null
            ? $subjectName
            : "{$subjectName} #{$subjectId}";
    }

    /**
     * @return array{
     *     request_id: string|null,
     *     route_name: string|null,
     *     http_method: string|null,
     *     ip_address: string|null,
     *     url: string|null,
     *     user_agent: string|null
     * }
     */
    private function requestContext(): array
    {
        if (app()->runningInConsole()) {
            return [
                'request_id' => null,
                'route_name' => null,
                'http_method' => null,
                'ip_address' => null,
                'url' => null,
                'user_agent' => null,
            ];
        }

        /** @var Request $request */
        $request = request();

        $requestId = $request->attributes->get(
            'audit_request_id',
        );

        if (
            !is_string($requestId)
            || $requestId === ''
        ) {
            $requestId = Str::uuid()->toString();

            $request->attributes->set(
                'audit_request_id',
                $requestId,
            );
        }

        return [
            'request_id' => $requestId,
            'route_name' => $request->route()?->getName(),
            'http_method' => $request->method(),
            'ip_address' => $request->ip(),

            /*
             * Query parameters are intentionally excluded because URLs can
             * sometimes contain confidential values.
             */
            'url' => $request->url(),

            'user_agent' => $request->userAgent(),
        ];
    }
}