<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        protected OrderImporterInterface $importer,
        protected OrderRepositoryInterface $repository
    ) {
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');

        if (! $file || ! $file->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload inválido. Verifique o arquivo enviado.',
            ], 422);
        }

        Log::info('Início da importação de pedidos', [
            'client_ip' => $request->ip(),
            'original_name' => $file->getClientOriginalName(),
        ]);

        try {
            $summary = $this->importer->import($file);

            return response()->json([
                'status' => 'ok',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            Log::error('Falha ao importar pedidos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível processar o arquivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['order_id', 'date_start', 'date_end']);
        $orders = $this->repository->query($filters);

        return response()->json([
            'status' => 'ok',
            'filters' => $filters,
            'count' => count($orders),
            'data' => $orders,
        ]);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = $this->repository->find($orderId);

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => "Pedido {$orderId} não encontrado.",
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $order,
        ]);
    }
}
