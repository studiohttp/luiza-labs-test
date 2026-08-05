<?php

namespace Tests\Unit;

use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use Tests\TestCase;

class OrderAggregationHandlerTest extends TestCase
{
    public function test_aggregate_groups_products_for_same_order(): void
    {
        $parser = new LegacyOrderParser;
        $aggregator = new OrderAggregationHandler;

        $first = $parser->parseLine('0000000070                              Palmer Prosacco00000007530000000003     1836.7420210308');
        $second = $parser->parseLine('0000000070                              Palmer Prosacco00000007530000000004     1210.5020210308');

        $orders = [];
        $orders = $aggregator->aggregate($orders, $first);
        $orders = $aggregator->aggregate($orders, $second);

        $this->assertCount(1, $orders);
        $this->assertSame(753, $orders[753]['order_id']);
        $this->assertSame(70, $orders[753]['user_id']);
        $this->assertSame('Palmer Prosacco', $orders[753]['name']);
        $this->assertSame('2021-03-08', $orders[753]['date']);
        $this->assertSame('3047.24', $orders[753]['total']);
        $this->assertCount(2, $orders[753]['products']);
        $this->assertSame(3, $orders[753]['products'][0]['product_id']);
        $this->assertSame('1836.74', $orders[753]['products'][0]['value']);
        $this->assertSame(4, $orders[753]['products'][1]['product_id']);
        $this->assertSame('1210.50', $orders[753]['products'][1]['value']);
    }
}
