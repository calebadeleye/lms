<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Services\RefundService;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        private RefundService $refunds,
        private AuditLogger $auditLogger,
    ) {}

    public function store(Request $request, string $paymentId)
    {
        $payment = Payment::where('status', 'success')->findOrFail($paymentId);

        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1', 'max:'.$payment->gross_amount_cents],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $refund = $this->refunds->refund($payment, $request->user(), $data['amount_cents'] ?? null, $data['reason'] ?? null);

        $this->auditLogger->log('payment.refunded', metadata: [
            'payment_id' => $payment->id, 'amount_cents' => $refund->amount_cents,
        ]);

        return response()->json(['data' => $refund], 201);
    }
}
