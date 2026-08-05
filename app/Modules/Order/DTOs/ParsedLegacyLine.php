<?php

declare(strict_types=1);

namespace App\Modules\Order\DTOs;

use App\Modules\Order\ValueObjects\Money;
use DateTimeImmutable;

final class ParsedLegacyLine
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly int $orderId,
        public readonly int $productId,
        public readonly Money $value,
        public readonly DateTimeImmutable $date,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'value' => $this->value->format(),
            'date' => $this->date->format('Y-m-d'),
        ];
    }
}
