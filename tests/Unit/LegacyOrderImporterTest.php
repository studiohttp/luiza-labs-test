<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\LegacyLineFactory;
use Tests\TestCase;

final class LegacyOrderImporterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_upload_is_stored_with_safe_name_and_enqueued(): void
    {
        Storage::fake('local');
        Bus::fake();
        $importer = $this->importer(Mockery::mock(OrderRepositoryInterface::class));

        $result = $importer->import(
            UploadedFile::fake()->createWithContent('../../orders.txt', LegacyLineFactory::make())
        );

        $this->assertSame('queued', $result['status']);
        Bus::assertDispatched(ProcessLegacyOrderJob::class, function (ProcessLegacyOrderJob $job): bool {
            return str_starts_with($job->path, 'queue_uploads/')
                && preg_match('/^[a-f0-9-]+\.txt$/', basename($job->path)) === 1;
        });
    }

    public function test_it_processes_in_bounded_batches_and_keeps_repeated_product_ids(): void
    {
        config()->set('order_import.batch_size', 1);
        $content = implode("\n", [
            LegacyLineFactory::make(orderId: 10, productId: 3, value: '10.00'),
            LegacyLineFactory::make(orderId: 11, productId: 4, value: '5.00'),
            LegacyLineFactory::make(orderId: 10, productId: 3, value: '2.50'),
            'corrupted',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'legacy_test_');
        file_put_contents($path, $content);

        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('saveOrders')->twice()->withArgs(function (array $orders): bool {
            if ($orders[0]['order_id'] !== 10) {
                return $orders[0]['order_id'] === 11;
            }

            return count($orders[0]['products']) === 2 && $orders[0]['total'] === '12.50';
        })->andReturn(1);

        try {
            $result = $this->importer($repository)->importFromPath($path, 'orders.txt');
        } finally {
            unlink($path);
        }

        $this->assertSame(2, $result['orders_processed']);
        $this->assertSame(3, $result['items_processed']);
        $this->assertSame(2, $result['saved_orders']);
        $this->assertSame(1, $result['invalid_lines']);
    }

    private function importer(OrderRepositoryInterface $repository): LegacyOrderImporter
    {
        return new LegacyOrderImporter(
            $repository,
            new LegacyOrderParser,
            new OrderAggregationHandler,
        );
    }
}
