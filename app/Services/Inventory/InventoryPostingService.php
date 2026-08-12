<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryBalance;
use App\Models\ProductWarehouseSetting;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;

final class InventoryPostingService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function postGoodsReceiptLine(
        GoodsReceipt $goodsReceipt,
        GoodsReceiptLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): ?StockLedgerEntry {
        if (
            !$line->isStockItem()
            || !$line->hasAcceptedQuantity()
        ) {
            return null;
        }

        $tenantId = $this->activeTenantId();

        $this->ensureTenant(
            goodsReceipt: $goodsReceipt,
            line: $line,
            actor: $actor,
            tenantId: $tenantId,
        );

        if ($goodsReceipt->warehouse_id === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'A warehouse is required to post stock products.',
                ],
            ]);
        }

        $postingKey = sprintf(
            'goods-receipt:%d:line:%d:post',
            (int) $goodsReceipt->getKey(),
            (int) $line->getKey(),
        );

        $existingEntry = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->first();

        if ($existingEntry instanceof StockLedgerEntry) {
            return $existingEntry;
        }

        $warehouseSetting =
            ProductWarehouseSetting::query()
                ->where(
                    'product_id',
                    $line->product_id,
                )
                ->where(
                    'branch_id',
                    $goodsReceipt->branch_id,
                )
                ->where(
                    'warehouse_id',
                    $goodsReceipt->warehouse_id,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$warehouseSetting
                instanceof ProductWarehouseSetting
            || !$warehouseSetting->isActive()
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Product {$line->product_name} is not enabled for the receiving warehouse.",
                ],
            ]);
        }

        $balance = $this->lockBalance(
            tenantId: $tenantId,
            branchId:
                (int) $goodsReceipt->branch_id,
            warehouseId:
                (int) $goodsReceipt->warehouse_id,
            productId:
                (int) $line->product_id,
            unitId:
                (int) $line->unit_id,
        );

        $quantity = BigDecimal::of(
            (string) $line->accepted_quantity,
        );

        $incomingValue = BigDecimal::of(
            (string) $line->total_cost,
        );

        $currentQuantity = BigDecimal::of(
            (string) $balance->quantity_on_hand,
        );

        $currentValue = BigDecimal::of(
            (string) $balance->inventory_value,
        );

        $newQuantity = $currentQuantity
            ->plus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $newValue = $currentValue
            ->plus($incomingValue)
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $newAverageCost = $newQuantity->isZero()
            ? BigDecimal::zero()->toScale(
                self::SCALE,
            )
            : $newValue->dividedBy(
                $newQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $goodsReceipt->branch_id,

                'warehouse_id' =>
                    $goodsReceipt->warehouse_id,

                'product_id' =>
                    $line->product_id,

                'unit_id' =>
                    $line->unit_id,

                'movement_type' =>
                    'goods_receipt',

                'posting_key' =>
                    $postingKey,

                'source_type' =>
                    GoodsReceipt::class,

                'source_id' =>
                    $goodsReceipt->getKey(),

                'source_line_id' =>
                    $line->getKey(),

                'document_number' =>
                    $goodsReceipt->receipt_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    $quantity->__toString(),

                'quantity_out' =>
                    '0.000000',

                'unit_cost' =>
                    (string) $line->unit_cost,

                'total_cost' =>
                    $incomingValue->__toString(),

                'balance_quantity' =>
                    $newQuantity->__toString(),

                'balance_value' =>
                    $newValue->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' => null,
            ]);
    }

    public function reverseGoodsReceiptLine(
        GoodsReceipt $goodsReceipt,
        GoodsReceiptLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): ?StockLedgerEntry {
        if (
            !$line->isStockItem()
            || !$line->hasAcceptedQuantity()
        ) {
            return null;
        }

        $tenantId = $this->activeTenantId();

        $this->ensureTenant(
            goodsReceipt: $goodsReceipt,
            line: $line,
            actor: $actor,
            tenantId: $tenantId,
        );

        if ($goodsReceipt->warehouse_id === null) {
            throw new LogicException(
                'A posted stock receipt must have a warehouse.',
            );
        }

        $originalPostingKey = sprintf(
            'goods-receipt:%d:line:%d:post',
            (int) $goodsReceipt->getKey(),
            (int) $line->getKey(),
        );

        $reversalPostingKey = sprintf(
            'goods-receipt:%d:line:%d:reverse',
            (int) $goodsReceipt->getKey(),
            (int) $line->getKey(),
        );

        /*
         * Lock the original posting first. Concurrent reversal attempts
         * then serialize on the same immutable stock-ledger entry.
         */
        $originalEntry = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $originalPostingKey,
            )
            ->lockForUpdate()
            ->first();

        if (!$originalEntry instanceof StockLedgerEntry) {
            throw new LogicException(
                'The original Goods Receipt stock entry was not found.',
            );
        }

        /*
         * Perform the idempotency check after locking the original entry.
         * This prevents two concurrent reversal requests from creating
         * duplicate reversal entries.
         */
        $existingReversal = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $reversalPostingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            $existingReversal
            instanceof StockLedgerEntry
        ) {
            return $existingReversal;
        }

        $this->ensureOriginalEntryMatchesReceipt(
            goodsReceipt: $goodsReceipt,
            line: $line,
            originalEntry: $originalEntry,
        );

        /*
         * Locking the balance serializes this reversal against all other
         * inventory posting operations for the same Warehouse and Product.
         */
        $balance = InventoryBalance::query()
            ->where(
                'warehouse_id',
                $goodsReceipt->warehouse_id,
            )
            ->where(
                'product_id',
                $line->product_id,
            )
            ->lockForUpdate()
            ->first();

        if (!$balance instanceof InventoryBalance) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The inventory balance required for reversal was not found.',
                ],
            ]);
        }

        $this->ensureBalanceMatchesReceiptLocation(
            goodsReceipt: $goodsReceipt,
            line: $line,
            balance: $balance,
        );

        /*
         * Later movements from another source make an exact document
         * reversal unsafe without replaying the complete stock ledger.
         *
         * Later lines from the same Goods Receipt are permitted because
         * GoodsReceiptService reverses the receipt lines in reverse posting
         * order. The balance snapshot check below ensures the sequence is
         * still correct before each line is reversed.
         */
        $blockingLaterEntry =
            StockLedgerEntry::query()
                ->where(
                    'warehouse_id',
                    $goodsReceipt->warehouse_id,
                )
                ->where(
                    'product_id',
                    $line->product_id,
                )
                ->where(
                    'id',
                    '>',
                    $originalEntry->getKey(),
                )
                ->where(
                    static function ($query) use (
                        $goodsReceipt,
                    ): void {
                        $query
                            ->where(
                                'source_type',
                                '!=',
                                GoodsReceipt::class,
                            )
                            ->orWhere(
                                'source_id',
                                '!=',
                                $goodsReceipt->getKey(),
                            );
                    },
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

        if (
            $blockingLaterEntry
            instanceof StockLedgerEntry
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    "Goods Receipt {$goodsReceipt->receipt_number} cannot be reversed because a later stock movement exists for {$line->product_name}. Reverse the later movement first.",
                ],
            ]);
        }

        /*
         * Use the original immutable stock-ledger values. Do not calculate
         * the reversal using the Product's current weighted-average cost.
         */
        $quantity = BigDecimal::of(
            (string) $originalEntry->quantity_in,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $originalUnitCost = BigDecimal::of(
            (string) $originalEntry->unit_cost,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $originalValue = BigDecimal::of(
            (string) $originalEntry->total_cost,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $currentQuantity = BigDecimal::of(
            (string) $balance->quantity_on_hand,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $currentValue = BigDecimal::of(
            (string) $balance->inventory_value,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $postedBalanceQuantity = BigDecimal::of(
            (string) $originalEntry->balance_quantity,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $postedBalanceValue = BigDecimal::of(
            (string) $originalEntry->balance_value,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        /*
         * If this line is being reversed in the correct order, the current
         * balance must match the balance recorded immediately after the
         * original entry was posted.
         */
        if (
            !$currentQuantity->isEqualTo(
                $postedBalanceQuantity,
            )
            || !$currentValue->isEqualTo(
                $postedBalanceValue,
            )
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The Goods Receipt cannot be reversed because the inventory balance no longer matches its original posted balance.',
                ],
            ]);
        }

        if (
            $currentQuantity->isLessThan(
                $quantity,
            )
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    "Goods Receipt {$goodsReceipt->receipt_number} cannot be reversed because some of its stock has already been consumed or moved.",
                ],
            ]);
        }

        if (
            $currentValue->isLessThan(
                $originalValue,
            )
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The Goods Receipt cannot be reversed because its original inventory value is no longer available.',
                ],
            ]);
        }

        $newQuantity = $currentQuantity
            ->minus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $newValue = $currentValue
            ->minus($originalValue)
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            );

        if (
            $newQuantity->isLessThan(
                BigDecimal::zero(),
            )
            || $newValue->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The Goods Receipt reversal would create a negative inventory balance.',
                ],
            ]);
        }

        if (
            $newQuantity->isZero()
            && !$newValue->isZero()
        ) {
            throw ValidationException::withMessages([
                'goods_receipt' => [
                    'The Goods Receipt cannot be reversed because it would leave inventory value without inventory quantity.',
                ],
            ]);
        }

        $newAverageCost = $newQuantity->isZero()
            ? BigDecimal::zero()->toScale(
                self::SCALE,
            )
            : $newValue->dividedBy(
                $newQuantity,
                self::SCALE,
                RoundingMode::HalfUp,
            );

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $goodsReceipt->branch_id,

                'warehouse_id' =>
                    $goodsReceipt->warehouse_id,

                'product_id' =>
                    $line->product_id,

                'unit_id' =>
                    $line->unit_id,

                'movement_type' =>
                    'goods_receipt_reversal',

                'posting_key' =>
                    $reversalPostingKey,

                'source_type' =>
                    GoodsReceipt::class,

                'source_id' =>
                    $goodsReceipt->getKey(),

                'source_line_id' =>
                    $line->getKey(),

                'document_number' =>
                    $goodsReceipt->receipt_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    '0.000000',

                'quantity_out' =>
                    $quantity->__toString(),

                /*
                 * Preserve the exact original receipt cost.
                 */
                'unit_cost' =>
                    $originalUnitCost->__toString(),

                'total_cost' =>
                    $originalValue->__toString(),

                'balance_quantity' =>
                    $newQuantity->__toString(),

                'balance_value' =>
                    $newValue->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' =>
                    $originalEntry->getKey(),
            ]);
    }

    private function lockBalance(
        int $tenantId,
        int $branchId,
        int $warehouseId,
        int $productId,
        int $unitId,
    ): InventoryBalance {
        DB::table('inventory_balances')
            ->insertOrIgnore([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity_on_hand' => 0,
                'inventory_value' => 0,
                'average_unit_cost' => 0,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return InventoryBalance::query()
            ->where(
                'warehouse_id',
                $warehouseId,
            )
            ->where(
                'product_id',
                $productId,
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureOriginalEntryMatchesReceipt(
        GoodsReceipt $goodsReceipt,
        GoodsReceiptLine $line,
        StockLedgerEntry $originalEntry,
    ): void {
        $expectedQuantity = BigDecimal::of(
            (string) $line->accepted_quantity,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $postedQuantity = BigDecimal::of(
            (string) $originalEntry->quantity_in,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $postedQuantityOut = BigDecimal::of(
            (string) $originalEntry->quantity_out,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        if (
            $originalEntry->movement_type
                !== 'goods_receipt'
            || $originalEntry->source_type
                !== GoodsReceipt::class
            || (int) $originalEntry->source_id
                !== (int) $goodsReceipt->getKey()
            || (int) $originalEntry->source_line_id
                !== (int) $line->getKey()
            || (int) $originalEntry->branch_id
                !== (int) $goodsReceipt->branch_id
            || (int) $originalEntry->warehouse_id
                !== (int) $goodsReceipt->warehouse_id
            || (int) $originalEntry->product_id
                !== (int) $line->product_id
            || (int) $originalEntry->unit_id
                !== (int) $line->unit_id
            || $originalEntry->reversal_of_id
                !== null
            || !$postedQuantity->isEqualTo(
                $expectedQuantity,
            )
            || !$postedQuantityOut->isZero()
        ) {
            throw new LogicException(
                'The original Goods Receipt stock entry does not match the receipt line being reversed.',
            );
        }
    }

    private function ensureBalanceMatchesReceiptLocation(
        GoodsReceipt $goodsReceipt,
        GoodsReceiptLine $line,
        InventoryBalance $balance,
    ): void {
        if (
            (int) $balance->branch_id
                !== (int) $goodsReceipt->branch_id
            || (int) $balance->warehouse_id
                !== (int) $goodsReceipt->warehouse_id
            || (int) $balance->product_id
                !== (int) $line->product_id
            || (int) $balance->unit_id
                !== (int) $line->unit_id
        ) {
            throw new LogicException(
                'The inventory balance does not match the Goods Receipt stock location.',
            );
        }
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureTenant(
        GoodsReceipt $goodsReceipt,
        GoodsReceiptLine $line,
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $goodsReceipt->tenant_id
                !== $tenantId
            || (int) $line->tenant_id
                !== $tenantId
            || (int) $actor->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'Goods Receipt inventory posting crossed a tenant boundary.',
            );
        }
    }

    public function postPurchaseReturnLine(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): ?StockLedgerEntry {
        if (
            !$line->isStockItem()
            || !$line->hasReturnQuantity()
        ) {
            return null;
        }

        $tenantId = $this->activeTenantId();

        $this->ensurePurchaseReturnTenant(
            purchaseReturn:
                $purchaseReturn,

            line:
                $line,

            actor:
                $actor,

            tenantId:
                $tenantId,
        );

        if (
            $purchaseReturn
                ->warehouse_id
            === null
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'A warehouse is required to post a stock Purchase Return.',
                ],
            ]);
        }

        $postingKey = sprintf(
            'purchase-return:%d:line:%d:post',
            (int) $purchaseReturn->getKey(),
            (int) $line->getKey(),
        );

        $existingEntry =
            StockLedgerEntry::query()
                ->where(
                    'posting_key',
                    $postingKey,
                )
                ->first();

        if (
            $existingEntry
            instanceof StockLedgerEntry
        ) {
            return $existingEntry;
        }

        $balance = $this->lockBalance(
            tenantId:
                $tenantId,

            branchId:
                (int) $purchaseReturn
                    ->branch_id,

            warehouseId:
                (int) $purchaseReturn
                    ->warehouse_id,

            productId:
                (int) $line
                    ->product_id,

            unitId:
                (int) $line
                    ->unit_id,
        );

        $this
            ->ensureBalanceMatchesPurchaseReturnLocation(
                purchaseReturn:
                    $purchaseReturn,

                line:
                    $line,

                balance:
                    $balance,
            );

        $quantity = BigDecimal::of(
            (string) $line
                ->return_quantity,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $currentQuantity =
            BigDecimal::of(
                (string) $balance
                    ->quantity_on_hand,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $currentValue =
            BigDecimal::of(
                (string) $balance
                    ->inventory_value,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        if (
            $currentQuantity
                ->isLessThan(
                    $quantity,
                )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Purchase Return {$purchaseReturn->return_number} cannot be posted because {$line->product_name} does not have enough stock in the source warehouse.",
                ],
            ]);
        }

        if ($currentQuantity->isZero()) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Purchase Return {$purchaseReturn->return_number} cannot be posted because {$line->product_name} has no stock in the source warehouse.",
                ],
            ]);
        }

        /*
         * When the entire balance is returned, consume the exact
         * remaining inventory value to avoid a rounding residue.
         */
        if (
            $quantity
                ->isEqualTo(
                    $currentQuantity,
                )
        ) {
            $outgoingValue =
                $currentValue;

            $outgoingUnitCost =
                $outgoingValue
                    ->dividedBy(
                        $quantity,
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );
        } else {
            $outgoingUnitCost =
                BigDecimal::of(
                    (string) $balance
                        ->average_unit_cost,
                )->toScale(
                    self::SCALE,
                    RoundingMode::Unnecessary,
                );

            $outgoingValue =
                $outgoingUnitCost
                    ->multipliedBy(
                        $quantity,
                    )
                    ->toScale(
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );
        }

        if (
            $currentValue
                ->isLessThan(
                    $outgoingValue,
                )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Purchase Return {$purchaseReturn->return_number} cannot be posted because the inventory value for {$line->product_name} is insufficient.",
                ],
            ]);
        }

        $newQuantity =
            $currentQuantity
                ->minus(
                    $quantity,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $newValue =
            $currentValue
                ->minus(
                    $outgoingValue,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        if ($newQuantity->isZero()) {
            $newValue =
                BigDecimal::zero()
                    ->toScale(
                        self::SCALE,
                    );
        }

        if (
            $newQuantity->isLessThan(
                BigDecimal::zero(),
            )
            || $newValue->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Purchase Return would create a negative inventory balance.',
                ],
            ]);
        }

        $newAverageCost =
            $newQuantity->isZero()
                ? BigDecimal::zero()
                    ->toScale(
                        self::SCALE,
                    )
                : $newValue
                    ->dividedBy(
                        $newQuantity,
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $purchaseReturn
                        ->branch_id,

                'warehouse_id' =>
                    $purchaseReturn
                        ->warehouse_id,

                'product_id' =>
                    $line->product_id,

                'unit_id' =>
                    $line->unit_id,

                'movement_type' =>
                    'purchase_return',

                'posting_key' =>
                    $postingKey,

                'source_type' =>
                    PurchaseReturn::class,

                'source_id' =>
                    $purchaseReturn
                        ->getKey(),

                'source_line_id' =>
                    $line->getKey(),

                'document_number' =>
                    $purchaseReturn
                        ->return_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    '0.000000',

                'quantity_out' =>
                    $quantity
                        ->__toString(),

                'unit_cost' =>
                    $outgoingUnitCost
                        ->__toString(),

                'total_cost' =>
                    $outgoingValue
                        ->__toString(),

                'balance_quantity' =>
                    $newQuantity
                        ->__toString(),

                'balance_value' =>
                    $newValue
                        ->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' =>
                    null,
            ]);
    }

    public function reversePurchaseReturnLine(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        User $actor,
        CarbonInterface $occurredAt,
    ): ?StockLedgerEntry {
        if (
            !$line->isStockItem()
            || !$line->hasReturnQuantity()
        ) {
            return null;
        }

        $tenantId = $this->activeTenantId();

        $this->ensurePurchaseReturnTenant(
            purchaseReturn:
                $purchaseReturn,

            line:
                $line,

            actor:
                $actor,

            tenantId:
                $tenantId,
        );

        if (
            $purchaseReturn
                ->warehouse_id
            === null
        ) {
            throw new LogicException(
                'A posted stock Purchase Return must have a warehouse.',
            );
        }

        $originalPostingKey = sprintf(
            'purchase-return:%d:line:%d:post',
            (int) $purchaseReturn->getKey(),
            (int) $line->getKey(),
        );

        $reversalPostingKey = sprintf(
            'purchase-return:%d:line:%d:reverse',
            (int) $purchaseReturn->getKey(),
            (int) $line->getKey(),
        );

        $originalEntry =
            StockLedgerEntry::query()
                ->where(
                    'posting_key',
                    $originalPostingKey,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$originalEntry
            instanceof StockLedgerEntry
        ) {
            throw new LogicException(
                'The original Purchase Return stock entry was not found.',
            );
        }

        $existingReversal =
            StockLedgerEntry::query()
                ->where(
                    'posting_key',
                    $reversalPostingKey,
                )
                ->lockForUpdate()
                ->first();

        if (
            $existingReversal
            instanceof StockLedgerEntry
        ) {
            return $existingReversal;
        }

        $this
            ->ensureOriginalEntryMatchesPurchaseReturn(
                purchaseReturn:
                    $purchaseReturn,

                line:
                    $line,

                originalEntry:
                    $originalEntry,
            );

        $balance =
            InventoryBalance::query()
                ->where(
                    'warehouse_id',
                    $purchaseReturn
                        ->warehouse_id,
                )
                ->where(
                    'product_id',
                    $line->product_id,
                )
                ->lockForUpdate()
                ->first();

        if (
            !$balance
            instanceof InventoryBalance
        ) {
            throw ValidationException::withMessages([
                'purchase_return' => [
                    'The inventory balance required for Purchase Return reversal was not found.',
                ],
            ]);
        }

        $this
            ->ensureBalanceMatchesPurchaseReturnLocation(
                purchaseReturn:
                    $purchaseReturn,

                line:
                    $line,

                balance:
                    $balance,
            );

        /*
         * Exact reversal is only safe when no later movement from
         * another document exists for the same stock location.
         */
        $blockingLaterEntry =
            StockLedgerEntry::query()
                ->where(
                    'warehouse_id',
                    $purchaseReturn
                        ->warehouse_id,
                )
                ->where(
                    'product_id',
                    $line->product_id,
                )
                ->where(
                    'id',
                    '>',
                    $originalEntry
                        ->getKey(),
                )
                ->where(
                    static function (
                        $query,
                    ) use (
                        $purchaseReturn,
                    ): void {
                        $query
                            ->where(
                                'source_type',
                                '!=',
                                PurchaseReturn::class,
                            )
                            ->orWhere(
                                'source_id',
                                '!=',
                                $purchaseReturn
                                    ->getKey(),
                            );
                    },
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

        if (
            $blockingLaterEntry
            instanceof StockLedgerEntry
        ) {
            throw ValidationException::withMessages([
                'purchase_return' => [
                    "Purchase Return {$purchaseReturn->return_number} cannot be reversed because a later stock movement exists for {$line->product_name}. Reverse the later movement first.",
                ],
            ]);
        }

        /*
         * Use the immutable original Purchase Return ledger values.
         */
        $quantity = BigDecimal::of(
            (string) $originalEntry
                ->quantity_out,
        )->toScale(
            self::SCALE,
            RoundingMode::Unnecessary,
        );

        $originalUnitCost =
            BigDecimal::of(
                (string) $originalEntry
                    ->unit_cost,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $originalValue =
            BigDecimal::of(
                (string) $originalEntry
                    ->total_cost,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $currentQuantity =
            BigDecimal::of(
                (string) $balance
                    ->quantity_on_hand,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $currentValue =
            BigDecimal::of(
                (string) $balance
                    ->inventory_value,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $postedBalanceQuantity =
            BigDecimal::of(
                (string) $originalEntry
                    ->balance_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $postedBalanceValue =
            BigDecimal::of(
                (string) $originalEntry
                    ->balance_value,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        if (
            !$currentQuantity
                ->isEqualTo(
                    $postedBalanceQuantity,
                )
            || !$currentValue
                ->isEqualTo(
                    $postedBalanceValue,
                )
        ) {
            throw ValidationException::withMessages([
                'purchase_return' => [
                    'The Purchase Return cannot be reversed because the inventory balance no longer matches its original posted balance.',
                ],
            ]);
        }

        $newQuantity =
            $currentQuantity
                ->plus(
                    $quantity,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $newValue =
            $currentValue
                ->plus(
                    $originalValue,
                )
                ->toScale(
                    self::SCALE,
                    RoundingMode::HalfUp,
                );

        $newAverageCost =
            $newQuantity->isZero()
                ? BigDecimal::zero()
                    ->toScale(
                        self::SCALE,
                    )
                : $newValue
                    ->dividedBy(
                        $newQuantity,
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $purchaseReturn
                        ->branch_id,

                'warehouse_id' =>
                    $purchaseReturn
                        ->warehouse_id,

                'product_id' =>
                    $line->product_id,

                'unit_id' =>
                    $line->unit_id,

                'movement_type' =>
                    'purchase_return_reversal',

                'posting_key' =>
                    $reversalPostingKey,

                'source_type' =>
                    PurchaseReturn::class,

                'source_id' =>
                    $purchaseReturn
                        ->getKey(),

                'source_line_id' =>
                    $line->getKey(),

                'document_number' =>
                    $purchaseReturn
                        ->return_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    $quantity
                        ->__toString(),

                'quantity_out' =>
                    '0.000000',

                'unit_cost' =>
                    $originalUnitCost
                        ->__toString(),

                'total_cost' =>
                    $originalValue
                        ->__toString(),

                'balance_quantity' =>
                    $newQuantity
                        ->__toString(),

                'balance_value' =>
                    $newValue
                        ->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' =>
                    $originalEntry
                        ->getKey(),
            ]);
    }

    private function ensureOriginalEntryMatchesPurchaseReturn(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        StockLedgerEntry $originalEntry,
    ): void {
        $expectedQuantity =
            BigDecimal::of(
                (string) $line
                    ->return_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $postedQuantityIn =
            BigDecimal::of(
                (string) $originalEntry
                    ->quantity_in,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        $postedQuantityOut =
            BigDecimal::of(
                (string) $originalEntry
                    ->quantity_out,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );

        if (
            $originalEntry
                ->movement_type
                !== 'purchase_return'
            || $originalEntry
                ->source_type
                !== PurchaseReturn::class
            || (int) $originalEntry
                ->source_id
                !== (int) $purchaseReturn
                    ->getKey()
            || (int) $originalEntry
                ->source_line_id
                !== (int) $line
                    ->getKey()
            || (int) $originalEntry
                ->branch_id
                !== (int) $purchaseReturn
                    ->branch_id
            || (int) $originalEntry
                ->warehouse_id
                !== (int) $purchaseReturn
                    ->warehouse_id
            || (int) $originalEntry
                ->product_id
                !== (int) $line
                    ->product_id
            || (int) $originalEntry
                ->unit_id
                !== (int) $line
                    ->unit_id
            || $originalEntry
                ->reversal_of_id
                !== null
            || !$postedQuantityIn
                ->isZero()
            || !$postedQuantityOut
                ->isEqualTo(
                    $expectedQuantity,
                )
        ) {
            throw new LogicException(
                'The original Purchase Return stock entry does not match the return line being reversed.',
            );
        }
    }

    private function ensureBalanceMatchesPurchaseReturnLocation(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        InventoryBalance $balance,
    ): void {
        if (
            (int) $balance->branch_id
                !== (int) $purchaseReturn
                    ->branch_id
            || (int) $balance->warehouse_id
                !== (int) $purchaseReturn
                    ->warehouse_id
            || (int) $balance->product_id
                !== (int) $line
                    ->product_id
            || (int) $balance->unit_id
                !== (int) $line
                    ->unit_id
        ) {
            throw new LogicException(
                'The inventory balance does not match the Purchase Return stock location.',
            );
        }
    }

    private function ensurePurchaseReturnTenant(
        PurchaseReturn $purchaseReturn,
        PurchaseReturnLine $line,
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $purchaseReturn
                ->tenant_id
                !== $tenantId
            || (int) $line
                ->tenant_id
                !== $tenantId
            || (int) $actor
                ->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'Purchase Return inventory posting crossed a tenant boundary.',
            );
        }
    }
}
