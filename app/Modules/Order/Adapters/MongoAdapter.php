<?php

declare(strict_types=1);

namespace App\Modules\Order\Adapters;

use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;

final class MongoAdapter
{
    protected ?Manager $manager = null;

    protected bool $connected = false;

    public function __construct(string $uri)
    {
        try {
            if (! class_exists(Manager::class)) {
                Log::warning('MongoDB Manager não disponível (extensão ausente).');
                $this->connected = false;

                return;
            }

            $this->manager = new Manager($uri);
            $cmd = new Command(['ping' => 1]);
            $this->manager->executeCommand('admin', $cmd);
            $this->connected = true;
        } catch (\Throwable $e) {
            Log::warning('Falha ao conectar ao MongoDB', ['error' => $e->getMessage()]);
            $this->manager = null;
            $this->connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function getManager(): Manager
    {
        if (! $this->connected || $this->manager === null) {
            throw new \RuntimeException('MongoDB não está conectado.');
        }

        return $this->manager;
    }

    public function ensureIndexes(string $database): void
    {
        $this->getManager()->executeCommand($database, new Command([
            'createIndexes' => 'orders',
            'indexes' => [
                ['key' => ['order_id' => 1], 'name' => 'order_id_unique', 'unique' => true],
                ['key' => ['date' => 1], 'name' => 'date_idx'],
                ['key' => ['user_id' => 1], 'name' => 'user_id_idx'],
            ],
        ]));
    }
}
