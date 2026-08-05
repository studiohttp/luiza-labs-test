<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Order\DTOs\ParsedLegacyLine;
use App\Modules\Order\Handlers\LegacyOrderParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LegacyLineFactory;
use Tests\TestCase;

final class LegacyOrderParserTest extends TestCase
{
    public function test_it_parses_the_official_fixed_width_layout(): void
    {
        $entry = (new LegacyOrderParser)->parseLine(LegacyLineFactory::make());

        $this->assertInstanceOf(ParsedLegacyLine::class, $entry);
        $this->assertSame(70, $entry->userId);
        $this->assertSame('Palmer Prosacco', $entry->name);
        $this->assertSame(753, $entry->orderId);
        $this->assertSame(3, $entry->productId);
        $this->assertSame('1836.74', $entry->value->format());
        $this->assertSame('2021-03-08', $entry->date->format('Y-m-d'));
    }

    public function test_it_normalizes_one_decimal_place_and_one_cent(): void
    {
        $parser = new LegacyOrderParser;

        $this->assertSame('80.80', $parser->parseLine(LegacyLineFactory::make(value: '80.8'))->value->format());
        $this->assertSame('0.01', $parser->parseLine(LegacyLineFactory::make(value: '0.01'))->value->format());
    }

    #[DataProvider('invalidLines')]
    public function test_it_rejects_corrupted_lines(string $line): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LegacyOrderParser)->parseLine($line);
    }

    public static function invalidLines(): array
    {
        return [
            'short line' => ['short'],
            'long line' => [LegacyLineFactory::make().'x'],
            'invalid user id' => ['ABCDEFGHIJ'.substr(LegacyLineFactory::make(), 10)],
            'invalid money' => [substr_replace(LegacyLineFactory::make(), '       12,3x', 75, 12)],
            'impossible date' => [substr_replace(LegacyLineFactory::make(), '20210230', 87, 8)],
        ];
    }
}
