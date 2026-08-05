<?php

declare(strict_types=1);

namespace App\Modules\Order\Jobs;

use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessLegacyOrderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 1200;

    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
    ) {}

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(LegacyOrderImporter $importer): void
    {
        $fullPath = Storage::disk('local')->path($this->path);

        if (! Storage::disk('local')->exists($this->path)) {
            throw new \RuntimeException('Arquivo pendente não encontrado.');
        }

        $summary = $importer->importFromPath($fullPath, $this->originalName);
        Storage::disk('local')->delete($this->path);

        Log::info('Arquivo processado a partir da fila', [
            'file' => $this->path,
            'summary' => $summary,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha definitiva ao processar arquivo da fila', [
            'file' => $this->path,
            'error' => $exception->getMessage(),
        ]);
    }
}
