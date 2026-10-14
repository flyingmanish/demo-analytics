<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class MenuController
{
    public const ITEMS = [
        'margherita' => ['name' => 'Garden Margherita', 'price_cents' => 1450],
        'rigatoni' => ['name' => 'Sunday Rigatoni', 'price_cents' => 1750],
        'tiramisu' => ['name' => 'House Tiramisu', 'price_cents' => 850],
    ];

    public function index(): JsonResponse
    {
        $items = [];
        foreach (self::ITEMS as $id => $item) {
            $items[] = ['id' => $id, ...$item];
        }

        return response()->json($items);
    }
}
