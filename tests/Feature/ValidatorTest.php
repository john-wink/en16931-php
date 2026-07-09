<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;

function en(): En16931Validator
{
    return En16931Validator::en16931();
}

function xr(): En16931Validator
{
    return En16931Validator::xrechnung();
}

it('accepts a fully valid invoice under EN 16931 and XRechnung', function (): void {
    expect(en()->validateModel(makeInvoice())->isValid())->toBeTrue()
        ->and(xr()->validateModel(makeInvoice())->isValid())->toBeTrue();
});

it('flags a missing invoice number (BR-02)', function (): void {
    $result = en()->validateModel(makeInvoice(number: null));

    expect($result->isValid())->toBeFalse()
        ->and($result->hasViolation('BR-02'))->toBeTrue();
});

it('flags a broken line-total sum (BR-CO-10)', function (): void {
    $result = en()->validateModel(makeInvoice(totals: new Totals(
        lineTotal: '90.00', taxBasisTotal: '100.00', taxTotal: '19.00',
        grandTotal: '119.00', paidAmount: '0.00', payableAmount: '119.00',
    )));

    expect($result->hasViolation('BR-CO-10'))->toBeTrue();
});

it('flags a broken grand total (BR-CO-15)', function (): void {
    $result = en()->validateModel(makeInvoice(totals: new Totals(
        lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00',
        grandTotal: '120.00', paidAmount: '0.00', payableAmount: '120.00',
    )));

    expect($result->hasViolation('BR-CO-15'))->toBeTrue();
});

it('flags a negative item net price (BR-27)', function (): void {
    $result = en()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '-5.00', netPrice: '-5.00',
        quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-27'))->toBeTrue();
});

it('flags reverse charge without a buyer VAT id (BR-AE-03)', function (): void {
    $result = en()->validateModel(makeInvoice(
        buyer: new Party(name: 'Buyer AG', countryCode: 'DE', vatId: null),
        taxSubtotals: [new TaxSubtotal(category: 'AE', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Reverse charge')],
    ));

    expect($result->hasViolation('BR-AE-03'))->toBeTrue();
});

it('flags an invalid invoice type code (BR-CL-01)', function (): void {
    expect(en()->validateModel(makeInvoice(typeCode: '999'))->hasViolation('BR-CL-01'))->toBeTrue();
});

it('requires the Leitweg-ID only under the XRechnung rule set (BR-DE-1)', function (): void {
    $invoice = makeInvoice(buyerReference: null);

    expect(en()->validateModel($invoice)->hasViolation('BR-DE-1'))->toBeFalse()
        ->and(xr()->validateModel($invoice)->hasViolation('BR-DE-1'))->toBeTrue();
});
