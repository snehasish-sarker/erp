<?php

declare(strict_types=1);

namespace App\Support\Files;

use LogicException;

final class TenantFileCategoryRegistry
{
    /**
     * @var array<string, array{
     *     label: string,
     *     max_bytes: int,
     *     extensions: list<string>,
     *     mime_types: list<string>
     * }>
     */
    private const CATEGORIES = [
        'company_branding' => [
            'label' => 'Company Branding',
            'max_bytes' => 5 * 1024 * 1024,
            'extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],

        'user_avatar' => [
            'label' => 'User Avatar',
            'max_bytes' => 5 * 1024 * 1024,
            'extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],

        'product_media' => [
            'label' => 'Product Media',
            'max_bytes' => 10 * 1024 * 1024,
            'extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],

        'document_attachment' => [
            'label' => 'Document Attachment',
            'max_bytes' => 25 * 1024 * 1024,
            'extensions' => [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'webp',
                'doc',
                'docx',
                'xls',
                'xlsx',
                'csv',
                'txt',
            ],
            'mime_types' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
                'text/csv',
                'text/plain',
            ],
        ],

        'import_source' => [
            'label' => 'Import Source',
            'max_bytes' => 50 * 1024 * 1024,
            'extensions' => [
                'csv',
                'xls',
                'xlsx',
            ],
            'mime_types' => [
                'text/csv',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],

        'export_result' => [
            'label' => 'Export Result',
            'max_bytes' => 100 * 1024 * 1024,
            'extensions' => [
                'csv',
                'xls',
                'xlsx',
                'pdf',
                'zip',
            ],
            'mime_types' => [
                'text/csv',
                'text/plain',
                'application/pdf',
                'application/zip',
                'application/x-zip-compressed',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/octet-stream',
            ],
        ],

        'report_output' => [
            'label' => 'Report Output',
            'max_bytes' => 100 * 1024 * 1024,
            'extensions' => [
                'csv',
                'xls',
                'xlsx',
                'pdf',
                'zip',
            ],
            'mime_types' => [
                'text/csv',
                'text/plain',
                'application/pdf',
                'application/zip',
                'application/x-zip-compressed',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/octet-stream',
            ],
        ],

        'general' => [
            'label' => 'General File',
            'max_bytes' => 25 * 1024 * 1024,
            'extensions' => [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'webp',
                'doc',
                'docx',
                'xls',
                'xlsx',
                'csv',
                'txt',
            ],
            'mime_types' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
                'text/csv',
                'text/plain',
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    public function exists(string $category): bool
    {
        return array_key_exists(
            $category,
            self::CATEGORIES,
        );
    }

    /**
     * @return array{
     *     label: string,
     *     max_bytes: int,
     *     extensions: list<string>,
     *     mime_types: list<string>
     * }
     */
    public function configuration(
        string $category,
    ): array {
        $configuration =
            self::CATEGORIES[$category] ?? null;

        if (!is_array($configuration)) {
            throw new LogicException(
                "Unsupported tenant file category [{$category}].",
            );
        }

        return $configuration;
    }

    public function label(string $category): string
    {
        return $this->configuration(
            $category,
        )['label'];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     max_bytes: int,
     *     extensions: list<string>
     * }>
     */
    public function options(): array
    {
        $options = [];

        foreach (
            self::CATEGORIES
            as $value => $configuration
        ) {
            $options[] = [
                'value' => $value,
                'label' => $configuration['label'],
                'max_bytes' =>
                    $configuration['max_bytes'],
                'extensions' =>
                    $configuration['extensions'],
            ];
        }

        return $options;
    }
}