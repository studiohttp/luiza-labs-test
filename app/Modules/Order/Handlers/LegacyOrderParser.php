<?php

namespace App\Modules\Order\Handlers;

use InvalidArgumentException;

class LegacyOrderParser
{
    public function parseLine(string $line): array
    {
        if (strlen($line) < 95) {
            throw new InvalidArgumentException('Linha inválida: tamanho menor que o esperado.');
        }

        $orderId = trim(substr($line, 0, 10));
        $customerName = trim(substr($line, 10, 45));
        $productId = trim(substr($line, 55, 10));
        $quantityRaw = substr($line, 65, 10);
        $amountRaw = trim(substr($line, 80, 7));
        $dateRaw = trim(substr($line, 87, 8));

        if ($orderId === '' || ! ctype_digit($orderId)) {
            throw new InvalidArgumentException('ID do pedido inválido.');
        }

        if ($customerName === '') {
            throw new InvalidArgumentException('Nome do cliente inválido.');
        }

        if ($productId === '' || ! ctype_digit($productId)) {
            throw new InvalidArgumentException('Código do produto inválido.');
        }

        if ($quantityRaw === '' || ! ctype_digit(trim($quantityRaw))) {
            throw new InvalidArgumentException('Quantidade inválida.');
        }

        $quantity = intval(ltrim($quantityRaw, '0') ?: '0');
        $amount = str_replace(',', '.', $amountRaw);

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Valor do produto inválido.');
        }

        $amount = floatval($amount);
        $purchaseDate = \DateTimeImmutable::createFromFormat('Ymd', $dateRaw);

        if (! $purchaseDate) {
            throw new InvalidArgumentException('Data de compra inválida.');
        }

        return [
            'order_id' => $orderId,
            'customer_name' => $customerName,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $amount,
            'purchase_date' => $purchaseDate->format('Y-m-d'),
        ];
    }
}
