<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Reader\CiiInvoiceReader;

function ciiFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

it('reads the header, parties, lines and totals from CII', function (): void {
    $invoice = (new CiiInvoiceReader)->read(ciiFixture('valid-cii.xml'));

    expect($invoice->number)->toBe('R-2026-1')
        ->and($invoice->typeCode)->toBe('380')
        ->and($invoice->issueDate)->toBe('2026-01-15')       // format 102 normalized
        ->and($invoice->currency)->toBe('EUR')
        ->and($invoice->buyerReference)->toBe('04011000-12345-06')
        ->and($invoice->seller->name)->toBe('Seller GmbH')
        ->and($invoice->seller->vatId)->toBe('DE123456789')
        ->and($invoice->seller->contactEmail)->toBe('billing@seller.de')
        ->and($invoice->buyer->name)->toBe('Buyer AG')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines[0]->netAmount)->toBe('100.00')
        ->and($invoice->totals->grandTotal)->toBe('119.00')
        ->and($invoice->totals->taxTotal)->toBe('19.00')
        ->and($invoice->notes)->toBe(['Vielen Dank fuer Ihren Auftrag.']);
});

it('validates the fixture as a clean XRechnung', function (): void {
    $result = En16931Validator::xrechnung()->validateCii(ciiFixture('valid-cii.xml'));

    expect($result->isValid())->toBeTrue()
        ->and($result->violations)->toBe([]);
});

it('detects tampered totals in a real CII payload (BR-CO-15)', function (): void {
    $tampered = str_replace(
        '<ram:GrandTotalAmount>119.00</ram:GrandTotalAmount>',
        '<ram:GrandTotalAmount>199.00</ram:GrandTotalAmount>',
        ciiFixture('valid-cii.xml'),
    );

    $result = En16931Validator::xrechnung()->validateCii($tampered);

    expect($result->hasViolation('BR-CO-15'))->toBeTrue();
});
