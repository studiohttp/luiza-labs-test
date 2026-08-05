<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use App\Modules\Order\Exceptions\MongoUnavailableException;

class LegacyOrderImporter implements OrderImporterInterface
{
    public function __construct(
        protected OrderRepositoryInterface $repository,
        protected LegacyOrderParser $parser,
        protected OrderAggregationHandler $aggregator
    ) {
    }

    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->path(), 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo enviado.');
        }

        $lineNumber = 0;
        $invalidLines = [];
        $orders = [];
        $items = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            try {
                $entry = $this->parser->parseLine($line);
                $orders = $this->aggregator->aggregate($orders, $entry);
                $items++;
            } catch (\Throwable $e) {
                $invalidLines[] = [
                    'line' => $lineNumber,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Linha do arquivo ignorada durante importação', [
                    'line_number' => $lineNumber,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        try {
            $savedOrders = $this->repository->saveOrders(array_values($orders));

            return [
                'file_name' => $file->getClientOriginalName(),
                'orders_processed' => count($orders),
                'items_processed' => $items,
                'saved_orders' => $savedOrders,
                'invalid_lines' => count($invalidLines),
                'invalid_details' => $invalidLines,
            ];
        } catch (MongoUnavailableException $e) {
            // Persistir arquivo para processamento assíncrono via fila (Redis)
            $storePath = Storage::putFileAs('queue_uploads', $file, uniqid('legacy_') . '_' . $file->getClientOriginalName());

            // Dispatch job to process later
            Bus::dispatch(new ProcessLegacyOrderJob($storePath, $file->getClientOriginalName()));

            return [
                'file_name' => $file->getClientOriginalName(),
                'status' => 'queued',
                'message' => 'Arquivo enfileirado para processamento assíncrono. Será processado quando o banco estiver disponível.',
                'queue_path' => $storePath,
                'invalid_lines' => count($invalidLines),
                'invalid_details' => $invalidLines,
            ];
        }
    }

    public function importFromPath(string $path, string $originalName): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo no caminho: ' . $path);
        }

        $lineNumber = 0;
        $invalidLines = [];
        $orders = [];
        $items = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            try {
                $entry = $this->parser->parseLine($line);
                $orders = $this->aggregator->aggregate($orders, $entry);
                $items++;
            } catch (\Throwable $e) {
                $invalidLines[] = [
                    'line' => $lineNumber,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Linha do arquivo ignorada durante importação (fila)', [
                    'line_number' => $lineNumber,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        // attempt to save; may throw MongoUnavailableException which should be handled by worker retry
        $savedOrders = $this->repository->saveOrders(array_values($orders));

        return [
            'file_name' => $originalName,
            'orders_processed' => count($orders),
            'items_processed' => $items,
            'saved_orders' => $savedOrders,
            'invalid_lines' => count($invalidLines),
            'invalid_details' => $invalidLines,
        ];
    }
}
