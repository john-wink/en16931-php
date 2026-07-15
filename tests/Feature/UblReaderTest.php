<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Reader\UblInvoiceReader;

function ublFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

it('reads the header, parties, lines and totals from UBL', function (): void {
    $invoice = (new UblInvoiceReader)->read(ublFixture('valid-ubl.xml'));

    expect($invoice->number)->toBe('R-2026-1')
        ->and($invoice->typeCode)->toBe('380')
        ->and($invoice->issueDate)->toBe('2026-01-15')
        ->and($invoice->currency)->toBe('EUR')
        ->and($invoice->buyerReference)->toBe('04011000-12345-06')
        ->and($invoice->seller->name)->toBe('Seller GmbH')
        ->and($invoice->seller->vatId)->toBe('DE123456789')
        ->and($invoice->seller->contactEmail)->toBe('billing@seller.de')
        ->and($invoice->buyer->name)->toBe('Buyer AG')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines[0]->name)->toBe('Beratung')
        ->and($invoice->lines[0]->unitCode)->toBe('C62')
        ->and($invoice->lines[0]->netAmount)->toBe('100.00')
        ->and($invoice->taxSubtotals)->toHaveCount(1)
        ->and($invoice->taxSubtotals[0]->taxableAmount)->toBe('100.00')
        ->and($invoice->totals->grandTotal)->toBe('119.00')
        ->and($invoice->totals->taxTotal)->toBe('19.00')
        ->and($invoice->notes)->toBe(['Vielen Dank fuer Ihren Auftrag.']);
});

it('validates a clean UBL invoice via validateUbl', function (): void {
    $result = En16931Validator::xrechnung()->validateUbl(ublFixture('valid-ubl.xml'));

    expect($result->isValid())->toBeTrue()
        ->and($result->violations)->toBe([]);
});

it('auto-detects UBL syntax in validate()', function (): void {
    expect(En16931Validator::xrechnung()->validate(ublFixture('valid-ubl.xml'))->isValid())->toBeTrue();
});

it('detects a tampered UBL total (BR-CO-15)', function (): void {
    $tampered = str_replace(
        '<cbc:TaxInclusiveAmount currencyID="EUR">119.00</cbc:TaxInclusiveAmount>',
        '<cbc:TaxInclusiveAmount currencyID="EUR">199.00</cbc:TaxInclusiveAmount>',
        ublFixture('valid-ubl.xml'),
    );

    expect(En16931Validator::xrechnung()->validate($tampered)->hasViolation('BR-CO-15'))->toBeTrue();
});

it('reads the tax representative, seller tax registration and allowance reason code', function (): void {
    $invoice = (new UblInvoiceReader)->read(ublFixture('taxrep-ubl.xml'));

    expect($invoice->seller->vatId)->toBe('DE123456789')
        ->and($invoice->seller->taxRegistrationId)->toBe('123/456/7890')
        ->and($invoice->taxRepresentative?->name)->toBe('Vertreter GmbH')
        ->and($invoice->taxRepresentative?->vatId)->toBe('DE999999999')
        ->and($invoice->taxRepresentative?->countryCode)->toBe('DE')
        ->and($invoice->allowanceCharges[0]->reasonCode)->toBe('95');
});

it('leaves the tax representative null when BG-11 is absent', function (): void {
    expect((new UblInvoiceReader)->read(ublFixture('valid-ubl.xml'))->taxRepresentative)->toBeNull();
});

it('reads the postal address and electronic address of a party', function (): void {
    $invoice = (new UblInvoiceReader)->read(ublFixture('taxrep-ubl.xml'));

    expect($invoice->seller->street)->toBe('Musterstraße 1')
        ->and($invoice->seller->city)->toBe('Berlin')
        ->and($invoice->seller->postCode)->toBe('10115')
        ->and($invoice->seller->electronicAddress)->toBe('DE123456789')
        ->and($invoice->seller->electronicAddressScheme)->toBe('9930');
});

it('reads the payment means from UBL', function (): void {
    $invoice = (new UblInvoiceReader)->read(ublFixture('valid-ubl.xml'));

    expect($invoice->paymentMeans)->toHaveCount(1)
        ->and($invoice->paymentMeans[0]->typeCode)->toBe('30')
        ->and($invoice->paymentMeans[0]->accountId)->toBe('DE02120300000000202051')
        ->and($invoice->paymentMeans[0]->hasCreditTransfer)->toBeTrue()
        ->and($invoice->paymentMeans[0]->hasCardInformation)->toBeFalse()
        ->and($invoice->paymentMeans[0]->hasDirectDebit)->toBeFalse();
});
