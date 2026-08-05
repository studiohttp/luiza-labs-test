<?php

namespace App\Modules\Order\Repositories;

use App\Modules\Order\Adapters\MongoAdapter;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use App\Modules\Order\Exceptions\MongoUnavailableException;

class OrderRepository implements OrderRepositoryInterface
{
    protected string $mongoUri;
    protected string $mongoDatabase;
    protected string $fallbackPath;
    protected ?MongoAdapter $adapter;

    public function __construct(MongoAdapter $adapter = null)
    {
        $this->mongoUri = env('MONGO_URI', 'mongodb://127.0.0.1:27017');
        $this->mongoDatabase = env('MONGO_DB', 'luiza_labs');
        $this->fallbackPath = storage_path('app/orders.json');
        $this->adapter = $adapter;
    }

    public function saveOrders(array $orders): int
    {
        if (empty($orders)) {
            return 0;
        }
        if ($this->isMongoAvailable()) {
            try {
                return $this->saveToMongo($orders);
            } catch (\Throwable $e) {
                Log::warning('Falha ao persistir em MongoDB.', ['error' => $e->getMessage()]);
                throw new MongoUnavailableException($e->getMessage());
            }
        }

        throw new MongoUnavailableException('MongoDB indisponível');
    }

    public function query(array $filters = []): array
    {
        
            if ($this->isMongoAvailable()) {
                try {
                    return $this->queryMongo($filters);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao consultar MongoDB — usando consulta em arquivo.', ['error' => $e->getMessage()]);
                    return $this->queryFile($filters);
                }
            }

            return $this->queryFile($filters);
    }

    public function find(string $orderId): ?array
    {
        
            if ($this->isMongoAvailable()) {
                try {
                    return $this->findMongo($orderId);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao buscar pedido no MongoDB — usando fallback em arquivo.', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                }
            }

            $orders = $this->loadFileOrders();

            return $orders[$orderId] ?? null;
    }

    protected function isMongoAvailable(): bool
    {
        if (! class_exists(Manager::class)) {
            return false;
        }

        if ($this->adapter instanceof MongoAdapter) {
            return $this->adapter->isConnected();
        }

        // no adapter provided, assume not available to avoid unexpected exceptions
        return false;
    }

    protected function saveToMongo(array $orders): int
    {
        $bulk = new BulkWrite();

        foreach ($orders as $order) {
            $bulk->update(
                ['order_id' => $order['order_id']],
                ['$set' => $order],
                ['upsert' => true],
            );
        }

        $this->manager()->executeBulkWrite("{$this->mongoDatabase}.orders", $bulk);

        return count($orders);
    }

    protected function queryMongo(array $filters): array
    {
        $query = new Query($this->buildFilter($filters));
        $cursor = $this->manager()->executeQuery("{$this->mongoDatabase}.orders", $query);

        return array_map(
            static fn ($item) => json_decode(json_encode($item), true),
            iterator_to_array($cursor),
        );
    }

    protected function findMongo(string $orderId): ?array
    {
        $query = new Query(['order_id' => $orderId], ['limit' => 1]);
        $cursor = $this->manager()->executeQuery("{$this->mongoDatabase}.orders", $query);
        $documents = iterator_to_array($cursor);

        if (empty($documents)) {
            return null;
        }

        return json_decode(json_encode($documents[0]), true);
    }

    protected function saveToFile(array $orders): int
    {
        $current = $this->loadFileOrders();

        foreach ($orders as $order) {
            $current[$order['order_id']] = $order;
        }

        if (! is_dir(dirname($this->fallbackPath))) {
            mkdir(dirname($this->fallbackPath), 0755, true);
        }

        file_put_contents(
            $this->fallbackPath,
            json_encode(array_values($current), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        Log::info('Armazenamento de fallback usado para persistência de pedidos.', ['path' => $this->fallbackPath]);

        return count($orders);
    }

    protected function queryFile(array $filters): array
    {
        $orders = array_values($this->loadFileOrders());
        $filter = $this->buildFilter($filters);

        return array_values(array_filter($orders, static function (array $order) use ($filter): bool {
            if (isset($filter['order_id']) && $order['order_id'] !== $filter['order_id']) {
                return false;
            }

            if (isset($filter['purchase_date'])) {
                $date = $order['purchase_date'];
                $range = $filter['purchase_date'];

                if (isset($range['$gte']) && $date < $range['$gte']) {
                    return false;
                }

                if (isset($range['$lte']) && $date > $range['$lte']) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected function loadFileOrders(): array
    {
        if (! file_exists($this->fallbackPath)) {
            return [];
        }

        $content = file_get_contents($this->fallbackPath);
        $orders = json_decode($content, true);

        if (! is_array($orders)) {
            return [];
        }

        return array_column($orders, null, 'order_id');
    }

    protected function buildFilter(array $filters): array
    {
        $query = [];

        if (! empty($filters['order_id'])) {
            $query['order_id'] = trim($filters['order_id']);
        }

        if (! empty($filters['date_start']) || ! empty($filters['date_end'])) {
            $range = [];

            if (! empty($filters['date_start'])) {
                $range['$gte'] = trim($filters['date_start']);
            }

            if (! empty($filters['date_end'])) {
                $range['$lte'] = trim($filters['date_end']);
            }

            if (! empty($range)) {
                $query['purchase_date'] = $range;
            }
        }

        return $query;
    }

    protected function manager(): Manager
    {
        if ($this->adapter instanceof MongoAdapter && $this->adapter->isConnected()) {
            return $this->adapter->getManager();
        }

        // fallback: attempt to create a manager directly (may throw)
        return new Manager($this->mongoUri);
    }
}
