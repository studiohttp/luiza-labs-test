<?php

declare(strict_types=1);

namespace App\Modules\Order\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private int $cents;

    private function __construct(int $cents)
    {
        $this->cents = $cents;
    }

    public static function fromFixedWidth(string $raw): self
    {
        $value = trim($raw);

        if ($value === '') {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }

        if (! preg_match('/^\d+\.\d{1,2}$/', $value)) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }

        [$integer, $decimals] = explode('.', $value, 2);
        $decimals = str_pad($decimals, 2, '0');

        $cents = intval($integer) * 100 + intval($decimals);

        return new self($cents);
    }

    public function getCents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function format(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }
}
