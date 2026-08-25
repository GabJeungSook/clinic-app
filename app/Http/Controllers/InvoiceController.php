<?php

namespace App\Http\Controllers;

use App\Actions\Billing\GenerateReceipt;
use App\Actions\Billing\RecordPayment;
use App\Actions\Billing\RefundInvoice;
use App\Actions\Billing\VoidInvoice;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $invoices = Invoice::query()
            ->with('patient:id,first_name,last_name')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'patient' => $i->patient?->full_name,
                'status' => $i->status->value,
                'grand_total' => (float) $i->grand_total,
                'amount_paid' => (float) $i->amount_paid,
                'issued_at' => $i->issued_at?->toDateString(),
            ]);

        return Inertia::render('Billing/Index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search],
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $invoice->load('patient:id,first_name,last_name', 'items', 'payments.receiver:id,name', 'receipts', 'voider:id,name');

        return Inertia::render('Billing/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'patient' => $invoice->patient?->full_name,
                'status' => $invoice->status->value,
                'subtotal' => (float) $invoice->subtotal,
                'discount_total' => (float) $invoice->discount_total,
                'tax_total' => (float) $invoice->tax_total,
                'grand_total' => (float) $invoice->grand_total,
                'amount_paid' => (float) $invoice->amount_paid,
                'amount_due' => $invoice->amountDue(),
                'tax_enabled' => (bool) $invoice->tax_enabled,
                'tax_rate' => (float) $invoice->tax_rate_snapshot,
                'notes' => $invoice->notes,
                'issued_at' => $invoice->issued_at?->toDateString(),
                'void_reason' => $invoice->void_reason,
                'voided_at' => $invoice->voided_at?->toDateTimeString(),
                'voided_by' => $invoice->voider?->name,
            ],
            'can' => ['manage' => (bool) $request->user()?->can('billing.manage')],
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description_snapshot,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount,
                'tax' => (float) $i->tax,
                'line_total' => (float) $i->line_total,
            ]),
            'payments' => $invoice->payments->map(fn ($p) => [
                'method' => $p->method->value,
                'amount' => (float) $p->amount,
                'received_by' => $p->receiver?->name,
                'paid_at' => $p->paid_at?->toDateTimeString(),
            ]),
            'receipts' => $invoice->receipts->map(fn ($r) => ['id' => $r->id, 'receipt_no' => $r->receipt_no]),
            'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]),
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice, RecordPayment $record): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $record->handle(
            $invoice,
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            reference: $data['reference'] ?? null,
            receivedBy: $request->user()?->id,
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function void(Request $request, Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $void->handle($invoice, $data['reason'], voidedBy: $request->user()?->id);

        return back()->with('success', 'Invoice voided.');
    }

    public function refund(Request $request, Invoice $invoice, RefundInvoice $refund): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'restock' => ['boolean'],
        ]);

        $refund->handle(
            $invoice,
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            reason: $data['reason'] ?? null,
            restock: (bool) ($data['restock'] ?? false),
            performedBy: $request->user()?->id,
        );

        return back()->with('success', 'Refund recorded.');
    }

    public function generateReceipt(Invoice $invoice, GenerateReceipt $generate): RedirectResponse
    {
        $receipt = $generate->handle($invoice);

        return redirect()->route('receipts.show', $receipt->id)->with('success', 'Receipt generated.');
    }
}
