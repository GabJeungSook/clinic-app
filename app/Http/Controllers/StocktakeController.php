<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdjustStock;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk physical stock count. Staff count what is physically on the shelf and
 * type it in; on save we post ONE adjustment per item whose count differs from
 * the system figure (reusing AdjustStock so every correction is on the ledger
 * with a reason). Items left blank are treated as "not counted" and untouched.
 */
class StocktakeController extends Controller
{
    public function edit(): Response
    {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->with('baseUnit:id,abbreviation', 'category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'base_unit_id', 'inventory_category_id', 'stock_on_hand_cache'])
            ->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'category' => $i->category?->name,
                'unit' => $i->baseUnit?->abbreviation,
                'on_hand' => (float) $i->stock_on_hand_cache,
            ]);

        return Inertia::render('Inventory/Stocktake', [
            'items' => $items,
        ]);
    }

    public function store(Request $request, AdjustStock $adjust): RedirectResponse
    {
        $data = $request->validate([
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.id' => ['required', 'string', 'exists:inventory_items,id'],
            'counts.*.counted' => ['nullable', 'numeric', 'min:0'],
        ]);

        $adjusted = 0;

        DB::transaction(function () use ($data, $adjust, $request, &$adjusted) {
            $date = now()->toDateString();

            foreach ($data['counts'] as $row) {
                // Blank = not counted → leave the item alone.
                if (! isset($row['counted']) || $row['counted'] === null || $row['counted'] === '') {
                    continue;
                }

                $item = InventoryItem::query()->find($row['id']);
                if ($item === null) {
                    continue;
                }

                $current = $item->stockOnHand();
                $delta = round((float) $row['counted'] - $current, 3);

                // Already matches the system — no movement needed.
                if (abs($delta) < 0.001) {
                    continue;
                }

                $adjust->handle($item, $delta, "Stocktake {$date}", performedBy: $request->user()?->id);
                $adjusted++;
            }
        });

        return $adjusted > 0
            ? redirect()->route('inventory.index')->with('success', "Stock count saved — {$adjusted} item(s) corrected.")
            : back()->with('success', 'Stock count saved — everything already matched.');
    }
}
