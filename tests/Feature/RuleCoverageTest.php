<?php

declare(strict_types=1);

use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\ItemAttribute;
use JohnWink\En16931\Model\ItemClassification;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;
use JohnWink\En16931\RuleSets;
use JohnWink\En16931\Validator;

it('flags a missing specification identifier (BR-01)', function (): void {
    expect(core()->validateModel(makeInvoice(customizationId: null))->hasViolation('BR-01'))->toBeTrue();
});

it('flags an invalid currency code (BR-CL-04)', function (): void {
    expect(core()->validateModel(makeInvoice(currency: 'ZZZ'))->hasViolation('BR-CL-04'))->toBeTrue();
});

it('accepts a real ISO currency', function (): void {
    // CHF is a valid 2-decimal ISO currency and should not trip BR-CL-03.
    expect(core()->validateModel(makeInvoice(currency: 'CHF'))->hasViolation('BR-CL-03'))->toBeFalse();
});

it('flags an invalid seller country code (BR-CL-14)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        seller: new Party(name: 'S', countryCode: 'XX', vatId: 'DE1'),
    ));

    expect($validationResult->hasViolation('BR-CL-14'))->toBeTrue();
});

it('flags a line missing its item name (BR-25)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'S',
        taxRate: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-25'))->toBeTrue();
});

it('flags a VAT breakdown missing its taxable amount (BR-45)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxAmount: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-45'))->toBeTrue();
});

it('flags an inconsistent category tax amount (BR-CO-17)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '99.00',
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeTrue();
});

it('accepts a correct category tax amount', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeFalse();
});

it('tolerates a category tax amount one cent off the rounded product (BR-CO-17)', function (string $taxableAmount, string $taxAmount): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeFalse();
})->with([
    'one cent above' => ['100.00', '19.01'],
    'one cent below' => ['100.00', '18.99'],
    'credit note one cent off' => ['-100.00', '-19.01'],
    'just under one unit above' => ['100.00', '19.99'],
    'just under one unit below' => ['100.00', '18.01'],
    'credit note just under one unit above' => ['-100.00', '-19.99'],
    'credit note just under one unit below' => ['-100.00', '-18.01'],
]);

it('flags a category tax amount off by one unit or more (BR-CO-17)', function (string $taxableAmount, string $taxAmount): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeTrue();
})->with([
    'exactly one unit above' => ['100.00', '20.00'],
    'exactly one unit below' => ['100.00', '18.00'],
    'more than one unit above' => ['100.00', '20.50'],
    'credit note exactly one unit above' => ['-100.00', '-20.00'],
    'credit note exactly one unit below' => ['-100.00', '-18.00'],
]);

it('requires BT-117 to round to zero when the rate is missing or zero (BR-CO-17)', function (string $category, ?string $rate, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: $category,
        rate: $rate,
        taxableAmount: '100.00',
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBe($violates);
})->with([
    'rate missing, 0.49' => ['O', null, '0.49', false],
    'rate missing, 0.50' => ['O', null, '0.50', true],
    'rate missing, -0.50' => ['O', null, '-0.50', false],
    'rate missing, -0.51' => ['O', null, '-0.51', true],
    'rate zero, 0.49' => ['Z', '0.00', '0.49', false],
    'rate zero, 0.50' => ['Z', '0.00', '0.50', true],
    'rate zero, -0.50' => ['Z', '0.00', '-0.50', false],
    'rate zero, -0.51' => ['Z', '0.00', '-0.51', true],
]);

it('treats a rate that rounds to zero like a zero rate (BR-CO-17)', function (string $rate, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: $rate,
        taxableAmount: '1000.00',
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBe($violates);
})->with([
    'rate 0.30, product 3.00' => ['0.30', '3.00', true],
    'rate 0.30, BT-117 rounds to zero' => ['0.30', '0.40', false],
    'rate 0.50, product 5.00' => ['0.50', '5.00', false],
    'rate 0.50, BT-117 zero' => ['0.50', '0.00', true],
]);

it('flags a filled but unreadable amount or rate instead of skipping it (BR-CO-17)', function (?string $rate, string $taxableAmount, string $taxAmount): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: $rate,
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeTrue();
})->with([
    'unreadable BT-116' => ['19.00', 'n/a', '19.00'],
    'unreadable BT-119' => ['19 %', '100.00', '19.00'],
    'unreadable BT-117' => ['19.00', '100.00', '19,00'],
    'unreadable BT-117, rate missing' => [null, '100.00', 'zero'],
    'unreadable BT-116, rate missing' => [null, '1e2', '0.00'],
    'BT-117 +-19.00, with rate' => ['19.00', '100.00', '+-19.00'],
    'BT-117 +-19.00, rate missing' => [null, '100.00', '+-19.00'],
    'BT-117 --19.00, with rate' => ['19.00', '100.00', '--19.00'],
    'BT-117 --19.00, rate missing' => [null, '100.00', '--19.00'],
    'BT-117 1.2.3, with rate' => ['19.00', '100.00', '1.2.3'],
    'BT-117 1.2.3, rate missing' => [null, '100.00', '1.2.3'],
    'BT-117 - 19.00, with rate' => ['19.00', '100.00', '- 19.00'],
    'BT-117 - 19.00, rate missing' => [null, '100.00', '- 19.00'],
    'BT-117 19 .00, with rate' => ['19.00', '100.00', '19 .00'],
    'BT-117 19 .00, rate missing' => [null, '100.00', '19 .00'],
    'BT-117 +, with rate' => ['19.00', '0.00', '+'],
    'BT-117 +, rate missing' => [null, '100.00', '+'],
    'BT-117 -, with rate' => ['19.00', '0.00', '-'],
    'BT-117 -, rate missing' => [null, '100.00', '-'],
    'BT-117 ., with rate' => ['19.00', '0.00', '.'],
    'BT-117 ., rate missing' => [null, '100.00', '.'],
    'BT-117 -., with rate' => ['19.00', '0.00', '-.'],
    'BT-117 -., rate missing' => [null, '100.00', '-.'],
]);

it('trims only XML whitespace around BT-116, BT-119 and BT-117 (BR-CO-17)', function (string $rate, string $taxableAmount, string $taxAmount, bool $violates): void {
    $brCo17 = new Validator(array_values(array_filter(RuleSets::en16931(), static fn (Rule $rule): bool => $rule->id() === 'BR-CO-17')));

    $validationResult = $brCo17->validate(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: $rate,
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBe($violates);
})->with([
    'spaces around BT-117' => ['19.00', '100.00', ' 19.00 ', false],
    'tab and line feed around BT-117' => ['19.00', '100.00', "\t19.00\n", false],
    'carriage return and line feed around BT-116' => ['19.00', "\r\n100.00\r\n", '19.00', false],
    'tab around BT-119' => ["\t19.00\t", '100.00', '19.00', false],
    'no-break space around BT-117' => ['19.00', '100.00', "\u{00A0}19.00\u{00A0}", true],
    'no-break space around BT-116' => ['19.00', "\u{00A0}100.00\u{00A0}", '19.00', true],
    'no-break space around BT-119' => ["\u{00A0}19.00\u{00A0}", '100.00', '19.00', true],
    'ideographic space around BT-117' => ['19.00', '100.00', "\u{3000}19.00\u{3000}", true],
]);

it('applies the inclusive tolerance to CII and the strict one to UBL and source-less models (BR-CO-17)', function (?string $sourceSyntax, string $taxableAmount, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(sourceSyntax: $sourceSyntax, taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBe($violates);
})->with([
    'CII exactly 1.00 above' => ['cii', '100.00', '20.00', false],
    'CII exactly 1.00 below' => ['cii', '100.00', '18.00', false],
    'CII credit note exactly 1.00 above' => ['cii', '-100.00', '-20.00', false],
    'CII credit note exactly 1.00 below' => ['cii', '-100.00', '-18.00', false],
    'CII 1.01 above' => ['cii', '100.00', '20.01', true],
    'CII 1.01 below' => ['cii', '100.00', '17.99', true],
    'UBL exactly 1.00 above' => ['ubl', '100.00', '20.00', true],
    'UBL exactly 1.00 below' => ['ubl', '100.00', '18.00', true],
    'UBL just under 1.00 above' => ['ubl', '100.00', '19.99', false],
    'UBL 1.01 above' => ['ubl', '100.00', '20.01', true],
    'UBL 1.01 below' => ['ubl', '100.00', '17.99', true],
    'no source syntax exactly 1.00 above' => [null, '100.00', '20.00', true],
    'no source syntax exactly 1.00 below' => [null, '100.00', '18.00', true],
    'no source syntax just under 1.00 above' => [null, '100.00', '19.99', false],
]);

it('accepts a CII BT-117 exactly one unit off and rejects the same UBL one (BR-CO-17)', function (): void {
    $cii = str_replace('<ram:CalculatedAmount>19.00</ram:CalculatedAmount>', '<ram:CalculatedAmount>20.00</ram:CalculatedAmount>', (string) file_get_contents(__DIR__.'/../Fixtures/valid-cii.xml'));
    $ubl = str_replace('<cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount>
            <cac:TaxCategory>', '<cbc:TaxAmount currencyID="EUR">20.00</cbc:TaxAmount>
            <cac:TaxCategory>', (string) file_get_contents(__DIR__.'/../Fixtures/valid-ubl.xml'));

    expect($cii)->toContain('<ram:CalculatedAmount>20.00</ram:CalculatedAmount>')
        ->and($ubl)->toContain('<cbc:TaxAmount currencyID="EUR">20.00</cbc:TaxAmount>')
        ->and(en()->validateCii($cii)->hasViolation('BR-CO-17'))->toBeFalse()
        ->and(en()->validateUbl($ubl)->hasViolation('BR-CO-17'))->toBeTrue();
});

it('reads the xs:decimal forms of BT-116, BT-119 and BT-117 (BR-CO-17)', function (string $rate, string $taxableAmount, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: $rate,
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBe($violates);
})->with([
    'leading plus' => ['+19.00', '+100.00', '+19.00', false],
    'no integer digits' => ['.50', '1000', '5', false],
    'trailing dot' => ['19.', '100.', '19.', false],
    'credit note with trailing dot' => ['19.', '-100.', '-19.', false],
]);

it('reads an xs:decimal BT-117 through the full UBL pipeline (BR-CO-17)', function (): void {
    $xml = str_replace('<cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount>
            <cac:TaxCategory>', '<cbc:TaxAmount currencyID="EUR">+19.00</cbc:TaxAmount>
            <cac:TaxCategory>', (string) file_get_contents(__DIR__.'/../Fixtures/valid-ubl.xml'));

    expect($xml)->toContain('+19.00')
        ->and(en()->validateUbl($xml)->hasViolation('BR-CO-17'))->toBeFalse();
});

it('rejects an unreadable BT-117 at the XSD stage of the UBL pipeline', function (): void {
    $xml = str_replace('<cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount>
            <cac:TaxCategory>', '<cbc:TaxAmount currencyID="EUR">19,00</cbc:TaxAmount>
            <cac:TaxCategory>', (string) file_get_contents(__DIR__.'/../Fixtures/valid-ubl.xml'));

    $validationResult = en()->validateUbl($xml);

    expect($xml)->toContain('19,00')
        ->and($validationResult->isValid())->toBeFalse()
        ->and($validationResult->hasViolation('XSD'))->toBeTrue();
});

it('requires a zero category tax amount when the rate is zero (BR-CO-17)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '100.00', taxAmount: '1.00')]));
    $zero = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00')]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeTrue()
        ->and($zero->hasViolation('BR-CO-17'))->toBeFalse();
});

it('requires a zero category tax amount when the rate is missing (BR-CO-17)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '1.00')]));
    $zero = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00')]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeTrue()
        ->and($zero->hasViolation('BR-CO-17'))->toBeFalse();
});

it('keeps BR-CO-14 exact when BR-CO-17 tolerates one cent', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.01',
    )]));

    expect($validationResult->hasViolation('BR-CO-17'))->toBeFalse()
        ->and($validationResult->hasViolation('BR-CO-14'))->toBeTrue();
});

it('flags a missing total amount without VAT (BR-13)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(totals: new Totals(
        lineTotal: '100.00',
        taxTotal: '19.00',
        grandTotal: '119.00',
        paidAmount: '0.00',
        payableAmount: '119.00',
    )));

    expect($validationResult->hasViolation('BR-13'))->toBeTrue();
});

it('flags a non-zero VAT rate on an exempt line (BR-E-05)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'E',
        taxRate: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-E-05'))->toBeTrue();
});

it('flags a positive amount due with no due date or payment terms (BR-CO-25)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(paymentDueDate: null, paymentTerms: null));

    expect($validationResult->hasViolation('BR-CO-25'))->toBeTrue();
});

it('flags a line allowance without a reason (BR-42)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '95.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'S',
        taxRate: '19.00',
        allowanceCharges: [new LineAllowanceCharge(isCharge: false, amount: '5.00')],
    )]));

    expect($validationResult->hasViolation('BR-42'))->toBeTrue();
});

it('flags a seller with no identifier at all (BR-CO-26)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        seller: new Party(name: 'Seller GmbH', countryCode: 'DE'), // no VAT id, BT-29 or BT-30
    ));

    expect($validationResult->hasViolation('BR-CO-26'))->toBeTrue();
});

it('flags a Standard document allowance with a zero rate (BR-S-06)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '0.00'),
    ]));

    expect($validationResult->hasViolation('BR-S-06'))->toBeTrue();
});

it('flags an exempt group without an exemption reason (BR-E-10)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
    )]));

    expect($validationResult->hasViolation('BR-E-10'))->toBeTrue();
});

it('accepts an exempt group that carries an exemption reason', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
        exemptionReason: 'Kleinunternehmer §19 UStG',
    )]));

    expect($validationResult->hasViolation('BR-E-10'))->toBeFalse();
});

it('flags a reverse-charge group that carries a non-zero tax amount (BR-AE-09)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'AE',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
        exemptionReason: 'Reverse charge',
    )]));

    expect($validationResult->hasViolation('BR-AE-09'))->toBeTrue();
});

it('flags a Standard group that carries an exemption reason (BR-S-10)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
        exemptionReason: 'should not be here',
    )]));

    expect($validationResult->hasViolation('BR-S-10'))->toBeTrue();
});

it('flags a used line category with no matching breakdown group (BR-S-01)', function (): void {
    // Default line is category S, but the only breakdown group is E.
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
        exemptionReason: 'exempt',
    )]));

    expect($validationResult->hasViolation('BR-S-01'))->toBeTrue();
});

it('reports a Standard document charge with a zero rate under BR-S-07, not BR-S-06', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: true, amount: '10.00', taxCategory: 'S', taxRate: '0.00'),
    ]));

    expect($validationResult->hasViolation('BR-S-07'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-S-06'))->toBeFalse();
});

it('flags a zero-rated line with an absent VAT rate (BR-Z-05)', function (): void {
    // Officially xs:decimal(cbc:Percent) = 0 — an absent rate fails the assert.
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'Z',
    )]));

    expect($validationResult->hasViolation('BR-Z-05'))->toBeTrue();
});

it('flags an exempt document allowance with an absent VAT rate (BR-E-06)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'E'),
    ]));

    expect($validationResult->hasViolation('BR-E-06'))->toBeTrue();
});

it('flags two Zero-rated breakdown groups — BR-Z-01 requires exactly one', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        lines: [new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'Z', taxRate: '0.00')],
        taxSubtotals: [
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '60.00', taxAmount: '0.00'),
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '40.00', taxAmount: '0.00'),
        ],
    ));

    expect($validationResult->hasViolation('BR-Z-01'))->toBeTrue();
});

it('triggers BR-Z-01 from a document allowance category as well', function (): void {
    // Default S line + S breakdown; the Z allowance has no Z breakdown group.
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'Z', taxRate: '0.00'),
    ]));

    expect($validationResult->hasViolation('BR-Z-01'))->toBeTrue();
});

it('flags a Standard breakdown group when the category is never used (BR-S-01)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        lines: [new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'Z', taxRate: '0.00')],
        taxSubtotals: [
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00'),
            new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '0.00', taxAmount: '0.00'),
        ],
    ));

    expect($validationResult->hasViolation('BR-S-01'))->toBeTrue();
});

it('flags an invalid VAT category on a breakdown group (BR-CL-17 on BT-118)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'XX',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-CL-17'))->toBeTrue();
});

it('accepts VAT category B (split payment) as a valid code', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'B',
        taxRate: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-CL-17'))->toBeFalse();
});

it('accepts newly added official invoice type codes (BR-CL-01)', function (): void {
    expect(core()->validateModel(makeInvoice(typeCode: '261'))->hasViolation('BR-CL-01'))->toBeFalse();
});

it('rejects type code 936, which is not part of UNTDID 1001 (BR-CL-01)', function (): void {
    expect(core()->validateModel(makeInvoice(typeCode: '936'))->hasViolation('BR-CL-01'))->toBeTrue();
});

// ---- Schritt 1: Dokument-Abschläge/-Zuschläge (BR-31..38) ----

it('flags a document allowance without an amount (BR-31)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, taxCategory: 'S', taxRate: '19.00'),
    ]));

    expect($validationResult->hasViolation('BR-31'))->toBeTrue();
});

it('flags a document allowance without a VAT category (BR-32)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00'),
    ]));

    expect($validationResult->hasViolation('BR-32'))->toBeTrue();
});

it('flags a document allowance without a reason or reason code (BR-33)', function (): void {
    $allowance = new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00');
    $withCode = new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '95');

    expect(core()->validateModel(makeInvoice(allowanceCharges: [$allowance]))->hasViolation('BR-33'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(allowanceCharges: [$withCode]))->hasViolation('BR-33'))->toBeFalse();
});

it('flags a document charge without an amount (BR-36), category (BR-37) and reason (BR-38)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: true),
    ]));

    expect($validationResult->hasViolation('BR-36'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-37'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-38'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-31'))->toBeFalse();
});

// ---- Schritt 1: Breakdown-Rate & VAT-Id-Präfix ----

it('flags a VAT breakdown without a rate (BR-48), except for category O', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', taxableAmount: '100.00', taxAmount: '19.00',
    )]));
    $notSubject = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject',
    )]));

    expect($validationResult->hasViolation('BR-48'))->toBeTrue()
        ->and($notSubject->hasViolation('BR-48'))->toBeFalse();
});

it('flags a VAT identifier without a country prefix (BR-CO-09)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: '123456789')));
    $greek = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'GR', vatId: 'EL123456789')));

    expect($validationResult->hasViolation('BR-CO-09'))->toBeTrue()
        ->and($greek->hasViolation('BR-CO-09'))->toBeFalse();
});

// ---- Schritt 1: Identifikationsregeln je Kategorie ----

it('flags a Standard-rated invoice whose seller has no tax identification (BR-S-02)', function (): void {
    $invoice = makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X'));
    $viaTaxRep = makeInvoice(
        seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X'),
        taxRepresentative: new Party(name: 'Rep', countryCode: 'DE', vatId: 'DE999999999'),
    );
    $viaTaxRegistration = makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X', taxRegistrationId: '123/456/7890'));

    expect(core()->validateModel($invoice)->hasViolation('BR-S-02'))->toBeTrue()
        ->and(core()->validateModel($viaTaxRep)->hasViolation('BR-S-02'))->toBeFalse()
        ->and(core()->validateModel($viaTaxRegistration)->hasViolation('BR-S-02'))->toBeFalse();
});

it('requires buyer identification on reverse charge (BR-AE-02)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'AE', taxRate: '0.00');
    $subtotal = new TaxSubtotal(category: 'AE', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Reverse charge');

    $validationResult = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], buyer: new Party(name: 'B', countryCode: 'DE')));
    $withLegalRegistration = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], buyer: new Party(name: 'B', countryCode: 'DE', legalRegistrationId: 'HRB 1')));

    expect($validationResult->hasViolation('BR-AE-02'))->toBeTrue()
        ->and($withLegalRegistration->hasViolation('BR-AE-02'))->toBeFalse();
});

it('flags a reverse-charge document allowance without buyer identification (BR-AE-03)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        buyer: new Party(name: 'B', countryCode: 'DE'),
        allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'AE', taxRate: '0.00', reason: 'Rabatt')],
    ));

    expect($validationResult->hasViolation('BR-AE-03'))->toBeTrue();
});

it('forbids VAT identifiers on a Not-subject-to-VAT invoice (BR-O-02)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'O');
    $subtotal = new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject');

    // Default seller carries a VAT id — forbidden for O.
    expect(core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal]))->hasViolation('BR-O-02'))->toBeTrue();
});

// ---- Schritt 1: O-Verbote ----

it('forbids a VAT rate on a Not-subject line (BR-O-05)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'O', taxRate: '0.00',
    )]));

    expect($validationResult->hasViolation('BR-O-05'))->toBeTrue();
});

it('forbids other breakdown groups next to O (BR-O-11) and non-O lines (BR-O-12)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [
        new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject'),
        new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00'),
    ]));

    // Default line is S: both the extra S group (O-11) and the S line (O-12) violate.
    expect($validationResult->hasViolation('BR-O-11'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-O-12'))->toBeTrue();
});

// ---- Schritt 1: IGIC/IPSI (L/M) und Split payment (B) ----

it('requires a present, non-negative rate on an IGIC line (BR-IG-05)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L',
    )]));
    $positive = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00',
    )]));

    expect($validationResult->hasViolation('BR-IG-05'))->toBeTrue()
        ->and($positive->hasViolation('BR-IG-05'))->toBeFalse();
});

it('tolerates rounding within one unit on IGIC tax amounts (BR-IG-09)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00');

    $validationResult = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '100.00', taxAmount: '9.00')]));
    $withinTolerance = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '100.00', taxAmount: '7.50')]));

    expect($validationResult->hasViolation('BR-IG-09'))->toBeTrue()
        ->and($withinTolerance->hasViolation('BR-IG-09'))->toBeFalse();
});

it('reconciles the IGIC taxable amount per rate (BR-IG-08)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00');

    $validationResult = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [
        new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '200.00', taxAmount: '14.00'),
    ]));

    expect($validationResult->hasViolation('BR-IG-08'))->toBeTrue();
});

it('requires split payment invoices to be domestic Italian (BR-B-01)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'B', taxRate: '22.00');
    $subtotal = new TaxSubtotal(category: 'B', rate: '22.00', taxableAmount: '100.00', taxAmount: '22.00');

    $validationResult = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal]));
    $italian = core()->validateModel(makeInvoice(
        lines: [$line],
        taxSubtotals: [$subtotal],
        seller: new Party(name: 'S', countryCode: 'IT', vatId: 'IT12345678901'),
        buyer: new Party(name: 'B', countryCode: 'IT', vatId: 'IT10987654321'),
    ));

    expect($validationResult->hasViolation('BR-B-01'))->toBeTrue()
        ->and($italian->hasViolation('BR-B-01'))->toBeFalse();
});

it('forbids mixing split payment with standard rated (BR-B-02)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [
        new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'B', taxRate: '22.00'),
        new InvoiceLine(id: '2', name: 'y', netAmount: '50.00', netPrice: '50.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00'),
    ]));

    expect($validationResult->hasViolation('BR-B-02'))->toBeTrue();
});

// ---- Schritt 1: Steuervertreter (BG-11) ----

it('requires name, VAT id and country on a tax representative (BR-18, BR-56, BR-20, BR-CL-14)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxRepresentative: new Party(countryCode: 'DE', vatId: 'DE999999999')));
    $withoutVat = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', countryCode: 'DE')));
    $withoutCountry = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', vatId: 'DE999999999')));
    $invalidCountry = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', countryCode: 'XX', vatId: 'DE999999999')));

    expect($validationResult->hasViolation('BR-18'))->toBeTrue()
        ->and($withoutVat->hasViolation('BR-56'))->toBeTrue()
        ->and($withoutCountry->hasViolation('BR-20'))->toBeTrue()
        ->and($invalidCountry->hasViolation('BR-CL-14'))->toBeTrue();
});

it('requires a zero rate on export and intra-community lines (BR-G-05, BR-IC-05)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'G', taxRate: '19.00',
    )]));
    $intraCommunity = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'K',
    )]));

    expect($validationResult->hasViolation('BR-G-05'))->toBeTrue()
        ->and($intraCommunity->hasViolation('BR-IC-05'))->toBeTrue();
});

// ---- Schritt 2: Adressen & elektronische Adressen ----

it('flags missing postal addresses (BR-08, BR-10)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(
        seller: new Party(name: 'S', vatId: 'DE123456789'),
        buyer: new Party(name: 'B', vatId: 'DE987654321'),
    ));

    expect($validationResult->hasViolation('BR-08'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-10'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-08'))->toBeFalse();
});

it('requires a scheme on electronic addresses (BR-62, BR-63)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'seller@example.org')));
    $buyer = core()->validateModel(makeInvoice(buyer: new Party(name: 'B', countryCode: 'DE', vatId: 'DE987654321', electronicAddress: 'buyer@example.org')));

    expect($validationResult->hasViolation('BR-62'))->toBeTrue()
        ->and($buyer->hasViolation('BR-63'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-63'))->toBeFalse();
});

it('validates the electronic address scheme against the EAS list (BR-CL-25)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'x', electronicAddressScheme: '9999')));
    $valid = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'x', electronicAddressScheme: 'EM')));

    expect($validationResult->hasViolation('BR-CL-25'))->toBeTrue()
        ->and($valid->hasViolation('BR-CL-25'))->toBeFalse();
});

// ---- Schritt 3: Zahlungsinstruktionen (BG-16..19) ----

it('requires a payment means type code (BR-49) from the UNTDID 4461 list (BR-CL-16)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(accountId: 'DE02120300000000202051', hasCreditTransfer: true)]));
    $unknown = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '99')]));

    expect($validationResult->hasViolation('BR-49'))->toBeTrue()
        ->and($unknown->hasViolation('BR-CL-16'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-CL-16'))->toBeFalse();
});

it('requires an account id for credit transfers (BR-61) and on present accounts (BR-50)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '58')]));
    $emptyAccount = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '30', hasCreditTransfer: true)]));

    expect($validationResult->hasViolation('BR-61'))->toBeTrue()
        ->and($emptyAccount->hasViolation('BR-50'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-61'))->toBeFalse();
});

it('warns about a full card primary account number (BR-51)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: '4111111111111111')]));
    $truncated = core()->validateModel(makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: '1111')]));

    expect($validationResult->hasViolation('BR-51'))->toBeTrue()
        ->and($truncated->hasViolation('BR-51'))->toBeFalse();
});

// ---- Schritt 4: Lieferung & Zeiträume ----

it('flags an invoicing period ending before it starts (BR-29)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, invoicingPeriodStart: '2026-02-01', invoicingPeriodEnd: '2026-01-01'));
    $ordered = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, invoicingPeriodStart: '2026-01-01', invoicingPeriodEnd: '2026-01-31'));

    expect($validationResult->hasViolation('BR-29'))->toBeTrue()
        ->and($ordered->hasViolation('BR-29'))->toBeFalse();
});

it('flags a line period ending before it starts (BR-30) and an empty one (BR-CO-20)', function (): void {
    $line = static fn (?string $start, ?string $end): InvoiceLine => new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00', hasPeriod: true, periodStart: $start, periodEnd: $end,
    );

    $validationResult = core()->validateModel(makeInvoice(lines: [$line('2026-02-01', '2026-01-01')]));
    $empty = core()->validateModel(makeInvoice(lines: [$line(null, null)]));

    expect($validationResult->hasViolation('BR-30'))->toBeTrue()
        ->and($empty->hasViolation('BR-CO-20'))->toBeTrue();
});

it('flags an empty invoicing period (BR-CO-19) but accepts a lone description code', function (): void {
    $validationResult = core()->validateModel(makeInvoice(hasInvoicingPeriod: true));
    $codeOnly = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '35'));

    expect($validationResult->hasViolation('BR-CO-19'))->toBeTrue()
        ->and($codeOnly->hasViolation('BR-CO-19'))->toBeFalse();
});

it('requires a country on the deliver-to address (BR-57) from ISO 3166 (BR-CL-14)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(deliverTo: new Party(city: 'Potsdam', postCode: '14467')));
    $invalid = core()->validateModel(makeInvoice(deliverTo: new Party(countryCode: 'XX', city: 'Potsdam', postCode: '14467')));

    expect($validationResult->hasViolation('BR-57'))->toBeTrue()
        ->and($invalid->hasViolation('BR-CL-14'))->toBeTrue();
});

it('requires delivery information on intra-community invoices (BR-IC-11, BR-IC-12)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'K', taxRate: '0.00');
    $subtotal = new TaxSubtotal(category: 'K', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Intra-community supply');

    $validationResult = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], actualDeliveryDate: null));
    $complete = core()->validateModel(makeInvoice(
        lines: [$line],
        taxSubtotals: [$subtotal],
        deliverTo: new Party(countryCode: 'AT', city: 'Wien', postCode: '1010'),
    ));

    expect($validationResult->hasViolation('BR-IC-11'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-IC-12'))->toBeTrue()
        ->and($complete->hasViolation('BR-IC-11'))->toBeFalse()
        ->and($complete->hasViolation('BR-IC-12'))->toBeFalse();
});

// ---- Schritt 5: Dokumente & Zeilen-Details ----

it('requires a distinct payee name (BR-17)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(payee: new Party(name: 'Seller GmbH')));
    $nameless = core()->validateModel(makeInvoice(payee: new Party(identifier: 'X')));
    $distinct = core()->validateModel(makeInvoice(payee: new Party(name: 'Factoring AG')));

    expect($validationResult->hasViolation('BR-17'))->toBeTrue()
        ->and($nameless->hasViolation('BR-17'))->toBeTrue()
        ->and($distinct->hasViolation('BR-17'))->toBeFalse();
});

it('requires a postal address on the tax representative (BR-19)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', vatId: 'DE999999999')));

    expect($validationResult->hasViolation('BR-19'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', countryCode: 'DE', vatId: 'DE999999999')))->hasViolation('BR-19'))->toBeFalse();
});

it('flags a negative gross price (BR-28)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00', grossPrice: '-5.00',
    )]));

    expect($validationResult->hasViolation('BR-28'))->toBeTrue();
});

it('requires a reference on every supporting document (BR-52) with a valid MIME code (BR-CL-24)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(attachments: [new Attachment(filename: 'a.pdf', mimeCode: 'application/pdf')]));
    $badMime = core()->validateModel(makeInvoice(attachments: [new Attachment(reference: 'DOC-1', mimeCode: 'text/html')]));

    expect($validationResult->hasViolation('BR-52'))->toBeTrue()
        ->and($badMime->hasViolation('BR-CL-24'))->toBeTrue()
        ->and($badMime->hasViolation('BR-52'))->toBeFalse();
});

it('requires BT-111 when an accounting currency is set (BR-53, BR-DEC-15)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxCurrency: 'USD'));
    $tooManyDecimals = core()->validateModel(makeInvoice(taxCurrency: 'USD', totals: new Totals(
        lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00',
        paidAmount: '0.00', payableAmount: '119.00', taxTotalAccounting: '20.123',
    )));

    expect($validationResult->hasViolation('BR-53'))->toBeTrue()
        ->and($tooManyDecimals->hasViolation('BR-53'))->toBeFalse()
        ->and($tooManyDecimals->hasViolation('BR-DEC-15'))->toBeTrue();
});

it('requires name and value on item attributes (BR-54) and schemes on item identifiers (BR-64, BR-65)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemStandardId: '4012345001235',
        itemClassifications: [new ItemClassification(code: '10201000')],
        attributes: [new ItemAttribute(name: 'Farbe')],
    );

    $validationResult = core()->validateModel(makeInvoice(lines: [$line]));

    expect($validationResult->hasViolation('BR-54'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-64'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-65'))->toBeTrue();
});

it('requires a reference on preceding invoices (BR-55)', function (): void {
    expect(core()->validateModel(makeInvoice(precedingInvoiceReferences: [null]))->hasViolation('BR-55'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(precedingInvoiceReferences: ['R-2025-9']))->hasViolation('BR-55'))->toBeFalse();
});

it('forbids combining tax point date and code (BR-CO-03)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxPointDate: '2026-01-15', hasInvoicingPeriod: true, taxPointDateCode: '35'));

    expect($validationResult->hasViolation('BR-CO-03'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(taxPointDate: '2026-01-15'))->hasViolation('BR-CO-03'))->toBeFalse();
});

it('mirrors the reason requirements under the BR-CO ids (BR-CO-21..24)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00'),
        new DocumentAllowanceCharge(isCharge: true, amount: '5.00', taxCategory: 'S', taxRate: '19.00'),
    ]));
    $line = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [
            new LineAllowanceCharge(isCharge: false, amount: '5.00'),
            new LineAllowanceCharge(isCharge: true, amount: '2.00'),
        ],
    )]));

    expect($validationResult->hasViolation('BR-CO-21'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-CO-22'))->toBeTrue()
        ->and($line->hasViolation('BR-CO-23'))->toBeTrue()
        ->and($line->hasViolation('BR-CO-24'))->toBeTrue();
});

it('checks decimals on base amounts and line allowances (BR-DEC-02/05/06/24/25/27/28)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.123', taxCategory: 'S', taxRate: '19.00', reason: 'Rabatt', baseAmount: '100.123'),
        new DocumentAllowanceCharge(isCharge: true, amount: '5.123', taxCategory: 'S', taxRate: '19.00', reason: 'Zuschlag', baseAmount: '50.123'),
    ]));
    $line = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [
            new LineAllowanceCharge(isCharge: false, amount: '5.123', reason: 'Rabatt', baseAmount: '10.123'),
            new LineAllowanceCharge(isCharge: true, amount: '2.123', reason: 'Zuschlag', baseAmount: '20.123'),
        ],
    )]));

    expect($validationResult->hasViolation('BR-DEC-01'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-DEC-02'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-DEC-05'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-DEC-06'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-24'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-25'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-27'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-28'))->toBeTrue();
});

// ---- Schritt 6: Codelisten ----

it('validates amount currency attributes (BR-CL-03) and the tax currency (BR-CL-05)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(amountCurrencyCodes: ['EUR', 'FOO']));
    $badTaxCurrency = core()->validateModel(makeInvoice(taxCurrency: 'ZZZ', totals: new Totals(
        lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00',
        paidAmount: '0.00', payableAmount: '119.00', taxTotalAccounting: '19.00',
    )));

    expect($validationResult->hasViolation('BR-CL-03'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(amountCurrencyCodes: ['EUR']))->hasViolation('BR-CL-03'))->toBeFalse()
        ->and($badTaxCurrency->hasViolation('BR-CL-05'))->toBeTrue();
});

it('reports an invalid invoice currency under the official id BR-CL-04', function (): void {
    $validationResult = core()->validateModel(makeInvoice(currency: 'ZZZ'));

    expect($validationResult->hasViolation('BR-CL-04'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-CL-03'))->toBeFalse();
});

it('reports an invalid line VAT category under the official id BR-CL-18', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'XX', taxRate: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-CL-18'))->toBeTrue();
});

it('validates the tax point date code (BR-CL-06)', function (): void {
    expect(core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '99'))->hasViolation('BR-CL-06'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '35'))->hasViolation('BR-CL-06'))->toBeFalse();
});

it('validates object identifier schemes (BR-CL-07) and note subject codes (BR-CL-08)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(attachments: [new Attachment(reference: 'OBJ-1', typeCode: '130', scheme: 'ZZ9')]));
    $badNote = core()->validateModel(makeInvoice(notes: ['#XY9#Hinweistext']));

    expect($validationResult->hasViolation('BR-CL-07'))->toBeTrue()
        ->and($badNote->hasViolation('BR-CL-08'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(notes: ['Hinweis ohne Code']))->hasViolation('BR-CL-08'))->toBeFalse();
});

it('validates party identification schemes (BR-CL-10, BR-CL-11)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', identifier: 'X', identifierScheme: '9999')));
    $badLegalScheme = core()->validateModel(makeInvoice(buyer: new Party(name: 'B', countryCode: 'DE', vatId: 'DE987654321', legalRegistrationId: 'HRB 1', legalRegistrationIdScheme: '9999')));

    expect($validationResult->hasViolation('BR-CL-10'))->toBeTrue()
        ->and($badLegalScheme->hasViolation('BR-CL-11'))->toBeTrue();
});

it('validates item classification and standard identifier schemes (BR-CL-13, BR-CL-21) and origin country (BR-CL-15)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemStandardId: '4012345001235', itemStandardIdScheme: '9999',
        itemClassifications: [new ItemClassification(code: '10201000', scheme: 'XYZ1')],
        originCountryCode: 'X1',
    );

    $validationResult = core()->validateModel(makeInvoice(lines: [$line]));

    expect($validationResult->hasViolation('BR-CL-13'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-CL-21'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-CL-15'))->toBeTrue();
});

it('validates allowance and charge reason codes (BR-CL-19, BR-CL-20)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '999'),
        new DocumentAllowanceCharge(isCharge: true, amount: '5.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '999'),
    ]));
    $valid = core()->validateModel(makeInvoice(allowanceCharges: [
        new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '95'),
    ]));

    expect($validationResult->hasViolation('BR-CL-19'))->toBeTrue()
        ->and($validationResult->hasViolation('BR-CL-20'))->toBeTrue()
        ->and($valid->hasViolation('BR-CL-19'))->toBeFalse();
});

it('validates exemption reason codes case-insensitively (BR-CL-22)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReasonCode: 'VATEX-EU-999',
    )]));
    $lowercase = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReasonCode: 'vatex-eu-132',
    )]));

    expect($validationResult->hasViolation('BR-CL-22'))->toBeTrue()
        ->and($lowercase->hasViolation('BR-CL-22'))->toBeFalse();
});

it('validates unit codes against UN/ECE Rec 20+21 (BR-CL-23)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'XYZ9',
        taxCategory: 'S', taxRate: '19.00',
    )]));

    expect($validationResult->hasViolation('BR-CL-23'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-CL-23'))->toBeFalse();
});

it('validates the delivery location scheme (BR-CL-26)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(deliverTo: new Party(
        countryCode: 'DE', identifier: 'LOC-1', city: 'Potsdam', postCode: '14467', identifierScheme: '9999',
    )));

    expect($validationResult->hasViolation('BR-CL-26'))->toBeTrue();
});

// ---- Nachtrag: von der KoSIT-Config erzwungene Regeln ----

it('allows at most one payment card account (BR-66) and one mandate (BR-67)', function (): void {
    $validationResult = core()->validateModel(makeInvoice(paymentMeans: [
        new PaymentMeans(typeCode: '48', hasCardInformation: true),
        new PaymentMeans(typeCode: '48', hasCardInformation: true),
    ]));
    $twoMandates = core()->validateModel(makeInvoice(paymentMeans: [
        new PaymentMeans(typeCode: '59', hasDirectDebit: true, debitedAccountId: 'DE02120300000000202051'),
        new PaymentMeans(typeCode: '59', hasDirectDebit: true, debitedAccountId: 'DE02120300000000202051'),
    ], sepaCreditorId: 'DE98ZZZ09999999999'));

    expect($validationResult->hasViolation('BR-66'))->toBeTrue()
        ->and($twoMandates->hasViolation('BR-67'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-66'))->toBeFalse()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-67'))->toBeFalse();
});

it('registers BR-CO-27 without ever rejecting a well-formed account (tautology in the config)', function (): void {
    // The official assert is vacuous (IBAN or proprietary or neither) — it never fires.
    expect(core()->validateModel(makeInvoice())->hasViolation('BR-CO-27'))->toBeFalse();
});
