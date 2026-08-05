<?php

declare(strict_types=1);

namespace App\Modules\Order\Handlers;

use App\Modules\Order\DTOs\ParsedLegacyLine;
use App\Modules\Order\ValueObjects\Money;
use Illuminate\Support\Facades\Log;

class OrderAggregationHandler
{
    public function aggregate(array $orders, ParsedLegacyLine $entry): array
    {
        $orderId = $entry->orderId;
        $date = $entry->date->format('Y-m-d');
        $product = [
            'product_id' => $entry->productId,
            'value' => $entry->value->format(),
        ];

        if (! isset($orders[$orderId])) {
            $orders[$orderId] = [
                'order_id' => $orderId,
                'user_id' => $entry->userId,
                'name' => $entry->name,
                'date' => $date,
                'total' => $entry->value->format(),
                'products' => [$product],
            ];

            return $orders;
        }

        $existingOrder = &$orders[$orderId];

        if ($existingOrder['user_id'] !== $entry->userId || $existingOrder['name'] !== $entry->name) {
            Log::warning('Pedido encontrado com usuário inconsistente', [
                'order_id' => $orderId,
                'existing_user_id' => $existingOrder['user_id'],
                'new_user_id' => $entry->userId,
                'existing_name' => $existingOrder['name'],
                'new_name' => $entry->name,
            ]);
        }

        if ($existingOrder['date'] !== $date) {
            Log::warning('Pedido encontrado com datas diferentes', [
                'order_id' => $orderId,
                'existing_date' => $existingOrder['date'],
                'new_date' => $date,
            ]);
        }

        $existingOrder['products'][] = $product;
        $existingOrder['total'] = Money::fromFixedWidth($existingOrder['total'])
            ->add($entry->value)
            ->format();

        return $orders;
    }
}
