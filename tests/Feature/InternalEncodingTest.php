<?php

declare(strict_types=1);

use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Reader\CiiInvoiceReader;
use JohnWink\En16931\Reader\UblInvoiceReader;

$underEncoding = static function (string $encoding, Closure $callback): mixed {
    $previous = mb_internal_encoding();
    mb_internal_encoding($encoding);

    try {
        return $callback();
    } finally {
        mb_internal_encoding($previous);
    }
};

$fixture = static fn (string $name): string => (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);

$encodings = [
    'UTF-8' => ['UTF-8'],
    'ISO-8859-1' => ['ISO-8859-1'],
];

$syntaxes = [
    'UBL' => [static fn (): UblInvoiceReader => new UblInvoiceReader, 'valid-ubl.xml', '<cbc:CompanyID>DE123456789</cbc:CompanyID>', '<cbc:CompanyID>%s</cbc:CompanyID>', '<cbc:RegistrationName>Seller GmbH</cbc:RegistrationName>', '<cbc:RegistrationName>%s</cbc:RegistrationName>'],
    'CII' => [static fn (): CiiInvoiceReader => new CiiInvoiceReader, 'valid-cii.xml', '<ram:ID schemeID="VA">DE123456789</ram:ID>', '<ram:ID schemeID="VA">%s</ram:ID>', '<ram:Name>Seller GmbH</ram:Name>', '<ram:Name>%s</ram:Name>'],
];

it('reads a seller VAT identifier of only U+00A0 as missing whatever the internal encoding is', function (string $encoding, Closure $reader, string $file, string $vatId, string $vatIdTemplate) use ($underEncoding, $fixture): void {
    $xml = str_replace($vatId, sprintf($vatIdTemplate, "\u{00A0}"), $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->seller->vatId)->toBeNull()
        ->and($invoice->seller->hasVatId())->toBeFalse();
})->with($encodings)->with($syntaxes);

it('reads a seller name framed by U+00A0 without the frame whatever the internal encoding is', function (string $encoding, Closure $reader, string $file, string $vatId, string $vatIdTemplate, string $name, string $nameTemplate) use ($underEncoding, $fixture): void {
    $xml = str_replace($name, sprintf($nameTemplate, "\u{00A0}Seller GmbH\u{00A0}"), $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->seller->name)->toBe('Seller GmbH');
})->with($encodings)->with($syntaxes);

it('reads a currencyID framed by U+00A0 without the frame whatever the internal encoding is', function (string $encoding, Closure $reader, string $file) use ($underEncoding, $fixture): void {
    $xml = str_replace('currencyID="EUR"', "currencyID=\"\u{00A0}EUR\u{00A0}\"", $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->amountCurrencyCodes)->toBe(['EUR']);
})->with($encodings)->with($syntaxes);

it('trims rule inputs framed by U+00A0 as UTF-8 whatever the internal encoding is', function (string $encoding, Closure $scenario, string $ruleId, bool $violates) use ($underEncoding): void {
    $hasViolation = $underEncoding($encoding, static fn (): bool => xr()->validateModel($scenario())->hasViolation($ruleId));

    expect($hasViolation)->toBe($violates);
})->with($encodings)->with([
    'credit transfer code without an account (BR-61)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}30\u{00A0}")]), 'BR-61', true],
    'SEPA credit transfer code without an account (BR-50)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}58\u{00A0}", hasCreditTransfer: true)]), 'BR-50', true],
    'SEPA credit transfer code with an account that is no IBAN (BR-DE-19)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}58\u{00A0}", accountId: 'fixture-account-not-iban', hasCreditTransfer: true)]), 'BR-DE-19', true],
    'seller VAT identifier with a country prefix (BR-CO-09)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: "\u{00A0}DE123456789\u{00A0}", contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'BR-CO-09', false],
    'card number of ten digits (BR-51)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: "\u{00A0}1234567890\u{00A0}")]), 'BR-51', false],
    'VATEX code (BR-CL-22)' => [static fn (): Invoice => makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00', exemptionReasonCode: "\u{00A0}VATEX-EU-O\u{00A0}")]), 'BR-CL-22', false],
    'external document location (BR-TMP-2)' => [static fn (): Invoice => makeInvoice(attachments: [new Attachment(reference: 'DOC-1', externalUri: "\u{00A0}https://example.com/doc.pdf\u{00A0}")]), 'BR-TMP-2', false],
]);
