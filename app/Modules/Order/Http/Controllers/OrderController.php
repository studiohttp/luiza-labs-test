<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Contracts\OrderImporterInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        protected OrderImporterInterface $importer,
        protected OrderRepositoryInterface $repository
    ) {}

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'extensions:txt',
                'max:'.config('order_import.max_upload_kilobytes'),
            ],
        ]);

        $file = $request->file('file');

        if (! $file || ! $file->isValid() || $file->getSize() === 0) {
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

            return response()->json($summary, 202);
        } catch (\Throwable $e) {
            $traceId = Str::uuid()->toString();
            Log::error('Falha ao importar pedidos', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível processar o arquivo.',
                'trace_id' => $traceId,
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'order_id' => 'nullable|digits_between:1,20',
            'date_start' => 'nullable|date_format:Y-m-d',
            'date_end' => 'nullable|date_format:Y-m-d|after_or_equal:date_start',
        ]);

        $orders = $this->repository->query($filters);

        $users = [];

        foreach ($orders as $order) {
            $userKey = (string) $order['user_id'];

            if (! isset($users[$userKey])) {
                $users[$userKey] = [
                    'user_id' => $order['user_id'],
                    'name' => $order['name'],
                    'orders' => [],
                ];
            }

            $users[$userKey]['orders'][] = [
                'order_id' => $order['order_id'],
                'date' => $order['date'],
                'total' => $order['total'],
                'products' => $order['products'],
            ];
        }

        return response()->json(array_values($users));
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
            'user_id' => $order['user_id'],
            'name' => $order['name'],
            'orders' => [
                [
                    'order_id' => $order['order_id'],
                    'date' => $order['date'],
                    'total' => $order['total'],
                    'products' => $order['products'],
                ],
            ],
        ]);
    }
}
