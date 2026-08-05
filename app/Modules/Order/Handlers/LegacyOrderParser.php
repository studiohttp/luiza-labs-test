<?php

declare(strict_types=1);

namespace App\Modules\Order\Handlers;

use App\Modules\Order\DTOs\ParsedLegacyLine;
use App\Modules\Order\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;

class LegacyOrderParser
{
    public function parseLine(string $line): ParsedLegacyLine
    {
        $line = rtrim($line, "\r\n");

        if (strlen($line) !== 95) {
            throw new InvalidArgumentException('Linha inválida: tamanho diferente de 95 caracteres.');
        }

        $userIdRaw = substr($line, 0, 10);
        $nameRaw = substr($line, 10, 45);
        $orderIdRaw = substr($line, 55, 10);
        $productIdRaw = substr($line, 65, 10);
        $valueRaw = substr($line, 75, 12);
        $dateRaw = substr($line, 87, 8);

        $name = trim($nameRaw);
        if ($name === '') {
            throw new InvalidArgumentException('Nome do usuário inválido.');
        }

        $userId = $this->parseId($userIdRaw, 'do usuário');
        $orderId = $this->parseId($orderIdRaw, 'do pedido');
        $productId = $this->parseId($productIdRaw, 'do produto');
        $value = Money::fromFixedWidth($valueRaw);

        $date = DateTimeImmutable::createFromFormat('!Ymd', $dateRaw);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Data de compra inválida.');
        }

        return new ParsedLegacyLine(
            $userId,
            $name,
            $orderId,
            $productId,
            $value,
            $date,
        );
    }

    private function parseId(string $raw, string $label): int
    {
        $value = trim($raw);

        if ($value === '' || ! ctype_digit($value)) {
            throw new InvalidArgumentException(sprintf('ID %s inválido.', $label));
        }

        return (int) $value;
    }
}
