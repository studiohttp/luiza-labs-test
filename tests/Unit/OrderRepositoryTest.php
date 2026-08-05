<?php

namespace Tests\Unit;

use App\Modules\Order\Repositories\OrderRepository;
use App\Modules\Order\Exceptions\MongoUnavailableException;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    public function test_save_orders_throws_mongo_unavailable_when_adapter_is_missing(): void
    {
        $repository = new OrderRepository();

        $this->expectException(MongoUnavailableException::class);
        $this->expectExceptionMessage('MongoDB indisponível');

        $repository->saveOrders([
            [
                'order_id' => '0000000070',
                'customer_name' => 'Palmer Prosacco',
                'purchase_date' => '2021-03-08',
                'items' => [],
                'total_amount' => 1836.74,
            ],
        ]);
    }

    public function test_find_returns_null_when_no_fallback_file_exists(): void
    {
        $fallbackPath = storage_path('app/orders.json');
        if (file_exists($fallbackPath)) {
            unlink($fallbackPath);
        }

        $repository = new OrderRepository();

        $result = $repository->find('0000000070');

        $this->assertNull($result);
    }

    public function test_query_returns_empty_array_when_no_fallback_file_exists(): void
    {
        $fallbackPath = storage_path('app/orders.json');
        if (file_exists($fallbackPath)) {
            unlink($fallbackPath);
        }

        $repository = new OrderRepository();

        $this->assertSame([], $repository->query([]));
    }

    public function test_query_returns_filtered_orders_from_fallback_file(): void
    {
        $fallbackPath = storage_path('app/orders.json');
        if (! is_dir(dirname($fallbackPath))) {
            mkdir(dirname($fallbackPath), 0755, true);
        }

        $orders = [
            [
                'order_id' => '0000000070',
                'customer_name' => 'Palmer Prosacco',
                'purchase_date' => '2021-03-08',
                'items' => [],
                'total_amount' => 1836.74,
            ],
            [
                'order_id' => '0000000071',
                'customer_name' => 'Jorge Silva',
                'purchase_date' => '2021-03-09',
                'items' => [],
                'total_amount' => 100.0,
            ],
        ];

        file_put_contents($fallbackPath, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new OrderRepository();

        $result = $repository->query(['order_id' => '0000000070']);

        unlink($fallbackPath);

        $this->assertCount(1, $result);
        $this->assertSame('0000000070', $result[0]['order_id']);
    }
}
