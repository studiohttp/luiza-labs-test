<?php

declare(strict_types=1);

namespace App\Modules\Order\Services;

use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Handlers\LegacyOrderParser;
use App\Modules\Order\Handlers\OrderAggregationHandler;
use App\Modules\Order\Jobs\ProcessLegacyOrderJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class LegacyOrderImporter implements OrderImporterInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly LegacyOrderParser $parser,
        private readonly OrderAggregationHandler $aggregator,
    ) {}

    public function import(UploadedFile $file): array
    {
        $storedPath = Storage::disk('local')->putFileAs(
            'queue_uploads',
            $file,
            Str::uuid()->toString().'.txt',
        );

        if ($storedPath === false) {
            throw new RuntimeException('Não foi possível armazenar o arquivo para processamento.');
        }

        Bus::dispatch(new ProcessLegacyOrderJob($storedPath, $file->getClientOriginalName()));

        return [
            'status' => 'queued',
            'file_name' => $file->getClientOriginalName(),
            'message' => 'Arquivo recebido e enfileirado para processamento.',
        ];
    }

    public function importFromPath(string $path, string $originalName): array
    {
        $stagingPath = tempnam(storage_path('app'), 'legacy_import_');

        if ($stagingPath === false) {
            throw new RuntimeException('Não foi possível criar o staging da importação.');
        }

        try {
            return $this->processWithDiskStaging($path, $originalName, $stagingPath);
        } finally {
            if (is_file($stagingPath)) {
                unlink($stagingPath);
            }
        }
    }

    private function processWithDiskStaging(string $path, string $originalName, string $stagingPath): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo para processamento.');
        }

        $database = new PDO('sqlite:'.$stagingPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $database->exec('PRAGMA journal_mode = OFF');
        $database->exec('PRAGMA synchronous = OFF');
        $database->exec(
            'CREATE TABLE entries (
                sequence INTEGER PRIMARY KEY,
                order_id INTEGER NOT NULL,
                raw_line TEXT NOT NULL
            )'
        );

        $insert = $database->prepare(
            'INSERT INTO entries (sequence, order_id, raw_line) VALUES (:sequence, :order_id, :raw_line)'
        );
        $lineNumber = 0;
        $validItems = 0;
        $invalidCount = 0;
        $invalidDetails = [];
        $invalidDetailLimit = max(0, (int) config('order_import.invalid_detail_limit', 100));
        $stagingCommitSize = max(1, (int) config('order_import.staging_commit_size', 1000));

        $database->beginTransaction();

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = rtrim($line, "\r\n");

                if ($line === '') {
                    continue;
                }

                try {
                    $entry = $this->parser->parseLine($line);
                    $insert->execute([
                        'sequence' => $lineNumber,
                        'order_id' => $entry->orderId,
                        'raw_line' => $line,
                    ]);
                    $validItems++;

                    if ($validItems % $stagingCommitSize === 0) {
                        $database->commit();
                        $database->beginTransaction();
                    }
                } catch (Throwable $exception) {
                    $invalidCount++;

                    if (count($invalidDetails) < $invalidDetailLimit) {
                        $invalidDetails[] = [
                            'line' => $lineNumber,
                            'error' => $exception->getMessage(),
                            'content' => mb_substr($line, 0, 120),
                        ];
                    }

                    Log::warning('Linha ignorada durante a importação', [
                        'line_number' => $lineNumber,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        } finally {
            fclose($handle);
        }

        $database->exec('CREATE INDEX entries_order_sequence_idx ON entries (order_id, sequence)');
        $rows = $database->query('SELECT raw_line FROM entries ORDER BY order_id, sequence');
        $batchSize = max(1, (int) config('order_import.batch_size', 1000));
        $batch = [];
        $currentOrder = [];
        $currentOrderId = null;
        $savedOrders = 0;
        $ordersProcessed = 0;

        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $entry = $this->parser->parseLine($row['raw_line']);

            if ($currentOrderId !== null && $entry->orderId !== $currentOrderId) {
                $batch[] = $currentOrder[$currentOrderId];
                $ordersProcessed++;
                $currentOrder = [];

                if (count($batch) >= $batchSize) {
                    $savedOrders += $this->repository->saveOrders($batch);
                    $batch = [];
                }
            }

            $currentOrderId = $entry->orderId;
            $currentOrder = $this->aggregator->aggregate($currentOrder, $entry);
        }

        if ($currentOrderId !== null) {
            $batch[] = $currentOrder[$currentOrderId];
            $ordersProcessed++;
        }

        if ($batch !== []) {
            $savedOrders += $this->repository->saveOrders($batch);
        }

        return [
            'file_name' => $originalName,
            'orders_processed' => $ordersProcessed,
            'items_processed' => $validItems,
            'saved_orders' => $savedOrders,
            'invalid_lines' => $invalidCount,
            'invalid_details' => $invalidDetails,
            'invalid_details_truncated' => $invalidCount > count($invalidDetails),
        ];
    }
}
