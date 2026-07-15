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
        id: '1',
        name: null,
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'S',
        taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-25'))->toBeTrue();
});

it('flags a VAT breakdown missing its taxable amount (BR-45)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: null,
        taxAmount: '19.00',
    )]));

    expect($result->hasViolation('BR-45'))->toBeTrue();
});

it('flags an inconsistent category tax amount (BR-CO-17)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '99.00',
    )]));

    expect($result->hasViolation('BR-CO-17'))->toBeTrue();
});

it('accepts a correct category tax amount', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
    )]));

    expect($result->hasViolation('BR-CO-17'))->toBeFalse();
});

it('flags a missing total amount without VAT (BR-13)', function (): void {
    $result = core()->validateModel(makeInvoice(totals: new JohnWink\En16931\Model\Totals(
        lineTotal: '100.00',
        taxBasisTotal: null,
        taxTotal: '19.00',
        grandTotal: '119.00',
        paidAmount: '0.00',
        payableAmount: '119.00',
    )));

    expect($result->hasViolation('BR-13'))->toBeTrue();
});

it('flags a non-zero VAT rate on an exempt line (BR-E-05)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'E',
        taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-E-05'))->toBeTrue();
});

it('flags a positive amount due with no due date or payment terms (BR-CO-25)', function (): void {
    $result = core()->validateModel(makeInvoice(paymentDueDate: null, paymentTerms: null));

    expect($result->hasViolation('BR-CO-25'))->toBeTrue();
});

it('flags a line allowance without a reason (BR-42)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '95.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'S',
        taxRate: '19.00',
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
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
    )]));

    expect($result->hasViolation('BR-E-10'))->toBeTrue();
});

it('accepts an exempt group that carries an exemption reason', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
        exemptionReason: 'Kleinunternehmer §19 UStG',
    )]));

    expect($result->hasViolation('BR-E-10'))->toBeFalse();
});

it('flags a reverse-charge group that carries a non-zero tax amount (BR-AE-09)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'AE',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
        exemptionReason: 'Reverse charge',
    )]));

    expect($result->hasViolation('BR-AE-09'))->toBeTrue();
});

it('flags a Standard group that carries an exemption reason (BR-S-10)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
        exemptionReason: 'should not be here',
    )]));

    expect($result->hasViolation('BR-S-10'))->toBeTrue();
});

it('flags a used line category with no matching breakdown group (BR-S-01)', function (): void {
    // Default line is category S, but the only breakdown group is E.
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E',
        rate: '0.00',
        taxableAmount: '100.00',
        taxAmount: '0.00',
        exemptionReason: 'exempt',
    )]));

    expect($result->hasViolation('BR-S-01'))->toBeTrue();
});

it('reports a Standard document charge with a zero rate under BR-S-07, not BR-S-06', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: true, amount: '10.00', taxCategory: 'S', taxRate: '0.00'),
    ]));

    expect($result->hasViolation('BR-S-07'))->toBeTrue()
        ->and($result->hasViolation('BR-S-06'))->toBeFalse();
});

it('flags a zero-rated line with an absent VAT rate (BR-Z-05)', function (): void {
    // Officially xs:decimal(cbc:Percent) = 0 — an absent rate fails the assert.
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'Z',
        taxRate: null,
    )]));

    expect($result->hasViolation('BR-Z-05'))->toBeTrue();
});

it('flags an exempt document allowance with an absent VAT rate (BR-E-06)', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'E', taxRate: null),
    ]));

    expect($result->hasViolation('BR-E-06'))->toBeTrue();
});

it('flags two Zero-rated breakdown groups — BR-Z-01 requires exactly one', function (): void {
    $result = core()->validateModel(makeInvoice(
        lines: [new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'Z', taxRate: '0.00')],
        taxSubtotals: [
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '60.00', taxAmount: '0.00'),
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '40.00', taxAmount: '0.00'),
        ],
    ));

    expect($result->hasViolation('BR-Z-01'))->toBeTrue();
});

it('triggers BR-Z-01 from a document allowance category as well', function (): void {
    // Default S line + S breakdown; the Z allowance has no Z breakdown group.
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'Z', taxRate: '0.00'),
    ]));

    expect($result->hasViolation('BR-Z-01'))->toBeTrue();
});

it('flags a Standard breakdown group when the category is never used (BR-S-01)', function (): void {
    $result = core()->validateModel(makeInvoice(
        lines: [new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'Z', taxRate: '0.00')],
        taxSubtotals: [
            new TaxSubtotal(category: 'Z', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00'),
            new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '0.00', taxAmount: '0.00'),
        ],
    ));

    expect($result->hasViolation('BR-S-01'))->toBeTrue();
});

it('flags an invalid VAT category on a breakdown group (BR-CL-17 on BT-118)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'XX',
        rate: '19.00',
        taxableAmount: '100.00',
        taxAmount: '19.00',
    )]));

    expect($result->hasViolation('BR-CL-17'))->toBeTrue();
});

it('accepts VAT category B (split payment) as a valid code', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1',
        name: 'x',
        netAmount: '100.00',
        netPrice: '100.00',
        quantity: '1',
        unitCode: 'C62',
        taxCategory: 'B',
        taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-CL-17'))->toBeFalse();
});

it('accepts newly added official invoice type codes (BR-CL-01)', function (): void {
    expect(core()->validateModel(makeInvoice(typeCode: '261'))->hasViolation('BR-CL-01'))->toBeFalse();
});

it('rejects type code 936, which is not part of UNTDID 1001 (BR-CL-01)', function (): void {
    expect(core()->validateModel(makeInvoice(typeCode: '936'))->hasViolation('BR-CL-01'))->toBeTrue();
});

// ---- Schritt 1: Dokument-Abschläge/-Zuschläge (BR-31..38) ----

it('flags a document allowance without an amount (BR-31)', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, taxCategory: 'S', taxRate: '19.00'),
    ]));

    expect($result->hasViolation('BR-31'))->toBeTrue();
});

it('flags a document allowance without a VAT category (BR-32)', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00'),
    ]));

    expect($result->hasViolation('BR-32'))->toBeTrue();
});

it('flags a document allowance without a reason or reason code (BR-33)', function (): void {
    $allowance = new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00');
    $withCode = new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '95');

    expect(core()->validateModel(makeInvoice(allowanceCharges: [$allowance]))->hasViolation('BR-33'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(allowanceCharges: [$withCode]))->hasViolation('BR-33'))->toBeFalse();
});

it('flags a document charge without an amount (BR-36), category (BR-37) and reason (BR-38)', function (): void {
    $result = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: true),
    ]));

    expect($result->hasViolation('BR-36'))->toBeTrue()
        ->and($result->hasViolation('BR-37'))->toBeTrue()
        ->and($result->hasViolation('BR-38'))->toBeTrue()
        ->and($result->hasViolation('BR-31'))->toBeFalse();
});

// ---- Schritt 1: Breakdown-Rate & VAT-Id-Präfix ----

it('flags a VAT breakdown without a rate (BR-48), except for category O', function (): void {
    $withoutRate = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'S', taxableAmount: '100.00', taxAmount: '19.00',
    )]));
    $notSubject = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject',
    )]));

    expect($withoutRate->hasViolation('BR-48'))->toBeTrue()
        ->and($notSubject->hasViolation('BR-48'))->toBeFalse();
});

it('flags a VAT identifier without a country prefix (BR-CO-09)', function (): void {
    $numeric = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: '123456789')));
    $greek = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'GR', vatId: 'EL123456789')));

    expect($numeric->hasViolation('BR-CO-09'))->toBeTrue()
        ->and($greek->hasViolation('BR-CO-09'))->toBeFalse();
});

// ---- Schritt 1: Identifikationsregeln je Kategorie ----

it('flags a Standard-rated invoice whose seller has no tax identification (BR-S-02)', function (): void {
    $unidentified = makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X'));
    $viaTaxRep = makeInvoice(
        seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X'),
        taxRepresentative: new Party(name: 'Rep', countryCode: 'DE', vatId: 'DE999999999'),
    );
    $viaTaxRegistration = makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', identifier: 'X', taxRegistrationId: '123/456/7890'));

    expect(core()->validateModel($unidentified)->hasViolation('BR-S-02'))->toBeTrue()
        ->and(core()->validateModel($viaTaxRep)->hasViolation('BR-S-02'))->toBeFalse()
        ->and(core()->validateModel($viaTaxRegistration)->hasViolation('BR-S-02'))->toBeFalse();
});

it('requires buyer identification on reverse charge (BR-AE-02)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'AE', taxRate: '0.00');
    $subtotal = new TaxSubtotal(category: 'AE', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Reverse charge');

    $withoutBuyerId = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], buyer: new Party(name: 'B', countryCode: 'DE')));
    $withLegalRegistration = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], buyer: new Party(name: 'B', countryCode: 'DE', legalRegistrationId: 'HRB 1')));

    expect($withoutBuyerId->hasViolation('BR-AE-02'))->toBeTrue()
        ->and($withLegalRegistration->hasViolation('BR-AE-02'))->toBeFalse();
});

it('flags a reverse-charge document allowance without buyer identification (BR-AE-03)', function (): void {
    $result = core()->validateModel(makeInvoice(
        buyer: new Party(name: 'B', countryCode: 'DE'),
        allowanceCharges: [new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'AE', taxRate: '0.00', reason: 'Rabatt')],
    ));

    expect($result->hasViolation('BR-AE-03'))->toBeTrue();
});

it('forbids VAT identifiers on a Not-subject-to-VAT invoice (BR-O-02)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'O');
    $subtotal = new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject');

    // Default seller carries a VAT id — forbidden for O.
    expect(core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal]))->hasViolation('BR-O-02'))->toBeTrue();
});

// ---- Schritt 1: O-Verbote ----

it('forbids a VAT rate on a Not-subject line (BR-O-05)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'O', taxRate: '0.00',
    )]));

    expect($result->hasViolation('BR-O-05'))->toBeTrue();
});

it('forbids other breakdown groups next to O (BR-O-11) and non-O lines (BR-O-12)', function (): void {
    $result = core()->validateModel(makeInvoice(taxSubtotals: [
        new TaxSubtotal(category: 'O', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Not subject'),
        new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00'),
    ]));

    // Default line is S: both the extra S group (O-11) and the S line (O-12) violate.
    expect($result->hasViolation('BR-O-11'))->toBeTrue()
        ->and($result->hasViolation('BR-O-12'))->toBeTrue();
});

// ---- Schritt 1: IGIC/IPSI (L/M) und Split payment (B) ----

it('requires a present, non-negative rate on an IGIC line (BR-AF-05)', function (): void {
    $missing = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L',
    )]));
    $positive = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00',
    )]));

    expect($missing->hasViolation('BR-AF-05'))->toBeTrue()
        ->and($positive->hasViolation('BR-AF-05'))->toBeFalse();
});

it('tolerates rounding within one unit on IGIC tax amounts (BR-AF-09)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00');

    $offByTwo = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '100.00', taxAmount: '9.00')]));
    $withinTolerance = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '100.00', taxAmount: '7.50')]));

    expect($offByTwo->hasViolation('BR-AF-09'))->toBeTrue()
        ->and($withinTolerance->hasViolation('BR-AF-09'))->toBeFalse();
});

it('reconciles the IGIC taxable amount per rate (BR-AF-08)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'L', taxRate: '7.00');

    $result = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [
        new TaxSubtotal(category: 'L', rate: '7.00', taxableAmount: '200.00', taxAmount: '14.00'),
    ]));

    expect($result->hasViolation('BR-AF-08'))->toBeTrue();
});

it('requires split payment invoices to be domestic Italian (BR-B-01)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'B', taxRate: '22.00');
    $subtotal = new TaxSubtotal(category: 'B', rate: '22.00', taxableAmount: '100.00', taxAmount: '22.00');

    $german = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal]));
    $italian = core()->validateModel(makeInvoice(
        lines: [$line],
        taxSubtotals: [$subtotal],
        seller: new Party(name: 'S', countryCode: 'IT', vatId: 'IT12345678901'),
        buyer: new Party(name: 'B', countryCode: 'IT', vatId: 'IT10987654321'),
    ));

    expect($german->hasViolation('BR-B-01'))->toBeTrue()
        ->and($italian->hasViolation('BR-B-01'))->toBeFalse();
});

it('forbids mixing split payment with standard rated (BR-B-02)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [
        new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'B', taxRate: '22.00'),
        new InvoiceLine(id: '2', name: 'y', netAmount: '50.00', netPrice: '50.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00'),
    ]));

    expect($result->hasViolation('BR-B-02'))->toBeTrue();
});

// ---- Schritt 1: Steuervertreter (BG-11) ----

it('requires name, VAT id and country on a tax representative (BR-18, BR-56, BR-20, BR-CL-14)', function (): void {
    $nameless = core()->validateModel(makeInvoice(taxRepresentative: new Party(countryCode: 'DE', vatId: 'DE999999999')));
    $withoutVat = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', countryCode: 'DE')));
    $withoutCountry = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', vatId: 'DE999999999')));
    $invalidCountry = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', countryCode: 'XX', vatId: 'DE999999999')));

    expect($nameless->hasViolation('BR-18'))->toBeTrue()
        ->and($withoutVat->hasViolation('BR-56'))->toBeTrue()
        ->and($withoutCountry->hasViolation('BR-20'))->toBeTrue()
        ->and($invalidCountry->hasViolation('BR-CL-14'))->toBeTrue();
});

it('requires a zero rate on export and intra-community lines (BR-G-05, BR-IC-05)', function (): void {
    $export = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'G', taxRate: '19.00',
    )]));
    $intraCommunity = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'K', taxRate: null,
    )]));

    expect($export->hasViolation('BR-G-05'))->toBeTrue()
        ->and($intraCommunity->hasViolation('BR-IC-05'))->toBeTrue();
});
