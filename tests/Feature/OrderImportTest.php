<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrderImportTest extends TestCase
{
    public function test_order_import_endpoint_accepts_file(): void
    {
        $content = "0000000070                              Palmer Prosacco00000007530000000003     1836.7420210308\n";
        $file = UploadedFile::fake()->createWithContent('orders.txt', $content);

        $response = $this->postJson('/api/orders/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
        ]);
    }
}
