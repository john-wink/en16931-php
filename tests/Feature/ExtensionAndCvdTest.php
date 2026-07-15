<?php

declare(strict_types=1);

use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\ItemAttribute;
use JohnWink\En16931\Model\ItemClassification;
use JohnWink\En16931\Model\SubInvoiceLine;
use JohnWink\En16931\Model\ThirdPartyPayment;

const XR_CIUS = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';
const XR_EXTENSION = XR_CIUS.'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0';
const XR_CVD = XR_CIUS.'#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9';

// core() is defined in RuleCoverageTest.php and shared across the Pest suite.

/*
 * BR-DEX-* and BR-DE-CVD-* rules are gated on the specification identifier
 * (BT-24), so they must stay silent on a plain invoice and only fire once the
 * extension / CVD profile is declared.
 */

it('leaves the extension rules silent on a plain invoice', function (): void {
    $result = xr()->validateModel(makeInvoice(
        thirdPartyPayments: [new ThirdPartyPayment], // would break BR-DEX-10/11/12 under the extension
    ));

    expect($result->hasViolation('BR-DEX-10'))->toBeFalse()
        ->and($result->hasViolation('BR-DEX-11'))->toBeFalse();
});

it('requires type, amount and description on third party payments under the extension (BR-DEX-10/11/12)', function (): void {
    $result = xr()->validateModel(makeInvoice(
        customizationId: XR_EXTENSION,
        thirdPartyPayments: [new ThirdPartyPayment],
    ));

    expect($result->hasViolation('BR-DEX-10'))->toBeTrue()
        ->and($result->hasViolation('BR-DEX-11'))->toBeTrue()
        ->and($result->hasViolation('BR-DEX-12'))->toBeTrue();
});

it('checks third party payment decimals and currency (BR-DEX-13/14)', function (): void {
    $badDecimals = xr()->validateModel(makeInvoice(
        customizationId: XR_EXTENSION,
        thirdPartyPayments: [new ThirdPartyPayment(id: 'T', amount: '10.123', description: 'x', currency: 'EUR')],
    ));
    $wrongCurrency = xr()->validateModel(makeInvoice(
        customizationId: XR_EXTENSION,
        thirdPartyPayments: [new ThirdPartyPayment(id: 'T', amount: '10.00', description: 'x', currency: 'USD')],
    ));

    expect($badDecimals->hasViolation('BR-DEX-13'))->toBeTrue()
        ->and($wrongCurrency->hasViolation('BR-DEX-14'))->toBeTrue();
});

it('reconciles the amount due with third party payments (BR-DEX-09)', function (): void {
    // Official formula: BT-115 = BT-112 − BT-113 + BT-114 + Σ third party amount.
    // Grand total 119.00 + 19.00 third party → amount due 138.00.
    $balanced = xr()->validateModel(makeInvoice(
        customizationId: XR_EXTENSION,
        totals: new JohnWink\En16931\Model\Totals(
            lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00',
            paidAmount: '0.00', payableAmount: '138.00',
        ),
        thirdPartyPayments: [new ThirdPartyPayment(id: 'T', amount: '19.00', description: 'x', currency: 'EUR')],
    ));
    $broken = xr()->validateModel(makeInvoice(
        customizationId: XR_EXTENSION,
        thirdPartyPayments: [new ThirdPartyPayment(id: 'T', amount: '19.00', description: 'x', currency: 'EUR')],
    ));

    expect($balanced->hasViolation('BR-DEX-09'))->toBeFalse()
        ->and($broken->hasViolation('BR-DEX-09'))->toBeTrue();
});

it('warns when a line net amount does not match its sub lines (BR-DEX-02)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        subLines: [
            new SubInvoiceLine(netAmount: '60.00', vatCategoryCount: 1),
            new SubInvoiceLine(netAmount: '30.00', vatCategoryCount: 1),
        ],
    );

    $result = xr()->validateModel(makeInvoice(customizationId: XR_EXTENSION, lines: [$line]));

    expect($result->hasViolation('BR-DEX-02'))->toBeTrue()
        ->and($result->isValid())->toBeTrue(); // warning only
});

it('requires exactly one VAT information per sub line (BR-DEX-03)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        subLines: [new SubInvoiceLine(netAmount: '100.00', vatCategoryCount: 0)],
    );

    expect(xr()->validateModel(makeInvoice(customizationId: XR_EXTENSION, lines: [$line]))->hasViolation('BR-DEX-03'))->toBeTrue();
});

it('allows application/xml attachments only under the extension (BR-DEX-01)', function (): void {
    $xmlAttachment = [new Attachment(reference: 'DOC-1', filename: 'a.xml', mimeCode: 'application/xml')];

    expect(xr()->validateModel(makeInvoice(customizationId: XR_EXTENSION, attachments: $xmlAttachment))->hasViolation('BR-DEX-01'))->toBeFalse()
        // Without the extension the plain MIME rule (BR-CL-24) rejects application/xml.
        ->and(xr()->validateModel(makeInvoice(attachments: $xmlAttachment))->hasViolation('BR-CL-24'))->toBeTrue();
});

it('leaves the CVD rules silent on a plain invoice', function (): void {
    expect(xr()->validateModel(makeInvoice())->hasViolation('BR-DE-CVD-01'))->toBeFalse();
});

it('requires contract and tender references on a CVD invoice (BR-DE-CVD-01/02)', function (): void {
    $result = xr()->validateModel(makeInvoice(customizationId: XR_CVD));

    expect($result->hasViolation('BR-DE-CVD-01'))->toBeTrue()
        ->and($result->hasViolation('BR-DE-CVD-02'))->toBeTrue();
});

it('requires a CVD classification with an allowed vehicle category and cva attribute (BR-DE-CVD-03/04/05)', function (): void {
    $goodLine = new InvoiceLine(
        id: '1', name: 'Fahrzeug', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemClassifications: [new ItemClassification(code: 'M1', scheme: 'CVD')],
        attributes: [new ItemAttribute(name: 'cva', value: 'clean')],
    );
    $badCategory = new InvoiceLine(
        id: '1', name: 'Fahrzeug', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemClassifications: [new ItemClassification(code: 'X9', scheme: 'CVD')],
        attributes: [new ItemAttribute(name: 'cva', value: 'spaceship')],
    );

    $complete = xr()->validateModel(makeInvoice(
        customizationId: XR_CVD, contractReference: 'C-1', tenderReference: 'T-1', lines: [$goodLine],
    ));
    $wrong = xr()->validateModel(makeInvoice(
        customizationId: XR_CVD, contractReference: 'C-1', tenderReference: 'T-1', lines: [$badCategory],
    ));
    $missingClassification = xr()->validateModel(makeInvoice(
        customizationId: XR_CVD, contractReference: 'C-1', tenderReference: 'T-1',
    ));

    expect($complete->hasViolation('BR-DE-CVD-03'))->toBeFalse()
        ->and($complete->hasViolation('BR-DE-CVD-04'))->toBeFalse()
        ->and($complete->hasViolation('BR-DE-CVD-05'))->toBeFalse()
        ->and($wrong->hasViolation('BR-DE-CVD-04'))->toBeTrue()
        ->and($wrong->hasViolation('BR-DE-CVD-05'))->toBeTrue()
        ->and($missingClassification->hasViolation('BR-DE-CVD-03'))->toBeTrue();
});

it('requires cva and CVD classification to pair up one-to-one (BR-DE-CVD-06-a/b)', function (): void {
    $classificationWithoutCva = new InvoiceLine(
        id: '1', name: 'Fahrzeug', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemClassifications: [new ItemClassification(code: 'M1', scheme: 'CVD')],
    );
    $cvaWithoutClassification = new InvoiceLine(
        id: '1', name: 'Fahrzeug', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        attributes: [new ItemAttribute(name: 'cva', value: 'clean')],
    );

    expect(xr()->validateModel(makeInvoice(customizationId: XR_CVD, contractReference: 'C-1', tenderReference: 'T-1', lines: [$classificationWithoutCva]))->hasViolation('BR-DE-CVD-06-a'))->toBeTrue()
        ->and(xr()->validateModel(makeInvoice(customizationId: XR_CVD, contractReference: 'C-1', tenderReference: 'T-1', lines: [$cvaWithoutClassification]))->hasViolation('BR-DE-CVD-06-b'))->toBeTrue();
});

it('warns about sub invoice lines under the extension (BR-DEX-15)', function (): void {
    $line = new InvoiceLine(
        id: '1', name: 'x', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        subLines: [new SubInvoiceLine(netAmount: '100.00', vatCategoryCount: 1)],
    );

    $ext = xr()->validateModel(makeInvoice(customizationId: XR_EXTENSION, lines: [$line]));

    expect($ext->hasViolation('BR-DEX-15'))->toBeTrue()
        ->and($ext->isValid())->toBeTrue() // warning only
        ->and(xr()->validateModel(makeInvoice(lines: [$line]))->hasViolation('BR-DEX-15'))->toBeFalse();
});
