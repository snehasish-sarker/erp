<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreTenantFileRequest;
use App\Models\TenantFile;
use App\Models\User;
use App\Services\Files\TenantFileStorageService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantFileController extends Controller
{
    public function __construct(
        private readonly TenantFileStorageService $fileStorageService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function store(
        StoreTenantFileRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            TenantFile::class,
        );

        $uploadedFile = $request->file('file');

        if (!$uploadedFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => [
                    'Select a file to upload.',
                ],
            ]);
        }

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $validated = $request->validated();

        $tenantFile = $this->fileStorageService->store(
            file: $uploadedFile,
            category: $validated['category'],
            uploader: $actor,
        );

        return $this->responseService->success(
            message: 'File uploaded successfully.',
            data: $this->fileData($tenantFile),
            status: 201,
        );
    }

    public function download(
        TenantFile $tenantFile,
    ): StreamedResponse {
        Gate::authorize(
            'view',
            $tenantFile,
        );

        return $this->fileStorageService->download(
            $tenantFile,
        );
    }

    public function destroy(
        TenantFile $tenantFile,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $tenantFile,
        );

        $this->fileStorageService->delete(
            $tenantFile,
        );

        return $this->responseService->success(
            message: 'File deleted successfully.',
        );
    }

    /**
     * @return array{
     *     id: int,
     *     category: string,
     *     original_name: string,
     *     mime_type: string,
     *     extension: string|null,
     *     size_bytes: int,
     *     status: string,
     *     is_attached: bool,
     *     attachable_type: string|null,
     *     attachable_id: int|null,
     *     download_url: string,
     *     created_at: string|null
     * }
     */
    private function fileData(
        TenantFile $tenantFile,
    ): array {
        return [
            'id' => (int) $tenantFile->getKey(),
            'category' => $tenantFile->category,

            'original_name' =>
                $tenantFile->original_name,

            'mime_type' => $tenantFile->mime_type,
            'extension' => $tenantFile->extension,

            'size_bytes' =>
                (int) $tenantFile->size_bytes,

            'status' => $tenantFile->status,

            'is_attached' =>
                $tenantFile->isAttached(),

            'attachable_type' =>
                $tenantFile->attachable_type,

            'attachable_id' =>
                $tenantFile->attachable_id,

            'download_url' => route(
                'files.download',
                $tenantFile,
            ),

            'created_at' =>
                $tenantFile
                    ->created_at
                    ?->toIso8601String(),
        ];
    }
}