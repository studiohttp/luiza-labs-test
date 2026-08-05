<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Order\Contracts\OrderRepositoryInterface;
use Mockery;
use Tests\TestCase;

final class OrderQueryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_the_official_user_order_product_contract(): void
    {
        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('query')->once()->with([
            'order_id' => '753',
            'date_start' => '2021-01-01',
            'date_end' => '2021-12-31',
        ])->andReturn([
            [
                'user_id' => 70,
                'name' => 'Palmer Prosacco',
                'order_id' => 753,
                'total' => '1836.74',
                'date' => '2021-03-08',
                'products' => [['product_id' => 3, 'value' => '1836.74']],
            ],
        ]);
        $this->app->instance(OrderRepositoryInterface::class, $repository);

        $response = $this->getJson(
            '/api/orders?order_id=753&date_start=2021-01-01&date_end=2021-12-31'
        );

        $response->assertOk()->assertExactJson([
            [
                'user_id' => 70,
                'name' => 'Palmer Prosacco',
                'orders' => [[
                    'order_id' => 753,
                    'date' => '2021-03-08',
                    'total' => '1836.74',
                    'products' => [['product_id' => 3, 'value' => '1836.74']],
                ]],
            ],
        ]);
        $this->assertArrayNotHasKey('_id', $response->json()[0]);
        $this->assertArrayNotHasKey('_id', $response->json()[0]['orders'][0]);
    }

    public function test_it_rejects_an_inverted_date_range(): void
    {
        $this->getJson('/api/orders?date_start=2021-12-31&date_end=2021-01-01')
            ->assertUnprocessable();
    }
}
