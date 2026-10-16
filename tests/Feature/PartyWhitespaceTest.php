<?php

declare(strict_types=1);
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;
use JohnWink\En16931\Severity;
use JohnWink\En16931\ValidationResult;
use JohnWink\En16931\Violation;

$hasFatal = static fn (ValidationResult $validationResult, string $ruleId): bool => array_any($validationResult->violations, static fn (Violation $violation): bool => $violation->ruleId === $ruleId && $violation->severity === Severity::Fatal);

$seller = static fn (?string $vatId = 'DE123456789', ?string $identifier = null): Party => new Party(
    name: 'Seller GmbH',
    countryCode: 'DE',
    vatId: $vatId,
    identifier: $identifier,
    contactName: 'Max Muster',
    contactPhone: '+49 30 000000',
    contactEmail: 'billing@seller.de',
    city: 'Berlin',
    postCode: '10115',
);

$blanks = [
    'only a no-break space' => ["\u{00A0}"],
    'only an ideographic space' => ["\u{3000}"],
    'no-break and ideographic spaces' => ["\u{00A0}\u{3000}\u{00A0}"],
];

it('treats a party field of only U+00A0 or U+3000 as missing', function (string $blank): void {
    $party = new Party(countryCode: $blank, vatId: $blank, identifier: $blank, legalRegistrationId: $blank, street: $blank, city: $blank, postCode: $blank);

    expect($party->hasVatId())->toBeFalse()
        ->and($party->hasAnyIdentifier())->toBeFalse()
        ->and($party->hasPostalAddress())->toBeFalse();
})->with($blanks);

it('fails a seller whose only identifier is a BT-31 of U+00A0 or U+3000', function (string $blank, Closure $validator, string $ruleId) use ($seller, $hasFatal): void {
    $validationResult = $validator()->validateModel(makeInvoice(seller: $seller()));
    $actual = $validator()->validateModel(makeInvoice(seller: $seller(vatId: $blank)));

    expect($validationResult->isValid())->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, $ruleId))->toBeTrue();
})->with($blanks)->with([
    'BR-CO-26' => [static fn () => core(), 'BR-CO-26'],
    'BR-S-02' => [static fn () => core(), 'BR-S-02'],
    'BR-DE-16' => [static fn () => xr(), 'BR-DE-16'],
]);

it('fails a seller whose only BT-29 is U+00A0 or U+3000 (BR-CO-26)', function (string $blank) use ($seller, $hasFatal): void {
    $validationResult = core()->validateModel(makeInvoice(seller: $seller(identifier: 'fixture-seller-id')));
    $actual = core()->validateModel(makeInvoice(seller: $seller(vatId: null, identifier: $blank)));

    expect($validationResult->isValid())->toBeTrue()
        ->and($hasFatal($actual, 'BR-CO-26'))->toBeTrue();
})->with($blanks);

it('fails a buyer postal address made only of U+00A0 or U+3000 (BR-10)', function (string $blank) use ($hasFatal): void {
    $validationResult = core()->validateModel(makeInvoice());
    $actual = core()->validateModel(makeInvoice(buyer: new Party(name: 'Buyer AG', vatId: 'DE987654321', city: $blank, postCode: $blank)));

    expect($validationResult->isValid())->toBeTrue()
        ->and($hasFatal($actual, 'BR-10'))->toBeTrue();
})->with($blanks);

it('keeps BR-O-02 failing on any supplied seller VAT identifier, blank ones included', function (?string $vatId, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(
        seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: $vatId, identifier: 'fixture-seller-id', city: 'Berlin', postCode: '10115'),
        buyer: new Party(name: 'Buyer AG', countryCode: 'DE', city: 'Hamburg', postCode: '20095'),
        totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '0.00', grandTotal: '100.00', paidAmount: '0.00', payableAmount: '100.00'),
        lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'O')],
        taxSubtotals: [new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject to VAT')],
    ));

    expect($validationResult->hasViolation('BR-O-02'))->toBe($violates);
})->with([
    'absent' => [null, false],
    'empty' => ['', false],
    'only a no-break space' => ["\u{00A0}", true],
    'only an ideographic space' => ["\u{3000}", true],
    'filled' => ['DE123456789', true],
]);
