<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        $expenses = Expense::query()
            ->with('recorder:id,name')
            ->latest('spent_at')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'description' => $e->description,
                'category' => $e->category,
                'amount' => (float) $e->amount,
                'spent_at' => $e->spent_at?->toDateString(),
                'by' => $e->recorder?->name,
            ]);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'monthTotal' => (float) Expense::query()
                ->whereBetween('spent_at', [$monthStart, $monthEnd])
                ->sum('amount'),
            'monthLabel' => $monthStart->format('F Y'),
            'currency' => Settings::get('billing.currency_symbol', '₱'),
            'categories' => ['Supplies', 'Utilities', 'Food/Meals', 'Transport', 'Maintenance', 'Salary/Allowance', 'Miscellaneous'],
            'canManage' => $request->user()?->can('expenses.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('expenses.manage'), 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['recorded_by'] = $request->user()?->id;
        Expense::create($data);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()?->can('expenses.manage'), 403);

        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense removed.');
    }
}
