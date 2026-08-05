<?php

namespace App\Modules\Order\Jobs;

use App\Modules\Order\Services\LegacyOrderImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLegacyOrderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected string $path;
    protected string $originalName;

    public function __construct(string $path, string $originalName)
    {
        $this->path = $path;
        $this->originalName = $originalName;
    }

    public function handle(LegacyOrderImporter $importer): void
    {
        try {
            $fullPath = storage_path('app/' . $this->path);
            $importer->importFromPath($fullPath, $this->originalName);
            Log::info('Arquivo processado a partir da fila', ['file' => $this->path]);
        } catch (\Throwable $e) {
            Log::error('Falha ao processar arquivo da fila', ['file' => $this->path, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
