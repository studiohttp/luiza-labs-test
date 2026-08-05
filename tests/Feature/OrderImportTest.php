<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacyLineFactory;
use Tests\TestCase;

final class OrderImportTest extends TestCase
{
    public function test_import_accepts_a_text_file_asynchronously(): void
    {
        Storage::fake('local');
        Bus::fake();

        $response = $this->post('/api/orders/import', [
            'file' => UploadedFile::fake()->createWithContent('orders.txt', LegacyLineFactory::make()),
        ]);

        $response->assertAccepted()->assertJson([
            'status' => 'queued',
            'file_name' => 'orders.txt',
        ]);
        Bus::assertDispatched(ProcessLegacyOrderJob::class);
    }

    public function test_import_rejects_an_empty_file(): void
    {
        Storage::fake('local');

        $this->post('/api/orders/import', [
            'file' => UploadedFile::fake()->createWithContent('orders.txt', ''),
        ])->assertUnprocessable();
    }

    public function test_import_rejects_an_unexpected_extension(): void
    {
        Storage::fake('local');

        $this->postJson('/api/orders/import', [
            'file' => UploadedFile::fake()->createWithContent('orders.php', LegacyLineFactory::make()),
        ])->assertUnprocessable();
    }
}
