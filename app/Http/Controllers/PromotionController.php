<?php

namespace App\Http\Controllers;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $promotions = Promotion::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type->value,
                'value' => (float) $p->value,
                'applies_to' => $p->applies_to->value,
                'min_spend' => $p->min_spend !== null ? (float) $p->min_spend : null,
                'valid_from' => $p->valid_from?->toDateString(),
                'valid_to' => $p->valid_to?->toDateString(),
                'max_uses' => $p->max_uses,
                'used_count' => $p->used_count,
                'is_active' => (bool) $p->is_active,
                'status' => $this->status($p),
            ]);

        return Inertia::render('Billing/Promotions', [
            'promotions' => $promotions,
            'filters' => ['search' => $search],
            'types' => collect(PromotionType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'scopes' => collect(PromotionScope::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    /** Human-readable lifecycle status for the list. */
    private function status(Promotion $p): string
    {
        $today = now();

        if (! $p->is_active) {
            return 'inactive';
        }
        if ($p->max_uses !== null && $p->used_count >= $p->max_uses) {
            return 'used_up';
        }
        if ($p->valid_from && $today->lt($p->valid_from->startOfDay())) {
            return 'scheduled';
        }
        if ($p->valid_to && $today->gt($p->valid_to->endOfDay())) {
            return 'expired';
        }

        return 'active';
    }

    public function store(Request $request): RedirectResponse
    {
        Promotion::create($this->validated($request));

        return back()->with('success', 'Promotion created.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($this->validated($request));

        return back()->with('success', 'Promotion updated.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        return back()->with('success', 'Promotion removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'applies_to' => ['required', Rule::enum(PromotionScope::class)],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
    }
}
