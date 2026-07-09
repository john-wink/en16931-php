<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\TaxSubtotal;

function core(): En16931Validator
{
    return En16931Validator::en16931();
}

it('flags a missing specification identifier (BR-01)', function (): void {
    expect(core()->validateModel(makeInvoice(customizationId: null))->hasViolation('BR-01'))->toBeTrue();
});

it('flags an invalid currency code (BR-CL-03)', function (): void {
    expect(core()->validateModel(makeInvoice(currency: 'ZZZ'))->hasViolation('BR-CL-03'))->toBeTrue();
});

it('accepts a real ISO currency', function (): void {
    // CHF is a valid 2-decimal ISO currency and should not trip BR-CL-03.
    expect(core()->validateModel(makeInvoice(currency: 'CHF'))->hasViolation('BR-CL-03'))->toBeFalse();
});

it('flags an invalid seller country code (BR-CL-14)', function (): void {
    $result = core()->validateModel(makeInvoice(
        seller: new Party(name: 'S', countryCode: 'XX', vatId: 'DE1'),
    ));

    expect($result->hasViolation('BR-CL-14'))->toBeTrue();
});

it('flags a line missing its item name (BR-25)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: null, netAmount: '100.00', netPrice: '100.00',
        quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-25'))->toBeTrue();
});

it('flags a VAT breakdown missing its taxable amount (BR-45)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', rate: '19.00', taxableAmount: null, taxAmount: '19.00',
    )]));

    expect($result->hasViolation('BR-45'))->toBeTrue();
});

it('flags an inconsistent category tax amount (BR-CO-17)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '99.00',
    )]));

    expect($result->hasViolation('BR-CO-17'))->toBeTrue();
});

it('accepts a correct category tax amount', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00',
    )]));

    expect($result->hasViolation('BR-CO-17'))->toBeFalse();
});

it('flags an exempt group without an exemption reason (BR-E-10)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00',
    )]));

    expect($result->hasViolation('BR-E-10'))->toBeTrue();
});

it('accepts an exempt group that carries an exemption reason', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00',
        exemptionReason: 'Kleinunternehmer §19 UStG',
    )]));

    expect($result->hasViolation('BR-E-10'))->toBeFalse();
});
