<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\SaasInvoiceCounter;
use Illuminate\Support\Facades\DB;

final class SaasInvoiceNumberService
{
    public function next(): string
    {
        $periodKey = now()->format('Ym');

        return DB::transaction(
            function () use ($periodKey): string {
                $counter = SaasInvoiceCounter::query()
                    ->where('counter_key', 'invoice')
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();

                if (!$counter instanceof SaasInvoiceCounter) {
                    $counter = SaasInvoiceCounter::query()->create([
                        'counter_key' => 'invoice',
                        'period_key' => $periodKey,
                        'next_number' => 1,
                    ]);

                    $counter = SaasInvoiceCounter::query()
                        ->whereKey($counter->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $number = (int) $counter->next_number;

                $counter->forceFill([
                    'next_number' => $number + 1,
                ])->save();

                return sprintf(
                    'SINV-%s-%06d',
                    $periodKey,
                    $number,
                );
            },
            attempts: 5,
        );
    }
}
