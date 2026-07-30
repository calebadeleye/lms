<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Commerce\Services\CommissionService;
use App\Domain\Commerce\Services\OrderFulfillmentService;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /** Staff — transaction list (payments.view). */
    public function index(Request $request)
    {
        $orders = Order::with('user:id,name,email', 'items', 'payment')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $orders->items(),
            'meta' => ['pagination' => ['page' => $orders->currentPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total()]],
        ]);
    }

    /** Student — their own order/receipt history. */
    public function mine(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items', 'payment')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Staff — manually re-checks a stuck "pending" order directly
     * with the payment provider and fulfils it if it actually succeeded.
     * The normal path is the webhook; this exists for when that never
     * arrives (most commonly: the webhook URL was never configured on the
     * provider's dashboard — see PaymentConfig::webhookUrl()).
     */
    public function reconcile(
        string $orderId,
        PaymentProviderFactory $providers,
        CommissionService $commission,
        OrderFulfillmentService $fulfillment,
    ) {
        $order = Order::findOrFail($orderId);
        $config = PaymentConfig::where('status', 'active')->first();

        if (! $config) {
            throw ValidationException::withMessages([
                'order' => ['No active payment configuration to verify against.'],
            ]);
        }

        $provider = $providers->forConfig($config);
        $result = $fulfillment->reconcileWithProvider($order, $config, $provider, $commission);

        return response()->json(['data' => ['order' => $order->fresh('items', 'payment'), 'result' => $result]]);
    }
}
