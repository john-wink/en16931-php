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

it('flags a missing total amount without VAT (BR-13)', function (): void {
    $result = core()->validateModel(makeInvoice(totals: new JohnWink\En16931\Model\Totals(
        lineTotal: '100.00', taxBasisTotal: null, taxTotal: '19.00',
        grandTotal: '119.00', paidAmount: '0.00', payableAmount: '119.00',
    )));

    expect($result->hasViolation('BR-13'))->toBeTrue();
});

it('flags a non-zero VAT rate on an exempt line (BR-E-05)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00',
        quantity: '1', unitCode: 'C62', taxCategory: 'E', taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-E-05'))->toBeTrue();
});

it('flags a positive amount due with no due date or payment terms (BR-CO-25)', function (): void {
    $result = core()->validateModel(makeInvoice(paymentDueDate: null, paymentTerms: null));

    expect($result->hasViolation('BR-CO-25'))->toBeTrue();
});

it('flags a line allowance without a reason (BR-42)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '95.00', netPrice: '100.00',
        quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [new JohnWink\En16931\Model\LineAllowanceCharge(isCharge: false, amount: '5.00')],
    )]));

    expect($result->hasViolation('BR-42'))->toBeTrue();
});

it('flags a seller with no identifier at all (BR-CO-26)', function (): void {
    $result = core()->validateModel(makeInvoice(
        seller: new Party(name: 'Seller GmbH', countryCode: 'DE'), // no VAT id, BT-29 or BT-30
    ));

    expect($result->hasViolation('BR-CO-26'))->toBeTrue();
});

it('flags a Standard document allowance with a zero rate (BR-S-06)', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '0.00'),
    ]));

    expect($result->hasViolation('BR-S-06'))->toBeTrue();
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

it('flags a reverse-charge group that carries a non-zero tax amount (BR-AE-09)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'AE', rate: '0.00', taxableAmount: '100.00', taxAmount: '19.00',
        exemptionReason: 'Reverse charge',
    )]));

    expect($result->hasViolation('BR-AE-09'))->toBeTrue();
});

it('flags a Standard group that carries an exemption reason (BR-S-10)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00',
        exemptionReason: 'should not be here',
    )]));

    expect($result->hasViolation('BR-S-10'))->toBeTrue();
});

it('flags a used line category with no matching breakdown group (BR-S-01)', function (): void {
    // Default line is category S, but the only breakdown group is E.
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00',
        exemptionReason: 'exempt',
    )]));

    expect($result->hasViolation('BR-S-01'))->toBeTrue();
});
