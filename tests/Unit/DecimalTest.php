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

it('reads xs:decimal values and ignores only XML whitespace', function (?string $value, ?string $expected): void {
    expect(Decimal::fromXsDecimal($value))->toBe($expected);
})->with([
    'plain' => ['19.00', '19.00'],
    'spaces' => [' 19.00 ', '19.00'],
    'tab, carriage return and line feed' => ["\t-19.00\r\n", '-19.00'],
    'leading plus' => ['+19.00', '19.00'],
    'no integer digits' => ['.50', '0.50'],
    'trailing dot' => ['19.', '19'],
    'no-break space' => ["\u{00A0}19.00\u{00A0}", null],
    'decimal comma' => ['19,00', null],
    'exponent' => ['1e2', null],
    'sign only' => ['-', null],
    'empty' => ['', null],
    'null' => [null, null],
]);
