<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\OperationsRuntimeState;
use Illuminate\Support\Facades\Schema;

final class OperationsRuntimeStateService
{
    /** @param array<string, mixed> $value */
    public function touch(string $key, array $value = []): ?OperationsRuntimeState
    {
        if (!Schema::hasTable('operations_runtime_states')) {
            return null;
        }

        /** @var OperationsRuntimeState $state */
        $state = OperationsRuntimeState::query()->updateOrCreate(
            ['state_key' => $key],
            ['value' => $value, 'touched_at' => now()],
        );

        return $state;
    }

    public function find(string $key): ?OperationsRuntimeState
    {
        if (!Schema::hasTable('operations_runtime_states')) {
            return null;
        }

        $state = OperationsRuntimeState::query()->find($key);

        return $state instanceof OperationsRuntimeState
            ? $state
            : null;
    }
}
