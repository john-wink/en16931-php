<?php

declare(strict_types=1);

namespace JohnWink\En16931;

/**
 * Exact decimal arithmetic over BCMath for the EN 16931 calculation rules. All
 * amounts are handled as strings straight from the XML — never floats — so the
 * tolerance-free (BR-CO-*) comparisons are exact. The engine works at the
 * EN 16931 default of two decimal places.
 */
final class Decimal
{
    public const int SCALE = 2;

    /**
     * @phpstan-assert-if-true numeric-string $value
     */
    public static function isNumeric(?string $value): bool
    {
        return $value !== null && preg_match('/^-?\d+(\.\d+)?$/', mb_trim($value)) === 1;
    }

    /**
     * @param  numeric-string  $augend
     * @param  numeric-string  $addend
     * @return numeric-string
     */
    public static function add(string $augend, string $addend, int $scale = self::SCALE): string
    {
        return bcadd($augend, $addend, $scale);
    }

    /**
     * @param  numeric-string  $minuend
     * @param  numeric-string  $subtrahend
     * @return numeric-string
     */
    public static function sub(string $minuend, string $subtrahend, int $scale = self::SCALE): string
    {
        return bcsub($minuend, $subtrahend, $scale);
    }

    /**
     * Multiply at high precision (no intermediate rounding); the caller rounds.
     *
     * @param  numeric-string  $multiplicand
     * @param  numeric-string  $multiplier
     * @return numeric-string
     */
    public static function mul(string $multiplicand, string $multiplier, int $scale = 8): string
    {
        return bcmul($multiplicand, $multiplier, $scale);
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     */
    public static function compare(string $left, string $right, int $scale = self::SCALE): int
    {
        return bccomp($left, $right, $scale);
    }

    /**
     * @param  numeric-string  $value
     */
    public static function isNegative(string $value): bool
    {
        return bccomp($value, '0', self::SCALE) < 0;
    }

    /**
     * Commercial half-up rounding (away from zero on a tie), implemented by
     * nudging by half a unit at the target scale and letting BCMath truncate.
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $nudge = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);

        return str_starts_with($value, '-')
            ? bcsub($value, $nudge, $scale)
            : bcadd($value, $nudge, $scale);
    }

    /**
     * Whether two amounts are equal once both are rounded to the given scale
     * (the EN 16931 comparison for the calculation rules).
     *
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     */
    public static function equals(string $left, string $right, int $scale = self::SCALE): bool
    {
        return bccomp(self::round($left, $scale), self::round($right, $scale), $scale) === 0;
    }
}
