<?php

namespace App\Http\Controllers\Api;

use App\Services\Analytics\ServerEventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController
{
    public function store(Request $request, ServerEventTracker $tracker): JsonResponse
    {
        $input = $request->validate([
            'item_id' => ['required', 'string', 'in:margherita,rigatoni,tiramisu'],
            'quantity' => ['required', 'integer', 'between:1,10'],
            'visitor_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
            'session_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
        ]);
        $item = MenuController::ITEMS[$input['item_id']];
        $createdAt = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $order = [
            'order_id' => (string) Str::uuid(),
            'item_id' => $input['item_id'],
            'item_name' => $item['name'],
            'quantity' => (int) $input['quantity'],
            'total_cents' => $item['price_cents'] * (int) $input['quantity'],
            'visitor_id' => $input['visitor_id'],
            'session_id' => $input['session_id'],
            'created_at' => $createdAt,
        ];

        DB::transaction(function () use ($order, $tracker): void {
            DB::table('orders')->insert($order);
            $tracker->track(
                'order_created',
                $order['visitor_id'],
                $order['session_id'],
                [
                    'order_id' => $order['order_id'],
                    'item_id' => $order['item_id'],
                    'quantity' => $order['quantity'],
                    'total_cents' => $order['total_cents'],
                ],
            );
        });

        return response()->json(['order' => $order, 'analytics' => 'tracked'], 201);
    }
}
