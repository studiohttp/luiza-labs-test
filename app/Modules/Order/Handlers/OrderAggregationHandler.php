<?php

namespace App\Modules\Order\Handlers;

use Illuminate\Support\Facades\Log;

class OrderAggregationHandler
{
    public function aggregate(array $orders, array $entry): array
    {
        $orderId = $entry['order_id'];

        if (! isset($orders[$orderId])) {
            $orders[$orderId] = [
                'order_id' => $orderId,
                'customer_name' => $entry['customer_name'],
                'purchase_date' => $entry['purchase_date'],
                'items' => [],
                'total_amount' => 0.0,
            ];
        }

        if ($orders[$orderId]['purchase_date'] !== $entry['purchase_date']) {
            Log::warning('Data diferente encontrada para o mesmo pedido', [
                'order_id' => $orderId,
                'existing_date' => $orders[$orderId]['purchase_date'],
                'new_date' => $entry['purchase_date'],
            ]);
        }

        $lineTotal = $entry['unit_price'] * $entry['quantity'];

        $orders[$orderId]['items'][] = [
            'product_id' => $entry['product_id'],
            'quantity' => $entry['quantity'],
            'unit_price' => $entry['unit_price'],
            'line_total' => $lineTotal,
        ];

        $orders[$orderId]['total_amount'] += $lineTotal;

        return $orders;
    }
}
