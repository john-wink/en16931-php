<?php

declare(strict_types=1);

use JohnWink\En16931\Decimal;

it('rounds half up, away from zero', function (): void {
    expect(Decimal::round('2.005'))->toBe('2.01')
        ->and(Decimal::round('2.004'))->toBe('2.00')
        ->and(Decimal::round('-2.005'))->toBe('-2.01');
});

it('compares by two-decimal rounded equality', function (): void {
    expect(Decimal::equals('100.00', '100.004'))->toBeTrue()
        ->and(Decimal::equals('100.00', '100.005'))->toBeFalse()
        ->and(Decimal::equals('100.00', '100.01'))->toBeFalse();
});

it('recognises decimal strings', function (): void {
    expect(Decimal::isNumeric('19.00'))->toBeTrue()
        ->and(Decimal::isNumeric('-5'))->toBeTrue()
        ->and(Decimal::isNumeric('1,00'))->toBeFalse()
        ->and(Decimal::isNumeric(null))->toBeFalse();
});
