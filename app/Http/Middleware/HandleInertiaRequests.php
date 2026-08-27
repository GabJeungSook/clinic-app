<?php

namespace App\Http\Middleware;

use App\Models\Batch;
use App\Models\InventoryItem;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // Flat list of the current user's permission names, used to
                // filter the sidebar nav on the client.
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Count of inventory things needing attention (low/oversold items +
            // batches nearing expiry), shown as a badge on the Inventory nav item.
            // Lazy closure so it only runs for users who can see inventory.
            'inventoryAlerts' => fn () => ($user && $user->can('inventory.view'))
                ? $this->inventoryAlertCount()
                : 0,
            // Surfaced as toasts on the client (see resources/js/lib/flashToast.ts).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Items that are low on stock or oversold, plus batches nearing expiry —
     * the number a manager would want to act on. Kept as a simple total so the
     * sidebar badge stays glanceable.
     */
    private function inventoryAlertCount(): int
    {
        $threshold = (int) Settings::get('inventory.expiry_threshold_days', 30);

        $needsReorder = InventoryItem::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('reorder_level', '>', 0)->whereColumn('stock_on_hand_cache', '<=', 'reorder_level'))
                ->orWhere('stock_on_hand_cache', '<', 0))
            ->count();

        $expiring = Batch::query()
            ->whereNotNull('expiry_date')
            ->where('qty_remaining_cache', '>', 0)
            ->whereDate('expiry_date', '<=', now()->addDays($threshold))
            ->count();

        return $needsReorder + $expiring;
    }
}
