<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class LegacyLineFactory
{
    public static function make(
        int $userId = 70,
        string $name = 'Palmer Prosacco',
        int $orderId = 753,
        int $productId = 3,
        string $value = '1836.74',
        string $date = '20210308',
    ): string {
        $line = str_pad((string) $userId, 10, '0', STR_PAD_LEFT)
            .str_pad($name, 45, ' ', STR_PAD_LEFT)
            .str_pad((string) $orderId, 10, '0', STR_PAD_LEFT)
            .str_pad((string) $productId, 10, '0', STR_PAD_LEFT)
            .str_pad($value, 12, ' ', STR_PAD_LEFT)
            .$date;

        if (strlen($line) !== 95) {
            throw new RuntimeException('A fixture legada deve possuir exatamente 95 caracteres.');
        }

        return $line;
    }
}
