<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt): Response
    {
        return Inertia::render('Billing/Receipt', [
            'receipt' => [
                'receipt_no' => $receipt->receipt_no,
                'printed_at' => $receipt->printed_at?->toDateTimeString(),
                'snapshot' => $receipt->snapshot,
            ],
        ]);
    }
}
