<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Tenant staff — transaction list (payments.view). */
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
}
