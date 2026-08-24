<?php

namespace App\Enums;

/**
 * Types of entries in the immutable stock-movement ledger.
 *
 * Each movement stores a SIGNED quantity; on-hand stock for an item (or batch)
 * is the SUM of its movement quantities. sign() gives the direction so callers
 * never have to remember which types add and which subtract.
 */
enum MovementType: string
{
    case PurchaseIn = 'purchase_in';
    case ReturnIn = 'return_in';
    case AdjustmentIn = 'adjustment_in';
    case TransferIn = 'transfer_in';
    case SessionConsume = 'session_consume';
    case SaleOut = 'sale_out';
    case AdjustmentOut = 'adjustment_out';
    case ExpiryWriteoff = 'expiry_writeoff';
    case TransferOut = 'transfer_out';

    /**
     * Whether this movement increases stock on hand.
     */
    public function isInflow(): bool
    {
        return match ($this) {
            self::PurchaseIn, self::ReturnIn, self::AdjustmentIn, self::TransferIn => true,
            default => false,
        };
    }

    /**
     * +1 for inflow, -1 for outflow. Multiply an absolute quantity by this
     * to get the signed quantity stored on the ledger.
     */
    public function sign(): int
    {
        return $this->isInflow() ? 1 : -1;
    }

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Purchase received',
            self::ReturnIn => 'Return to stock',
            self::AdjustmentIn => 'Adjustment (in)',
            self::TransferIn => 'Transfer in',
            self::SessionConsume => 'Consumed in session',
            self::SaleOut => 'Retail sale',
            self::AdjustmentOut => 'Adjustment (out)',
            self::ExpiryWriteoff => 'Expiry write-off',
            self::TransferOut => 'Transfer out',
        };
    }
}
