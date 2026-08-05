<?php

namespace Tests\Unit;

use App\Modules\Order\Exceptions\MongoUnavailableException;
use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class LegacyOrderImporterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_import_processes_valid_file_and_returns_saved_orders(): void
    {
        $content = "0000000070                              Palmer Prosacco00000007530000000003     1836.7420210308\n";
        $file = UploadedFile::fake()->createWithContent('orders.txt', $content);

        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('saveOrders')
            ->once()
            ->withArgs(function (array $orders) {
                return count($orders) === 1
                    && $orders[0]['order_id'] === '0000000070'
                    && $orders[0]['customer_name'] === 'Palmer Prosacco'
                    && $orders[0]['total_amount'] === 5510.22
                    && count($orders[0]['items']) === 1;
            })
            ->andReturn(1);

        $importer = new LegacyOrderImporter(
            $repository,
            new LegacyOrderParser(),
            new OrderAggregationHandler(),
        );

        $result = $importer->import($file);

        $this->assertSame('orders.txt', $result['file_name']);
        $this->assertSame(1, $result['orders_processed']);
        $this->assertSame(1, $result['items_processed']);
        $this->assertSame(1, $result['saved_orders']);
        $this->assertSame(0, $result['invalid_lines']);
        $this->assertSame([], $result['invalid_details']);
    }

    public function test_import_enqueues_when_mongo_is_unavailable(): void
    {
        Storage::fake('local');
        Bus::fake();

        $content = "0000000070                              Palmer Prosacco00000007530000000003     1836.7420210308\n";
        $file = UploadedFile::fake()->createWithContent('orders.txt', $content);

        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('saveOrders')
            ->once()
            ->andThrow(new MongoUnavailableException('MongoDB indisponível'));

        $importer = new LegacyOrderImporter(
            $repository,
            new LegacyOrderParser(),
            new OrderAggregationHandler(),
        );

        $result = $importer->import($file);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('orders.txt', $result['file_name']);
        $this->assertSame(0, $result['invalid_lines']);
        $this->assertStringContainsString('queue_uploads', $result['queue_path']);

        Bus::assertDispatched(ProcessLegacyOrderJob::class);
    }

    public function test_import_from_path_reads_file_and_saves_orders(): void
    {
        $content = "0000000070                              Palmer Prosacco00000007530000000003     1836.7420210308\n";
        $path = storage_path('app/test_order_import.txt');
        file_put_contents($path, $content);

        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('saveOrders')
            ->once()
            ->withArgs(function (array $orders) {
                return count($orders) === 1
                    && $orders[0]['order_id'] === '0000000070';
            })
            ->andReturn(1);

        $importer = new LegacyOrderImporter(
            $repository,
            new LegacyOrderParser(),
            new OrderAggregationHandler(),
        );

        $result = $importer->importFromPath($path, 'orders.txt');

        unlink($path);

        $this->assertSame('orders.txt', $result['file_name']);
        $this->assertSame(1, $result['orders_processed']);
        $this->assertSame(1, $result['items_processed']);
        $this->assertSame(1, $result['saved_orders']);
        $this->assertSame(0, $result['invalid_lines']);
    }
}
