<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tenant_files',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('uploaded_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('disk', 50);

                $table->string('category', 50)
                    ->comment('company_branding, user_avatar, product_media, document_attachment, import_source, export_result, report_output, general')
                    ->index();

                $table->string('original_name');
                $table->string('stored_name');
                $table->string('path', 500);

                $table->string('mime_type', 150);
                $table->string('extension', 20)->nullable();

                $table->unsignedBigInteger('size_bytes');

                $table->char(
                    'checksum_sha256',
                    64,
                );

                $table->string('visibility', 20)
                    ->default('private')
                    ->comment('private')
                    ->index();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, quarantined, deleted')
                    ->index();

                $table->string('attachable_type')
                    ->nullable();

                $table->unsignedBigInteger('attachable_id')
                    ->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'disk',
                        'path',
                    ],
                    'tenant_files_disk_path_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'checksum_sha256',
                    ],
                    'tenant_files_tenant_checksum_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'category',
                    ],
                    'tenant_files_tenant_status_category_index',
                );

                $table->index(
                    [
                        'attachable_type',
                        'attachable_id',
                    ],
                    'tenant_files_attachable_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'uploaded_by_user_id',
                    ],
                    'tenant_files_tenant_uploader_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_files');
    }
};