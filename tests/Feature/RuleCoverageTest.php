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

it('flags an invalid currency code (BR-CL-04)', function (): void {
    expect(core()->validateModel(makeInvoice(currency: 'ZZZ'))->hasViolation('BR-CL-04'))->toBeTrue();
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

// ---- Schritt 2: Adressen & elektronische Adressen ----

it('flags missing postal addresses (BR-08, BR-10)', function (): void {
    $result = core()->validateModel(makeInvoice(
        seller: new Party(name: 'S', vatId: 'DE123456789'),
        buyer: new Party(name: 'B', vatId: 'DE987654321'),
    ));

    expect($result->hasViolation('BR-08'))->toBeTrue()
        ->and($result->hasViolation('BR-10'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-08'))->toBeFalse();
});

it('requires a scheme on electronic addresses (BR-62, BR-63)', function (): void {
    $seller = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'seller@example.org')));
    $buyer = core()->validateModel(makeInvoice(buyer: new Party(name: 'B', countryCode: 'DE', vatId: 'DE987654321', electronicAddress: 'buyer@example.org')));

    expect($seller->hasViolation('BR-62'))->toBeTrue()
        ->and($buyer->hasViolation('BR-63'))->toBeTrue()
        ->and($seller->hasViolation('BR-63'))->toBeFalse();
});

it('validates the electronic address scheme against the EAS list (BR-CL-25)', function (): void {
    $invalid = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'x', electronicAddressScheme: '9999')));
    $valid = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', electronicAddress: 'x', electronicAddressScheme: 'EM')));

    expect($invalid->hasViolation('BR-CL-25'))->toBeTrue()
        ->and($valid->hasViolation('BR-CL-25'))->toBeFalse();
});

// ---- Schritt 3: Zahlungsinstruktionen (BG-16..19) ----

it('requires a payment means type code (BR-49) from the UNTDID 4461 list (BR-CL-16)', function (): void {
    $missing = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(accountId: 'DE02120300000000202051', hasCreditTransfer: true)]));
    $unknown = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '99')]));

    expect($missing->hasViolation('BR-49'))->toBeTrue()
        ->and($unknown->hasViolation('BR-CL-16'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-CL-16'))->toBeFalse();
});

it('requires an account id for credit transfers (BR-61) and on present accounts (BR-50)', function (): void {
    $noAccount = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '58')]));
    $emptyAccount = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '30', hasCreditTransfer: true)]));

    expect($noAccount->hasViolation('BR-61'))->toBeTrue()
        ->and($emptyAccount->hasViolation('BR-50'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-61'))->toBeFalse();
});

it('warns about a full card primary account number (BR-51)', function (): void {
    $full = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: '4111111111111111')]));
    $truncated = core()->validateModel(makeInvoice(paymentMeans: [new JohnWink\En16931\Model\PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: '1111')]));

    expect($full->hasViolation('BR-51'))->toBeTrue()
        ->and($truncated->hasViolation('BR-51'))->toBeFalse();
});

// ---- Schritt 4: Lieferung & Zeiträume ----

it('flags an invoicing period ending before it starts (BR-29)', function (): void {
    $inverted = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, invoicingPeriodStart: '2026-02-01', invoicingPeriodEnd: '2026-01-01'));
    $ordered = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, invoicingPeriodStart: '2026-01-01', invoicingPeriodEnd: '2026-01-31'));

    expect($inverted->hasViolation('BR-29'))->toBeTrue()
        ->and($ordered->hasViolation('BR-29'))->toBeFalse();
});

it('flags a line period ending before it starts (BR-30) and an empty one (BR-CO-20)', function (): void {
    $line = static fn (?string $start, ?string $end): InvoiceLine => new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00', hasPeriod: true, periodStart: $start, periodEnd: $end,
    );

    $inverted = core()->validateModel(makeInvoice(lines: [$line('2026-02-01', '2026-01-01')]));
    $empty = core()->validateModel(makeInvoice(lines: [$line(null, null)]));

    expect($inverted->hasViolation('BR-30'))->toBeTrue()
        ->and($empty->hasViolation('BR-CO-20'))->toBeTrue();
});

it('flags an empty invoicing period (BR-CO-19) but accepts a lone description code', function (): void {
    $empty = core()->validateModel(makeInvoice(hasInvoicingPeriod: true));
    $codeOnly = core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '35'));

    expect($empty->hasViolation('BR-CO-19'))->toBeTrue()
        ->and($codeOnly->hasViolation('BR-CO-19'))->toBeFalse();
});

it('requires a country on the deliver-to address (BR-57) from ISO 3166 (BR-CL-14)', function (): void {
    $missing = core()->validateModel(makeInvoice(deliverTo: new Party(city: 'Potsdam', postCode: '14467')));
    $invalid = core()->validateModel(makeInvoice(deliverTo: new Party(city: 'Potsdam', postCode: '14467', countryCode: 'XX')));

    expect($missing->hasViolation('BR-57'))->toBeTrue()
        ->and($invalid->hasViolation('BR-CL-14'))->toBeTrue();
});

it('requires delivery information on intra-community invoices (BR-IC-11, BR-IC-12)', function (): void {
    $line = new InvoiceLine(id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'K', taxRate: '0.00');
    $subtotal = new TaxSubtotal(category: 'K', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReason: 'Intra-community supply');

    $bare = core()->validateModel(makeInvoice(lines: [$line], taxSubtotals: [$subtotal], actualDeliveryDate: null));
    $complete = core()->validateModel(makeInvoice(
        lines: [$line],
        taxSubtotals: [$subtotal],
        deliverTo: new Party(city: 'Wien', postCode: '1010', countryCode: 'AT'),
    ));

    expect($bare->hasViolation('BR-IC-11'))->toBeTrue()
        ->and($bare->hasViolation('BR-IC-12'))->toBeTrue()
        ->and($complete->hasViolation('BR-IC-11'))->toBeFalse()
        ->and($complete->hasViolation('BR-IC-12'))->toBeFalse();
});

// ---- Schritt 5: Dokumente & Zeilen-Details ----

it('requires a distinct payee name (BR-17)', function (): void {
    $sameAsSeller = core()->validateModel(makeInvoice(payee: new Party(name: 'Seller GmbH')));
    $nameless = core()->validateModel(makeInvoice(payee: new Party(identifier: 'X')));
    $distinct = core()->validateModel(makeInvoice(payee: new Party(name: 'Factoring AG')));

    expect($sameAsSeller->hasViolation('BR-17'))->toBeTrue()
        ->and($nameless->hasViolation('BR-17'))->toBeTrue()
        ->and($distinct->hasViolation('BR-17'))->toBeFalse();
});

it('requires a postal address on the tax representative (BR-19)', function (): void {
    $withoutAddress = core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', vatId: 'DE999999999')));

    expect($withoutAddress->hasViolation('BR-19'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(taxRepresentative: new Party(name: 'Rep', vatId: 'DE999999999', countryCode: 'DE')))->hasViolation('BR-19'))->toBeFalse();
});

it('flags a negative gross price (BR-28)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00', grossPrice: '-5.00',
    )]));

    expect($result->hasViolation('BR-28'))->toBeTrue();
});

it('requires a reference on every supporting document (BR-52) with a valid MIME code (BR-CL-24)', function (): void {
    $withoutReference = core()->validateModel(makeInvoice(attachments: [new JohnWink\En16931\Model\Attachment(filename: 'a.pdf', mimeCode: 'application/pdf')]));
    $badMime = core()->validateModel(makeInvoice(attachments: [new JohnWink\En16931\Model\Attachment(reference: 'DOC-1', mimeCode: 'text/html')]));

    expect($withoutReference->hasViolation('BR-52'))->toBeTrue()
        ->and($badMime->hasViolation('BR-CL-24'))->toBeTrue()
        ->and($badMime->hasViolation('BR-52'))->toBeFalse();
});

it('requires BT-111 when an accounting currency is set (BR-53, BR-DEC-15)', function (): void {
    $missing = core()->validateModel(makeInvoice(taxCurrency: 'USD'));
    $tooManyDecimals = core()->validateModel(makeInvoice(taxCurrency: 'USD', totals: new JohnWink\En16931\Model\Totals(
        lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00',
        paidAmount: '0.00', payableAmount: '119.00', taxTotalAccounting: '20.123',
    )));

    expect($missing->hasViolation('BR-53'))->toBeTrue()
        ->and($tooManyDecimals->hasViolation('BR-53'))->toBeFalse()
        ->and($tooManyDecimals->hasViolation('BR-DEC-15'))->toBeTrue();
});

it('requires name and value on item attributes (BR-54) and schemes on item identifiers (BR-64, BR-65)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemStandardId: '4012345001235',
        itemClassifications: [new JohnWink\En16931\Model\ItemClassification(code: '10201000')],
        attributes: [new JohnWink\En16931\Model\ItemAttribute(name: 'Farbe')],
    );

    $result = core()->validateModel(makeInvoice(lines: [$line]));

    expect($result->hasViolation('BR-54'))->toBeTrue()
        ->and($result->hasViolation('BR-64'))->toBeTrue()
        ->and($result->hasViolation('BR-65'))->toBeTrue();
});

it('requires a reference on preceding invoices (BR-55)', function (): void {
    expect(core()->validateModel(makeInvoice(precedingInvoiceReferences: [null]))->hasViolation('BR-55'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(precedingInvoiceReferences: ['R-2025-9']))->hasViolation('BR-55'))->toBeFalse();
});

it('forbids combining tax point date and code (BR-CO-03)', function (): void {
    $both = core()->validateModel(makeInvoice(taxPointDate: '2026-01-15', hasInvoicingPeriod: true, taxPointDateCode: '35'));

    expect($both->hasViolation('BR-CO-03'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(taxPointDate: '2026-01-15'))->hasViolation('BR-CO-03'))->toBeFalse();
});

it('mirrors the reason requirements under the BR-CO ids (BR-CO-21..24)', function (): void {
    $document = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00'),
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: true, amount: '5.00', taxCategory: 'S', taxRate: '19.00'),
    ]));
    $line = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [
            new JohnWink\En16931\Model\LineAllowanceCharge(isCharge: false, amount: '5.00'),
            new JohnWink\En16931\Model\LineAllowanceCharge(isCharge: true, amount: '2.00'),
        ],
    )]));

    expect($document->hasViolation('BR-CO-21'))->toBeTrue()
        ->and($document->hasViolation('BR-CO-22'))->toBeTrue()
        ->and($line->hasViolation('BR-CO-23'))->toBeTrue()
        ->and($line->hasViolation('BR-CO-24'))->toBeTrue();
});

it('checks decimals on base amounts and line allowances (BR-DEC-02/05/06/24/25/27/28)', function (): void {
    $document = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.123', taxCategory: 'S', taxRate: '19.00', reason: 'Rabatt', baseAmount: '100.123'),
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: true, amount: '5.123', taxCategory: 'S', taxRate: '19.00', reason: 'Zuschlag', baseAmount: '50.123'),
    ]));
    $line = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [
            new JohnWink\En16931\Model\LineAllowanceCharge(isCharge: false, amount: '5.123', reason: 'Rabatt', baseAmount: '10.123'),
            new JohnWink\En16931\Model\LineAllowanceCharge(isCharge: true, amount: '2.123', reason: 'Zuschlag', baseAmount: '20.123'),
        ],
    )]));

    expect($document->hasViolation('BR-DEC-01'))->toBeTrue()
        ->and($document->hasViolation('BR-DEC-02'))->toBeTrue()
        ->and($document->hasViolation('BR-DEC-05'))->toBeTrue()
        ->and($document->hasViolation('BR-DEC-06'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-24'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-25'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-27'))->toBeTrue()
        ->and($line->hasViolation('BR-DEC-28'))->toBeTrue();
});

// ---- Schritt 6: Codelisten ----

it('validates amount currency attributes (BR-CL-03) and the tax currency (BR-CL-05)', function (): void {
    $badAmountCurrency = core()->validateModel(makeInvoice(amountCurrencyCodes: ['EUR', 'FOO']));
    $badTaxCurrency = core()->validateModel(makeInvoice(taxCurrency: 'ZZZ', totals: new JohnWink\En16931\Model\Totals(
        lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00',
        paidAmount: '0.00', payableAmount: '119.00', taxTotalAccounting: '19.00',
    )));

    expect($badAmountCurrency->hasViolation('BR-CL-03'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(amountCurrencyCodes: ['EUR']))->hasViolation('BR-CL-03'))->toBeFalse()
        ->and($badTaxCurrency->hasViolation('BR-CL-05'))->toBeTrue();
});

it('reports an invalid invoice currency under the official id BR-CL-04', function (): void {
    $result = core()->validateModel(makeInvoice(currency: 'ZZZ'));

    expect($result->hasViolation('BR-CL-04'))->toBeTrue()
        ->and($result->hasViolation('BR-CL-03'))->toBeFalse();
});

it('reports an invalid line VAT category under the official id BR-CL-18', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'XX', taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-CL-18'))->toBeTrue();
});

it('validates the tax point date code (BR-CL-06)', function (): void {
    expect(core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '99'))->hasViolation('BR-CL-06'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(hasInvoicingPeriod: true, taxPointDateCode: '35'))->hasViolation('BR-CL-06'))->toBeFalse();
});

it('validates object identifier schemes (BR-CL-07) and note subject codes (BR-CL-08)', function (): void {
    $badScheme = core()->validateModel(makeInvoice(attachments: [new JohnWink\En16931\Model\Attachment(reference: 'OBJ-1', typeCode: '130', scheme: 'ZZ9')]));
    $badNote = core()->validateModel(makeInvoice(notes: ['#XY9#Hinweistext']));

    expect($badScheme->hasViolation('BR-CL-07'))->toBeTrue()
        ->and($badNote->hasViolation('BR-CL-08'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice(notes: ['Hinweis ohne Code']))->hasViolation('BR-CL-08'))->toBeFalse();
});

it('validates party identification schemes (BR-CL-10, BR-CL-11)', function (): void {
    $badIdScheme = core()->validateModel(makeInvoice(seller: new Party(name: 'S', countryCode: 'DE', vatId: 'DE123456789', identifier: 'X', identifierScheme: '9999')));
    $badLegalScheme = core()->validateModel(makeInvoice(buyer: new Party(name: 'B', countryCode: 'DE', vatId: 'DE987654321', legalRegistrationId: 'HRB 1', legalRegistrationIdScheme: '9999')));

    expect($badIdScheme->hasViolation('BR-CL-10'))->toBeTrue()
        ->and($badLegalScheme->hasViolation('BR-CL-11'))->toBeTrue();
});

it('validates item classification and standard identifier schemes (BR-CL-13, BR-CL-21) and origin country (BR-CL-15)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemStandardId: '4012345001235', itemStandardIdScheme: '9999',
        itemClassifications: [new JohnWink\En16931\Model\ItemClassification(code: '10201000', scheme: 'XYZ1')],
        originCountryCode: 'X1',
    );

    $result = core()->validateModel(makeInvoice(lines: [$line]));

    expect($result->hasViolation('BR-CL-13'))->toBeTrue()
        ->and($result->hasViolation('BR-CL-21'))->toBeTrue()
        ->and($result->hasViolation('BR-CL-15'))->toBeTrue();
});

it('validates allowance and charge reason codes (BR-CL-19, BR-CL-20)', function (): void {
    $document = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '999'),
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: true, amount: '5.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '999'),
    ]));
    $valid = core()->validateModel(makeInvoice(allowanceCharges: [
        new JohnWink\En16931\Model\DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reasonCode: '95'),
    ]));

    expect($document->hasViolation('BR-CL-19'))->toBeTrue()
        ->and($document->hasViolation('BR-CL-20'))->toBeTrue()
        ->and($valid->hasViolation('BR-CL-19'))->toBeFalse();
});

it('validates exemption reason codes case-insensitively (BR-CL-22)', function (): void {
    $bad = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReasonCode: 'VATEX-EU-999',
    )]));
    $lowercase = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: 'E', rate: '0.00', taxableAmount: '100.00', taxAmount: '0.00', exemptionReasonCode: 'vatex-eu-132',
    )]));

    expect($bad->hasViolation('BR-CL-22'))->toBeTrue()
        ->and($lowercase->hasViolation('BR-CL-22'))->toBeFalse();
});

it('validates unit codes against UN/ECE Rec 20+21 (BR-CL-23)', function (): void {
    $result = core()->validateModel(makeInvoice(lines: [new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'XYZ9',
        taxCategory: 'S', taxRate: '19.00',
    )]));

    expect($result->hasViolation('BR-CL-23'))->toBeTrue()
        ->and(core()->validateModel(makeInvoice())->hasViolation('BR-CL-23'))->toBeFalse();
});

it('validates the delivery location scheme (BR-CL-26)', function (): void {
    $result = core()->validateModel(makeInvoice(deliverTo: new Party(
        city: 'Potsdam', postCode: '14467', countryCode: 'DE', identifier: 'LOC-1', identifierScheme: '9999',
    )));

    expect($result->hasViolation('BR-CL-26'))->toBeTrue();
});
