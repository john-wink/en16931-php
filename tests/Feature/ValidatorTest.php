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
        lineTotal: '90.00',
        taxBasisTotal: '100.00',
        taxTotal: '19.00',
        grandTotal: '119.00',
        paidAmount: '0.00',
        payableAmount: '119.00',
    )));

    expect($result->hasViolation('BR-CO-10'))->toBeTrue();
});

it('flags a broken grand total (BR-CO-15)', function (): void {
    $result = en()->validateModel(makeInvoice(totals: new Totals(
        lineTotal: '100.00',
        taxBasisTotal: '100.00',
        taxTotal: '19.00',
        grandTotal: '120.00',
        paidAmount: '0.00',
        payableAmount: '120.00',
    )));

    expect($result->hasViolation('BR-CO-15'))->toBeTrue();
});

it('flags a negative item net price (BR-27)', function (): void {
    $result = en()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '-5.00',
        netPrice: '-5.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'S',
        taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-27'))->toBeTrue();
});

it('flags a reverse-charge line without buyer identification (BR-AE-02)', function (): void {
    $result = en()->validateModel(makeInvoice(
        buyer: new Party(name: 'Buyer AG', countryCode: 'DE', vatId: null),
        lines: [new InvoiceLine(
            id: '1',
            name: 'x',
            netAmount: '100.00',
            netPrice: '100.00',
            quantity: '1',
            unitCode: 'C62',
            taxCategory: 'AE',
            taxRate: '0.00',
        )],
        taxSubtotals: [new TaxSubtotal(category: 'AE', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Reverse charge')],
    ));

    expect($result->hasViolation('BR-AE-02'))->toBeTrue();
});

it('flags an invalid invoice type code (BR-CL-01)', function (): void {
    expect(en()->validateModel(makeInvoice(typeCode: '999'))->hasViolation('BR-CL-01'))->toBeTrue();
});

it('requires the Leitweg-ID only under the XRechnung rule set (BR-DE-15)', function (): void {
    $invoice = makeInvoice(buyerReference: null);

    expect(en()->validateModel($invoice)->hasViolation('BR-DE-15'))->toBeFalse()
        ->and(xr()->validateModel($invoice)->hasViolation('BR-DE-15'))->toBeTrue();
});

// ---- Schritt 1: XRechnung-Regeln ----

it('requires a seller contact group under XRechnung (BR-DE-2)', function (): void {
    $invoice = makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789'));

    expect(en()->validateModel($invoice)->hasViolation('BR-DE-2'))->toBeFalse()
        ->and(xr()->validateModel($invoice)->hasViolation('BR-DE-2'))->toBeTrue();
});

it('requires seller VAT, tax registration or a tax representative (BR-DE-16)', function (): void {
    $invoice = makeInvoice(seller: new Party(
        name: 'S', countryCode: 'DE', identifier: 'X',
        contactName: 'Max', contactPhone: '+49 30 000000', contactEmail: 'billing@seller.de',
    ));

    expect(xr()->validateModel($invoice)->hasViolation('BR-DE-16'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-16'))->toBeFalse();
});

it('warns about unusual invoice type codes (BR-DE-17)', function (): void {
    expect(xr()->validateModel(makeInvoice(typeCode: '385'))->hasViolation('BR-DE-17'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice(typeCode: '380'))->hasViolation('BR-DE-17'))->toBeFalse()
        ->and(xr()->validateModel(makeInvoice(typeCode: '385'))->isValid())->toBeTrue();
});

it('validates the Skonto format in the payment terms (BR-DE-18)', function (): void {
    $valid = "Zahlbar innerhalb 30 Tagen.\n#SKONTO#TAGE=7#PROZENT=2.00#\n";
    $wrongDecimals = "#SKONTO#TAGE=7#PROZENT=2#\n";
    $missingLineBreak = '#SKONTO#TAGE=7#PROZENT=2.00#';

    expect(xr()->validateModel(makeInvoice(paymentTerms: $valid))->hasViolation('BR-DE-18'))->toBeFalse()
        ->and(xr()->validateModel(makeInvoice(paymentTerms: $wrongDecimals))->hasViolation('BR-DE-18'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice(paymentTerms: $missingLineBreak))->hasViolation('BR-DE-18'))->toBeTrue();
});

it('warns when BT-24 is not the XRechnung specification identifier (BR-DE-21)', function (): void {
    $xrechnung = makeInvoice(customizationId: 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0');

    expect(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-21'))->toBeTrue()
        ->and(xr()->validateModel($xrechnung)->hasViolation('BR-DE-21'))->toBeFalse();
});

it('warns about a phone number with fewer than three digits (BR-DE-27)', function (): void {
    $invoice = makeInvoice(seller: new Party(
        name: 'S', countryCode: 'DE', vatId: 'DE123456789',
        contactName: 'Max', contactPhone: 'null', contactEmail: 'billing@seller.de',
    ));

    expect(xr()->validateModel($invoice)->hasViolation('BR-DE-27'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-27'))->toBeFalse();
});

it('warns about a malformed contact email (BR-DE-28)', function (): void {
    $invoice = makeInvoice(seller: new Party(
        name: 'S', countryCode: 'DE', vatId: 'DE123456789',
        contactName: 'Max', contactPhone: '+49 30 000000', contactEmail: 'a@b',
    ));

    expect(xr()->validateModel($invoice)->hasViolation('BR-DE-28'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-28'))->toBeFalse();
});

it('requires city and post code in present addresses under XRechnung (BR-DE-3/4/8/9)', function (): void {
    $invoice = makeInvoice(
        seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', contactName: 'Max', contactPhone: '+49 30 000000', contactEmail: 'billing@seller.de'),
        buyer: new Party(name: 'B', countryCode: 'DE', vatId: 'DE987654321'),
    );

    $result = xr()->validateModel($invoice);

    expect($result->hasViolation('BR-DE-3'))->toBeTrue()
        ->and($result->hasViolation('BR-DE-4'))->toBeTrue()
        ->and($result->hasViolation('BR-DE-8'))->toBeTrue()
        ->and($result->hasViolation('BR-DE-9'))->toBeTrue()
        ->and(en()->validateModel($invoice)->hasViolation('BR-DE-3'))->toBeFalse();
});

it('does not require a city when no address group is present at all (BR-DE-3)', function (): void {
    // Without any postal address BR-08 owns the failure; BR-DE-3 must stay silent.
    $invoice = makeInvoice(seller: new Party(name: 'S', vatId: 'DE123456789', contactName: 'Max', contactPhone: '+49 30 000000', contactEmail: 'billing@seller.de'));

    $result = xr()->validateModel($invoice);

    expect($result->hasViolation('BR-DE-3'))->toBeFalse()
        ->and($result->hasViolation('BR-08'))->toBeTrue();
});

it('requires payment instructions on every XRechnung (BR-DE-1)', function (): void {
    $invoice = makeInvoice(paymentMeans: []);

    expect(en()->validateModel($invoice)->hasViolation('BR-DE-1'))->toBeFalse()
        ->and(xr()->validateModel($invoice)->hasViolation('BR-DE-1'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-1'))->toBeFalse();
});

it('requires credit transfer details for transfer codes (BR-DE-23-a/b)', function (): void {
    $withoutAccount = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '58')]));
    $withCard = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '30', hasCreditTransfer: true, accountId: 'DE02120300000000202051', hasCardInformation: true)]));

    expect($withoutAccount->hasViolation('BR-DE-23-a'))->toBeTrue()
        ->and($withCard->hasViolation('BR-DE-23-b'))->toBeTrue();
});

it('requires card information for card codes (BR-DE-24-a/b)', function (): void {
    $withoutCard = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '48')]));
    $withTransfer = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '54', hasCardInformation: true, hasCreditTransfer: true)]));

    expect($withoutCard->hasViolation('BR-DE-24-a'))->toBeTrue()
        ->and($withTransfer->hasViolation('BR-DE-24-b'))->toBeTrue();
});

it('requires direct debit details for code 59 (BR-DE-25-a/b, BR-DE-30, BR-DE-31)', function (): void {
    $withoutMandate = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '59')]));
    $mandate = new JohnWink\En16931\Model\PaymentMeans(typeCode: '59', hasDirectDebit: true, debitedAccountId: 'DE02120300000000202051');
    $withoutCreditor = xr()->validateModel(makeInvoice(paymentMeans: [$mandate]));
    $complete = xr()->validateModel(makeInvoice(paymentMeans: [$mandate], sepaCreditorId: 'DE98ZZZ09999999999'));
    $withTransfer = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '59', hasDirectDebit: true, hasCreditTransfer: true)]));

    expect($withoutMandate->hasViolation('BR-DE-25-a'))->toBeTrue()
        ->and($withoutCreditor->hasViolation('BR-DE-30'))->toBeTrue()
        ->and($complete->hasViolation('BR-DE-30'))->toBeFalse()
        ->and($complete->hasViolation('BR-DE-31'))->toBeFalse()
        ->and($withTransfer->hasViolation('BR-DE-25-b'))->toBeTrue()
        ->and($withTransfer->hasViolation('BR-DE-31'))->toBeTrue();
});

it('checks the IBAN of SEPA transfers and debits (BR-DE-19, BR-DE-20)', function (): void {
    $badTransferIban = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '58', hasCreditTransfer: true, accountId: 'DE00123456781234567890')]));
    $goodTransferIban = xr()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '58', hasCreditTransfer: true, accountId: 'DE02120300000000202051')]));
    $badDebitIban = xr()->validateModel(makeInvoice(
        paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '59', hasDirectDebit: true, debitedAccountId: 'XX00WRONG')],
        sepaCreditorId: 'DE98ZZZ09999999999',
    ));

    expect($badTransferIban->hasViolation('BR-DE-19'))->toBeTrue()
        ->and($goodTransferIban->hasViolation('BR-DE-19'))->toBeFalse()
        ->and($badDebitIban->hasViolation('BR-DE-20'))->toBeTrue();
});
