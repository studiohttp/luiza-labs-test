<?php

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
            $fullPath = Storage::disk('local')->path($this->path);

            if (! Storage::disk('local')->exists($this->path)) {
                throw new \RuntimeException('Arquivo de fila não encontrado: ' . $fullPath);
            }

            $importer->importFromPath($fullPath, $this->originalName);
            Log::info('Arquivo processado a partir da fila', ['file' => $this->path, 'path' => $fullPath]);
        } catch (\Throwable $e) {
            Log::error('Falha ao processar arquivo da fila', ['file' => $this->path, 'path' => $fullPath ?? null, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
