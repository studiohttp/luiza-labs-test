<?php

namespace App\Modules\Order\Contracts;

interface OrderRepositoryInterface
{
    public function saveOrders(array $orders): int;

    public function query(array $filters = []): array;

    public function find(string $orderId): ?array;
}
