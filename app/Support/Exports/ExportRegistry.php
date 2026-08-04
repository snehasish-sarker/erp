<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\User;
use InvalidArgumentException;

final class ExportRegistry
{
    /**
     * @var array<string, ExportDefinition>
     */
    private array $definitions = [];

    /**
     * @param iterable<ExportDefinition> $definitions
     */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $key = $definition->key();

            if (isset($this->definitions[$key])) {
                throw new InvalidArgumentException(
                    "Duplicate export definition [{$key}].",
                );
            }

            $this->definitions[$key] = $definition;
        }
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(
            $this->definitions,
        );
    }

    public function get(string $key): ExportDefinition
    {
        $definition = $this->definitions[$key] ?? null;

        if (!$definition instanceof ExportDefinition) {
            throw new InvalidArgumentException(
                "Unsupported export type [{$key}].",
            );
        }

        return $definition;
    }

    public function exists(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    public function optionsFor(User $user): array
    {
        $options = [];

        foreach ($this->definitions as $definition) {
            if (
                !$definition->isSelectableFromExportCenter()
                || !$user->can(
                    $definition->requiredPermission(),
                )
            ) {
                continue;
            }

            $options[] = [
                'value' => $definition->key(),
                'label' => $definition->label(),
            ];
        }

        usort(
            $options,
            static fn (
                array $left,
                array $right,
            ): int => $left['label'] <=> $right['label'],
        );

        return $options;
    }
}