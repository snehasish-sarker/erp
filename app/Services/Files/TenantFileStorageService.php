<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\Tenant;
use App\Models\TenantFile;
use App\Models\User;
use App\Services\Saas\SaasUsageLimitService;
use App\Support\Files\TenantFileCategoryRegistry;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class TenantFileStorageService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantFileCategoryRegistry $categoryRegistry,
        private readonly SaasUsageLimitService $saasUsageLimitService,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function store(
        UploadedFile $file,
        string $category,
        ?User $uploader = null,
        ?Model $attachable = null,
        array $metadata = [],
    ): TenantFile {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'file' => [
                    'The uploaded file is invalid.',
                ],
            ]);
        }

        if ($uploader !== null) {
            $this->ensureUserBelongsToTenant(
                user: $uploader,
                tenantId: $tenantId,
            );
        }

        if ($attachable !== null) {
            $this->ensureModelBelongsToTenant(
                model: $attachable,
                tenantId: $tenantId,
            );
        }

        $configuration =
            $this->categoryRegistry->configuration(
                $category,
            );

        $clientExtension = trim(
            $file->getClientOriginalExtension(),
        );

        $extension = mb_strtolower(
            $clientExtension !== ''
                ? $clientExtension
                : trim(
                    (string) $file->extension(),
                ),
        );

        $mimeType = $file->getMimeType();
        $sizeBytes = $file->getSize();

        if (
            !is_string($mimeType)
            || $mimeType === ''
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'The file MIME type could not be determined.',
                ],
            ]);
        }

        if (!is_int($sizeBytes)) {
            throw ValidationException::withMessages([
                'file' => [
                    'The file size could not be determined.',
                ],
            ]);
        }

        if (
            !in_array(
                $extension,
                $configuration['extensions'],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    sprintf(
                        'The selected file type is not allowed for %s files.',
                        $configuration['label'],
                    ),
                ],
            ]);
        }

        if (
            !in_array(
                $mimeType,
                $configuration['mime_types'],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    sprintf(
                        'The selected file content is not allowed for %s files.',
                        $configuration['label'],
                    ),
                ],
            ]);
        }

        if (
            $sizeBytes
            > $configuration['max_bytes']
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    sprintf(
                        'The file may not exceed %d MB.',
                        (int) ceil(
                            $configuration['max_bytes']
                            / 1024
                            / 1024,
                        ),
                    ),
                ],
            ]);
        }

        $realPath = $file->getRealPath();

        if (
            !is_string($realPath)
            || !is_file($realPath)
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'The uploaded file could not be read.',
                ],
            ]);
        }

        $checksum = hash_file(
            'sha256',
            $realPath,
        );

        if (!is_string($checksum)) {
            throw ValidationException::withMessages([
                'file' => [
                    'The uploaded file checksum could not be generated.',
                ],
            ]);
        }

        $disk = config('tenant-files.disk');

        if (!is_string($disk) || $disk === '') {
            throw new LogicException(
                'The tenant file storage disk is not configured.',
            );
        }

        $pathPrefix = config(
            'tenant-files.path_prefix',
            'tenants',
        );

        if (
            !is_string($pathPrefix)
            || trim($pathPrefix) === ''
        ) {
            throw new LogicException(
                'The tenant file path prefix is not configured.',
            );
        }

        $date = CarbonImmutable::now(
            $tenant->timezone,
        );

        $directory = sprintf(
            '%s/%d/%s/%s/%s',
            trim($pathPrefix, '/'),
            $tenantId,
            $category,
            $date->format('Y'),
            $date->format('m'),
        );

        $storedName = Str::uuid()->toString();

        if ($extension !== '') {
            $storedName .= ".{$extension}";
        }

        $originalName = $this->sanitizeOriginalName(
            $file->getClientOriginalName(),
        );

        $storedPath = null;

        try {
            return DB::transaction(
                function () use (
                    $attachable,
                    $category,
                    $checksum,
                    $directory,
                    $disk,
                    $extension,
                    $file,
                    $metadata,
                    $mimeType,
                    $originalName,
                    $sizeBytes,
                    $storedName,
                    $uploader,
                    &$storedPath,
                ): TenantFile {
                    /*
                     * The tenant row is locked by SaasUsageLimitService while
                     * the quota is checked. Keeping the file write inside the
                     * same transaction serializes concurrent uploads for the
                     * same tenant and prevents quota races.
                     */
                    $this->saasUsageLimitService
                        ->assertCanStoreFileBytes($sizeBytes);

                    $storedPath = Storage::disk($disk)
                        ->putFileAs(
                            $directory,
                            $file,
                            $storedName,
                            [
                                'visibility' => 'private',
                            ],
                        );

                    if (
                        !is_string($storedPath)
                        || $storedPath === ''
                    ) {
                        throw ValidationException::withMessages([
                            'file' => [
                                'The file could not be stored.',
                            ],
                        ]);
                    }

                    return TenantFile::query()->create([
                        'uploaded_by_user_id' =>
                            $uploader?->getKey(),

                        'disk' => $disk,
                        'category' => $category,
                        'original_name' => $originalName,
                        'stored_name' => $storedName,
                        'path' => $storedPath,
                        'mime_type' => $mimeType,

                        'extension' => $extension === ''
                            ? null
                            : $extension,

                        'size_bytes' => $sizeBytes,
                        'checksum_sha256' => $checksum,
                        'visibility' => 'private',
                        'status' => 'active',

                        'attachable_type' =>
                            $attachable?->getMorphClass(),

                        'attachable_id' =>
                            $attachable?->getKey(),

                        'metadata' => $metadata === []
                            ? null
                            : $metadata,
                    ]);
                },
                attempts: 1,
            );
        } catch (Throwable $exception) {
            if (
                is_string($storedPath)
                && $storedPath !== ''
            ) {
                Storage::disk($disk)->delete(
                    $storedPath,
                );
            }

            throw $exception;
        }
    }

    public function attach(
        TenantFile $tenantFile,
        Model $attachable,
    ): TenantFile {
        return DB::transaction(
            function () use (
                $tenantFile,
                $attachable,
            ): TenantFile {
                $lockedFile = TenantFile::query()
                    ->whereKey($tenantFile->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureActive($lockedFile);

                $this->ensureModelBelongsToTenant(
                    model: $attachable,
                    tenantId: (int) $lockedFile->tenant_id,
                );

                $attachableId = $attachable->getKey();

                if ($attachableId === null) {
                    throw new LogicException(
                        'A file cannot be attached to an unsaved model.',
                    );
                }

                $attachableType =
                    $attachable->getMorphClass();

                if (
                    $lockedFile->attachable_type
                        === $attachableType
                    && (int) $lockedFile->attachable_id
                        === (int) $attachableId
                ) {
                    return $lockedFile;
                }

                if ($lockedFile->isAttached()) {
                    throw ValidationException::withMessages([
                        'file' => [
                            'The file is already attached to another record.',
                        ],
                    ]);
                }

                $lockedFile->attachable_type =
                    $attachableType;

                $lockedFile->attachable_id =
                    (int) $attachableId;

                $lockedFile->save();

                return $lockedFile->refresh();
            },
            attempts: 5,
        );
    }

    public function detach(
        TenantFile $tenantFile,
    ): TenantFile {
        return DB::transaction(
            function () use (
                $tenantFile,
            ): TenantFile {
                $lockedFile = TenantFile::query()
                    ->whereKey($tenantFile->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureActive($lockedFile);

                if (!$lockedFile->isAttached()) {
                    return $lockedFile;
                }

                $lockedFile->attachable_type = null;
                $lockedFile->attachable_id = null;
                $lockedFile->save();

                return $lockedFile->refresh();
            },
            attempts: 5,
        );
    }

    public function download(
        TenantFile $tenantFile,
    ): StreamedResponse {
        $this->ensureActive($tenantFile);

        $filesystem = Storage::disk(
            $tenantFile->disk,
        );

        if (!$filesystem->exists($tenantFile->path)) {
            throw ValidationException::withMessages([
                'file' => [
                    'The stored file could not be found.',
                ],
            ]);
        }

        return $filesystem->download(
            $tenantFile->path,
            $tenantFile->original_name,
            [
                'Content-Type' =>
                    $tenantFile->mime_type,

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ],
        );
    }

    public function delete(
        TenantFile $tenantFile,
    ): void {
        DB::transaction(
            function () use (
                $tenantFile,
            ): void {
                $lockedFile = TenantFile::query()
                    ->whereKey($tenantFile->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureActive($lockedFile);

                if ($lockedFile->isAttached()) {
                    throw ValidationException::withMessages([
                        'file' => [
                            'An attached file cannot be deleted. The owning module must detach it after validating its document rules.',
                        ],
                    ]);
                }

                $disk = $lockedFile->disk;
                $path = $lockedFile->path;

                $lockedFile->status = 'deleted';

                /*
                 * Avoid a separate generic update audit entry immediately
                 * before the final deleted audit entry.
                 */
                $lockedFile->saveQuietly();
                $lockedFile->delete();

                DB::afterCommit(
                    static function () use (
                        $disk,
                        $path,
                    ): void {
                        Storage::disk($disk)->delete(
                            $path,
                        );
                    },
                );
            },
            attempts: 5,
        );
    }

    private function ensureActive(
        TenantFile $tenantFile,
    ): void {
        if (!$tenantFile->isActive()) {
            throw ValidationException::withMessages([
                'file' => [
                    'The file is not available.',
                ],
            ]);
        }
    }

    private function ensureUserBelongsToTenant(
        User $user,
        int $tenantId,
    ): void {
        if ((int) $user->tenant_id !== $tenantId) {
            throw new LogicException(
                'The uploader does not belong to the active tenant.',
            );
        }
    }

    private function ensureModelBelongsToTenant(
        Model $model,
        int $tenantId,
    ): void {
        if ($model->getKey() === null) {
            throw new LogicException(
                'A file cannot be attached to an unsaved model.',
            );
        }

        if ($model instanceof Tenant) {
            if ((int) $model->getKey() !== $tenantId) {
                throw new LogicException(
                    'The attachable model belongs to another tenant.',
                );
            }

            return;
        }

        $modelTenantId = $model->getAttribute(
            'tenant_id',
        );

        if (
            !is_int($modelTenantId)
            && !is_numeric($modelTenantId)
        ) {
            throw new LogicException(
                'The attachable model does not expose a tenant ID.',
            );
        }

        if ((int) $modelTenantId !== $tenantId) {
            throw new LogicException(
                'The attachable model belongs to another tenant.',
            );
        }
    }

    private function sanitizeOriginalName(
        string $originalName,
    ): string {
        $originalName = basename(
            $originalName,
        );

        $sanitized = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $originalName,
        );

        if (
            !is_string($sanitized)
            || trim($sanitized) === ''
        ) {
            return 'file';
        }

        return mb_substr(
            trim($sanitized),
            0,
            255,
        );
    }
}