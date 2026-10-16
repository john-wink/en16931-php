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

it('accepts only values that BCMath accepts', function (string $value, bool $numeric): void {
    expect(Decimal::isNumeric($value))->toBe($numeric);

    if (Decimal::isNumeric($value)) {
        expect(Decimal::add($value, '0'))->toBeString()
            ->and(Decimal::compare($value, '0'))->toBeInt();
    }
})->with([
    'plain' => ['19.00', true],
    'negative' => ['-19.00', true],
    'integer' => ['19', true],
    'spaces' => [' 19.00 ', false],
    'leading space' => [' 19.00', false],
    'trailing line feed' => ["19.00\n", false],
    'tab' => ["\t19.00", false],
    'no-break space' => ["\u{00A0}19.00\u{00A0}", false],
    'ideographic space' => ["\u{3000}19.00\u{3000}", false],
    'leading plus' => ['+19.00', false],
    'no integer digits' => ['.50', false],
    'trailing dot' => ['19.', false],
    'decimal comma' => ['19,00', false],
    'exponent' => ['1e2', false],
    'empty' => ['', false],
]);

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

it('splits every value into missing, filled but not an xs:decimal, or readable', function (?string $value, bool $filled, bool $unreadable, ?string $reading): void {
    expect(Decimal::isFilled($value))->toBe($filled)
        ->and(Decimal::isFilledButNotXsDecimal($value))->toBe($unreadable)
        ->and(Decimal::fromXsDecimal($value))->toBe($reading)
        ->and(Decimal::isFilledButNotXsDecimal($value))->toBe(Decimal::isFilled($value) && Decimal::fromXsDecimal($value) === null)
        ->and(Decimal::fromXsDecimal($value) === null || Decimal::isFilled($value))->toBeTrue();
})->with([
    'null' => [null, false, false, null],
    'empty' => ['', false, false, null],
    'only XML whitespace' => [" \t\r\n", false, false, null],
    'only a no-break space' => ["\u{00A0}", false, false, null],
    'only an ideographic space' => ["\u{3000}", false, false, null],
    'no-break and ideographic space mixed with XML whitespace' => ["\u{00A0} \u{3000}\t", false, false, null],
    'framed by no-break spaces' => ["\u{00A0}19\u{00A0}", true, true, null],
    'framed by no-break spaces with decimals' => ["\u{00A0}19.00\u{00A0}", true, true, null],
    'framed by ideographic spaces' => ["\u{3000}19\u{3000}", true, true, null],
    'no-break space inside XML whitespace' => [" \u{00A0}19 ", true, true, null],
    'decimal comma' => ['19,00', true, true, null],
    'sign only' => ['-', true, true, null],
    'text' => ['abc', true, true, null],
    'readable' => ['19.00', true, false, '19.00'],
    'readable inside XML whitespace' => [" \t19\r\n", true, false, '19'],
    'readable with a leading plus' => ['+19', true, false, '19'],
]);
