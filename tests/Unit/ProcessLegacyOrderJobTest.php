<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\LegacyLineFactory;
use Tests\TestCase;

final class ProcessLegacyOrderJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_deletes_the_temporary_file_after_success(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('queue_uploads/test.txt', LegacyLineFactory::make());
        $importer = Mockery::mock(LegacyOrderImporter::class);
        $importer->shouldReceive('importFromPath')->once()->andReturn(['saved_orders' => 1]);

        (new ProcessLegacyOrderJob('queue_uploads/test.txt', 'orders.txt'))->handle($importer);

        Storage::disk('local')->assertMissing('queue_uploads/test.txt');
    }

    public function test_it_preserves_the_file_when_processing_fails(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('queue_uploads/test.txt', LegacyLineFactory::make());
        $importer = Mockery::mock(LegacyOrderImporter::class);
        $importer->shouldReceive('importFromPath')->once()->andThrow(new RuntimeException('Mongo unavailable'));

        try {
            (new ProcessLegacyOrderJob('queue_uploads/test.txt', 'orders.txt'))->handle($importer);
            $this->fail('The job should propagate the processing failure.');
        } catch (RuntimeException) {
            Storage::disk('local')->assertExists('queue_uploads/test.txt');
        }
    }
}
