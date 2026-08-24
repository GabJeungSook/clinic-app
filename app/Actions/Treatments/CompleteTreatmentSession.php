<?php

namespace App\Actions\Treatments;

use App\Actions\Inventory\ConsumeStockFefo;
use App\Enums\CourseStatus;
use App\Enums\SessionStatus;
use App\Exceptions\NoSessionsRemainingException;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionConsumption;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Complete a treatment session in one transaction:
 *   1. Guard that the course still has a session left (unless overridden).
 *   2. Consume the service's bill-of-materials (or caller-supplied overrides)
 *      from inventory via FEFO, recording a consumption row per movement.
 *   3. Mark the session completed and stamp its sequential number.
 *   4. Auto-complete the course when no sessions remain.
 *
 * "Sessions remaining" is never stored — it is always derived from completed
 * sessions — so this action cannot drift it out of sync.
 */
class CompleteTreatmentSession
{
    public function __construct(private readonly ConsumeStockFefo $consume) {}

    /**
     * @param  array<int, array{inventory_item_id:string, quantity:float, unit_id?:string|null}>|null  $consumptionOverrides
     *         null = consume the service's default bill of materials.
     *         array = consume exactly these items (an empty array = consume nothing).
     */
    public function handle(
        TreatmentSession $session,
        ?int $performedBy = null,
        ?array $consumptionOverrides = null,
        bool $allowOverride = false,
        ?\DateTimeInterface $performedAt = null,
    ): TreatmentSession {
        return DB::transaction(function () use ($session, $performedBy, $consumptionOverrides, $allowOverride, $performedAt) {
            $session->loadMissing('course', 'service.consumables');

            if ($session->status === SessionStatus::Completed) {
                return $session;
            }

            $course = $session->course;

            if ($course !== null && $course->sessions_remaining <= 0 && ! $allowOverride) {
                throw NoSessionsRemainingException::forCourse($course->name_snapshot);
            }

            $lines = $this->resolveConsumptionLines($session, $consumptionOverrides);

            foreach ($lines as $line) {
                $unit = $line['unit_id'] ? Unit::find($line['unit_id']) : null;
                $factor = $unit ? (float) $unit->factor_to_base : 1.0;
                $baseQty = (float) $line['quantity'] * $factor;

                $movements = $this->consume->handle(
                    item: $line['item'],
                    quantityBase: $baseQty,
                    reference: $session,
                    performedBy: $performedBy,
                    allowOverride: $allowOverride,
                );

                foreach ($movements as $movement) {
                    TreatmentSessionConsumption::create([
                        'treatment_session_id' => $session->id,
                        'inventory_item_id' => $line['item']->id,
                        'batch_id' => $movement->batch_id,
                        'quantity' => abs((float) $movement->quantity),
                        'unit_id' => $line['item']->base_unit_id,
                        'stock_movement_id' => $movement->id,
                    ]);
                }
            }

            // Sequential number = completed-count in the course before this one + 1.
            $sessionNumber = null;
            if ($course !== null) {
                $completedBefore = $course->sessions()
                    ->where('status', SessionStatus::Completed->value)
                    ->where('id', '!=', $session->id)
                    ->count();
                $sessionNumber = $completedBefore + 1;
            }

            $session->forceFill([
                'status' => SessionStatus::Completed,
                'performed_at' => $performedAt ?? $session->performed_at ?? now(),
                'performed_by' => $performedBy ?? $session->performed_by,
                'session_number' => $sessionNumber ?? $session->session_number,
            ])->save();

            // Auto-complete the course when the last session is used up.
            if ($course !== null) {
                $course->refresh();
                if ($course->sessions_remaining <= 0 && $course->status === CourseStatus::Active) {
                    $course->forceFill(['status' => CourseStatus::Completed])->save();
                }
            }

            return $session->fresh(['consumptions', 'course']);
        });
    }

    /**
     * Build the effective list of items to consume: caller overrides if given,
     * otherwise the service's bill of materials.
     *
     * @param  array<int, array{inventory_item_id:string, quantity:float, unit_id?:string|null}>|null  $overrides
     * @return array<int, array{item:\App\Models\InventoryItem, quantity:float, unit_id:?string}>
     */
    private function resolveConsumptionLines(TreatmentSession $session, ?array $overrides): array
    {
        $lines = [];

        // An explicit list (even empty) is authoritative; null falls back to BoM.
        if ($overrides !== null) {
            foreach ($overrides as $o) {
                $item = \App\Models\InventoryItem::find($o['inventory_item_id']);
                if ($item === null) {
                    continue;
                }
                $lines[] = [
                    'item' => $item,
                    'quantity' => (float) $o['quantity'],
                    'unit_id' => $o['unit_id'] ?? null,
                ];
            }

            return $lines;
        }

        foreach ($session->service->consumables as $bom) {
            $lines[] = [
                'item' => $bom->item,
                'quantity' => (float) $bom->quantity,
                'unit_id' => $bom->unit_id,
            ];
        }

        return $lines;
    }
}
