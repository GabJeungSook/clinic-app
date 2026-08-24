<?php

namespace App\Support\Billing;

/**
 * Pure invoice math. Given line nets (already discounted), a whole-invoice
 * discount, and the tax configuration, it returns the invoice totals plus a
 * proportional per-line tax allocation.
 *
 * Tax modes:
 *  - disabled:  no tax; grand = taxable base.
 *  - exclusive: tax added on top of the base.
 *  - inclusive: prices already contain tax; the tax portion is extracted.
 */
class InvoiceCalculator
{
    /**
     * @param  array<int, float>  $lineNets  net amount per line (qty*price - line discount)
     * @return array{
     *   subtotal: float, discount_total: float, tax_total: float, grand_total: float,
     *   taxable_base: float, line_tax: array<int, float>
     * }
     */
    public static function compute(
        array $lineNets,
        float $invoiceDiscount,
        bool $taxEnabled,
        float $ratePercent,
        bool $inclusive,
    ): array {
        $subtotal = round(array_sum($lineNets), 2);
        $invoiceDiscount = round(max(0, min($invoiceDiscount, $subtotal)), 2);
        $taxableBase = round($subtotal - $invoiceDiscount, 2);

        $taxTotal = 0.0;
        if ($taxEnabled && $ratePercent > 0 && $taxableBase > 0) {
            $taxTotal = $inclusive
                ? round($taxableBase - ($taxableBase / (1 + $ratePercent / 100)), 2)
                : round($taxableBase * $ratePercent / 100, 2);
        }

        $grandTotal = ($taxEnabled && ! $inclusive)
            ? round($taxableBase + $taxTotal, 2)
            : $taxableBase; // inclusive or no-tax: base is already the payable amount

        // Allocate tax to lines proportionally to their net (largest-remainder
        // so the parts sum exactly to tax_total).
        $lineTax = self::allocate($taxTotal, $lineNets, $subtotal);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $invoiceDiscount,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'taxable_base' => $taxableBase,
            'line_tax' => $lineTax,
        ];
    }

    /**
     * @param  array<int, float>  $lineNets
     * @return array<int, float>
     */
    private static function allocate(float $total, array $lineNets, float $subtotal): array
    {
        $alloc = [];
        if ($total <= 0 || $subtotal <= 0) {
            foreach ($lineNets as $i => $_) {
                $alloc[$i] = 0.0;
            }

            return $alloc;
        }

        $cents = (int) round($total * 100);
        $assigned = 0;
        $remainders = [];

        foreach ($lineNets as $i => $net) {
            $exact = ($net / $subtotal) * $cents;
            $floor = (int) floor($exact);
            $alloc[$i] = $floor;
            $remainders[$i] = $exact - $floor;
            $assigned += $floor;
        }

        // Distribute the leftover cents to the largest remainders.
        arsort($remainders);
        $leftover = $cents - $assigned;
        foreach (array_keys($remainders) as $i) {
            if ($leftover <= 0) {
                break;
            }
            $alloc[$i]++;
            $leftover--;
        }

        return array_map(fn (int $c) => $c / 100, $alloc);
    }
}
