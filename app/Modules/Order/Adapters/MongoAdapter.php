<?php

namespace App\Modules\Order\Adapters;

use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;

class MongoAdapter
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
            // perform a lightweight ping to validate connectivity
            $cmd = new Command(['ping' => 1]);
            $this->manager->executeCommand('admin', $cmd);
            $this->connected = true;
        } catch (\Throwable $e) {
            Log::warning('Falha ao conectar ao MongoDB', ['uri' => $uri, 'error' => $e->getMessage()]);
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
}
