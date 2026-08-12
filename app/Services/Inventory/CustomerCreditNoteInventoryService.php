<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteDispatchAllocation;
use App\Models\CustomerCreditNoteLine;
use App\Models\InventoryBalance;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerCreditNoteInventoryService
{
    private const SCALE = 6;

    public function postLine(
        CustomerCreditNote $creditNote,
        CustomerCreditNoteLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): StockLedgerEntry {
        $this->ensureInsideTransaction();

        if (!$line->restoresInventory()) {
            throw new LogicException(
                'Only a physical stock-return line can restore inventory.',
            );
        }

        $postingKey = $this->postingKey($creditNote, $line);

        $existing = StockLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof StockLedgerEntry) {
            return $existing;
        }

        $this->ensureContext($creditNote, $line, $actor);

        $allocations = $this->lockAllocations($line);
        [$quantity, $totalCost] = $this->allocationTotals($allocations);

        $lineQuantity = BigDecimal::of((string) $line->credit_quantity);
        $lineCost = BigDecimal::of((string) $line->total_cost);

        if (
            !$quantity->isEqualTo($lineQuantity)
            || !$totalCost->isEqualTo($lineCost)
        ) {
            throw new LogicException(
                'The Customer Credit Note dispatch-cost allocations do not match the stock return line.',
            );
        }

        if ($creditNote->warehouse_id === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'A warehouse is required before stock can be returned.',
                ],
            ]);
        }

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $creditNote->warehouse_id)
            ->where('product_id', $line->product_id)
            ->lockForUpdate()
            ->first();

        if (!$balance instanceof InventoryBalance) {
            $balance = InventoryBalance::query()->create([
                'branch_id' => $creditNote->branch_id,
                'warehouse_id' => $creditNote->warehouse_id,
                'product_id' => $line->product_id,
                'unit_id' => $line->unit_id,
                'quantity_on_hand' => '0.000000',
                'inventory_value' => '0.000000',
                'average_unit_cost' => '0.000000',
                'version' => 0,
            ]);

            $balance = InventoryBalance::query()
                ->whereKey($balance->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        if (
            (int) $balance->tenant_id !== (int) $creditNote->tenant_id
            || (int) $balance->branch_id !== (int) $creditNote->branch_id
            || (int) $balance->warehouse_id !== (int) $creditNote->warehouse_id
            || (int) $balance->product_id !== (int) $line->product_id
            || (int) $balance->unit_id !== (int) $line->unit_id
        ) {
            throw new LogicException(
                'The locked inventory balance does not match the Customer Credit Note line.',
            );
        }

        $currentQuantity = BigDecimal::of((string) $balance->quantity_on_hand);
        $currentValue = BigDecimal::of((string) $balance->inventory_value);

        $newQuantity = $currentQuantity
            ->plus($quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newValue = $currentValue
            ->plus($totalCost)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newAverage = $newQuantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $newValue->dividedBy(
                $newQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $unitCost = $quantity->isZero()
            ? BigDecimal::zero()->toScale(self::SCALE)
            : $totalCost->dividedBy(
                $quantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $balance->quantity_on_hand = $newQuantity->__toString();
        $balance->inventory_value = $newValue->__toString();
        $balance->average_unit_cost = $newAverage->__toString();
        $balance->version = (int) $balance->version + 1;
        $balance->save();

        return StockLedgerEntry::query()->create([
            'branch_id' => $creditNote->branch_id,
            'warehouse_id' => $creditNote->warehouse_id,
            'product_id' => $line->product_id,
            'unit_id' => $line->unit_id,
            'movement_type' => 'sales_return',
            'posting_key' => $postingKey,
            'source_type' => CustomerCreditNote::class,
            'source_id' => $creditNote->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $creditNote->credit_note_number,
            'occurred_at' => $occurredAt,
            'quantity_in' => $quantity->__toString(),
            'quantity_out' => '0.000000',
            'unit_cost' => $unitCost->__toString(),
            'total_cost' => $totalCost->__toString(),
            'balance_quantity' => $newQuantity->__toString(),
            'balance_value' => $newValue->__toString(),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => null,
        ]);
    }

    public function reverseLine(
        CustomerCreditNote $creditNote,
        CustomerCreditNoteLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): StockLedgerEntry {
        $this->ensureInsideTransaction();

        if (!$line->restoresInventory()) {
            throw new LogicException(
                'Only a physical stock-return line can reverse inventory.',
            );
        }

        $this->ensureContext($creditNote, $line, $actor);

        $original = StockLedgerEntry::query()
            ->where('posting_key', $this->postingKey($creditNote, $line))
            ->lockForUpdate()
            ->first();

        if (!$original instanceof StockLedgerEntry) {
            throw new LogicException(
                'The original Customer Credit Note stock-ledger entry is unavailable.',
            );
        }

        $reversalKey = $this->reversalPostingKey($creditNote, $line);

        $existing = StockLedgerEntry::query()
            ->where('posting_key', $reversalKey)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof StockLedgerEntry) {
            return $existing;
        }

        $blockingLaterMovement = StockLedgerEntry::query()
            ->where('warehouse_id', $original->warehouse_id)
            ->where('product_id', $original->product_id)
            ->where('id', '>', $original->getKey())
            ->where(
                static function ($query) use ($creditNote): void {
                    $query
                        ->where('source_type', '!=', CustomerCreditNote::class)
                        ->orWhere('source_id', '!=', $creditNote->getKey());
                },
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($blockingLaterMovement instanceof StockLedgerEntry) {
            throw ValidationException::withMessages([
                'customer_credit_note' => [
                    "Credit Note {$creditNote->credit_note_number} cannot be reversed because a later stock movement exists for {$line->product_name}.",
                ],
            ]);
        }

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $original->warehouse_id)
            ->where('product_id', $original->product_id)
            ->lockForUpdate()
            ->first();

        if (!$balance instanceof InventoryBalance) {
            throw new LogicException(
                'The inventory balance required for sales-return reversal is unavailable.',
            );
        }

        $currentQuantity = BigDecimal::of((string) $balance->quantity_on_hand);
        $currentValue = BigDecimal::of((string) $balance->inventory_value);
        $postedBalanceQuantity = BigDecimal::of((string) $original->balance_quantity);
        $postedBalanceValue = BigDecimal::of((string) $original->balance_value);

        if (
            !$currentQuantity->isEqualTo($postedBalanceQuantity)
            || !$currentValue->isEqualTo($postedBalanceValue)
        ) {
            throw ValidationException::withMessages([
                'customer_credit_note' => [
                    "Credit Note {$creditNote->credit_note_number} cannot be reversed because the inventory balance for {$line->product_name} changed after the return.",
                ],
            ]);
        }

        $quantity = BigDecimal::of((string) $original->quantity_in);
        $totalCost = BigDecimal::of((string) $original->total_cost);

        if (
            $currentQuantity->isLessThan($quantity)
            || $currentValue->isLessThan($totalCost)
        ) {
            throw ValidationException::withMessages([
                'customer_credit_note' => [
                    "Inventory is insufficient to reverse the returned quantity for {$line->product_name}.",
                ],
            ]);
        }

        $newQuantity = $currentQuantity
            ->minus($quantity)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        $newValue = $currentValue
            ->minus($totalCost)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        if ($newQuantity->isZero()) {
            $newValue = BigDecimal::zero()->toScale(self::SCALE);
            $newAverage = BigDecimal::zero()->toScale(self::SCALE);
        } else {
            if ($newValue->isLessThan(BigDecimal::zero())) {
                throw new LogicException(
                    'The sales-return reversal would create a negative inventory value.',
                );
            }

            $newAverage = $newValue->dividedBy(
                $newQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );
        }

        $balance->quantity_on_hand = $newQuantity->__toString();
        $balance->inventory_value = $newValue->__toString();
        $balance->average_unit_cost = $newAverage->__toString();
        $balance->version = (int) $balance->version + 1;
        $balance->save();

        return StockLedgerEntry::query()->create([
            'branch_id' => $creditNote->branch_id,
            'warehouse_id' => $original->warehouse_id,
            'product_id' => $line->product_id,
            'unit_id' => $line->unit_id,
            'movement_type' => 'sales_return_reversal',
            'posting_key' => $reversalKey,
            'source_type' => CustomerCreditNote::class,
            'source_id' => $creditNote->getKey(),
            'source_line_id' => $line->getKey(),
            'document_number' => $creditNote->credit_note_number,
            'occurred_at' => $occurredAt,
            'quantity_in' => '0.000000',
            'quantity_out' => $quantity->__toString(),
            'unit_cost' => (string) $original->unit_cost,
            'total_cost' => $totalCost->__toString(),
            'balance_quantity' => $newQuantity->__toString(),
            'balance_value' => $newValue->__toString(),
            'created_by_user_id' => $actor->getKey(),
            'reversal_of_id' => $original->getKey(),
        ]);
    }

    public function postingKey(
        CustomerCreditNote $creditNote,
        CustomerCreditNoteLine $line,
    ): string {
        return sprintf(
            'customer_credit_note:%d:line:%d:stock:return',
            (int) $creditNote->getKey(),
            (int) $line->getKey(),
        );
    }

    public function reversalPostingKey(
        CustomerCreditNote $creditNote,
        CustomerCreditNoteLine $line,
    ): string {
        return sprintf(
            'customer_credit_note:%d:line:%d:stock:reverse',
            (int) $creditNote->getKey(),
            (int) $line->getKey(),
        );
    }

    /**
     * @return Collection<int, CustomerCreditNoteDispatchAllocation>
     */
    private function lockAllocations(
        CustomerCreditNoteLine $line,
    ): Collection {
        $allocations = CustomerCreditNoteDispatchAllocation::query()
            ->where('customer_credit_note_line_id', $line->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($allocations->isEmpty()) {
            throw new LogicException(
                'A physical sales return must retain dispatch-cost allocations.',
            );
        }

        return $allocations;
    }

    /**
     * @param Collection<int, CustomerCreditNoteDispatchAllocation> $allocations
     * @return array{0: BigDecimal, 1: BigDecimal}
     */
    private function allocationTotals(Collection $allocations): array
    {
        $quantity = BigDecimal::zero();
        $cost = BigDecimal::zero();

        foreach ($allocations as $allocation) {
            $quantity = $quantity->plus(
                BigDecimal::of((string) $allocation->allocated_quantity),
            );

            $cost = $cost->plus(
                BigDecimal::of((string) $allocation->total_cost),
            );
        }

        return [
            $quantity->toScale(self::SCALE, RoundingMode::HalfUp),
            $cost->toScale(self::SCALE, RoundingMode::HalfUp),
        ];
    }

    private function ensureContext(
        CustomerCreditNote $creditNote,
        CustomerCreditNoteLine $line,
        User $actor,
    ): void {
        if (
            (int) $creditNote->tenant_id !== (int) $line->tenant_id
            || (int) $creditNote->tenant_id !== (int) $actor->tenant_id
            || (int) $creditNote->getKey()
                !== (int) $line->customer_credit_note_id
            || (int) $creditNote->branch_id <= 0
        ) {
            throw new LogicException(
                'The Customer Credit Note inventory context is inconsistent.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Credit Note inventory posting must run inside the source transaction.',
            );
        }
    }
}