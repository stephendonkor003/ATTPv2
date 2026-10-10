<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class ExactMoney
{
    public const DATABASE_MAX = '9999999999999.99';

    public static function normalize(mixed $amount): string
    {
        try {
            return (string) BigDecimal::of(self::scalar($amount))
                ->toScale(2, RoundingMode::UNNECESSARY);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('A monetary amount must be an exact decimal with no more than two decimal places.', previous: $exception);
        }
    }

    public static function cents(mixed $amount): int
    {
        try {
            return BigDecimal::of(self::normalize($amount))
                ->withPointMovedRight(2)
                ->toBigInteger()
                ->toInt();
        } catch (MathException $exception) {
            throw new InvalidArgumentException('A monetary amount is outside the supported range.', previous: $exception);
        }
    }

    public static function fromCents(int $cents): string
    {
        return (string) BigDecimal::of($cents)
            ->withPointMovedLeft(2)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public static function multiply(mixed $amount, mixed $quantity): string
    {
        try {
            return (string) BigDecimal::of(self::normalize($amount))
                ->multipliedBy(BigDecimal::of(self::scalar($quantity)))
                ->toScale(2, RoundingMode::HALF_UP);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('A monetary line item could not be calculated exactly.', previous: $exception);
        }
    }

    public static function compareDecimal(mixed $left, mixed $right): int
    {
        try {
            return BigDecimal::of(self::scalar($left))->compareTo(BigDecimal::of(self::scalar($right)));
        } catch (MathException $exception) {
            throw new InvalidArgumentException('A decimal value could not be compared exactly.', previous: $exception);
        }
    }

    private static function scalar(mixed $value): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException('A decimal value must be a scalar number.');
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            throw new InvalidArgumentException('A decimal value cannot be empty.');
        }

        return $normalized;
    }
}
