<?php

declare(strict_types=1);

use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\SubInvoiceLine;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\ThirdPartyPayment;
use JohnWink\En16931\Model\Totals;
use JohnWink\En16931\ValidationResult;
use JohnWink\En16931\Violation;

$extensionId = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0';

$totals = static fn (Closure $pad, string $lineTotal = '100.00', string $taxBasisTotal = '100.00', string $taxTotal = '19.00', string $grandTotal = '119.00', string $payableAmount = '119.00', ?string $allowanceTotal = null, ?string $chargeTotal = null): Totals => new Totals(
    lineTotal: $pad($lineTotal),
    allowanceTotal: $allowanceTotal === null ? null : $pad($allowanceTotal),
    chargeTotal: $chargeTotal === null ? null : $pad($chargeTotal),
    taxBasisTotal: $pad($taxBasisTotal),
    taxTotal: $pad($taxTotal),
    grandTotal: $pad($grandTotal),
    paidAmount: $pad('0.00'),
    roundingAmount: $pad('0.00'),
    payableAmount: $pad($payableAmount),
);

$line = static fn (Closure $pad, string $netAmount = '100.00', string $netPrice = '100.00', string $category = 'S', string $rate = '19.00', ?string $grossPrice = null, array $subLines = []): InvoiceLine => new InvoiceLine(
    id: '1',
    name: 'Beratung',
    netAmount: $pad($netAmount),
    netPrice: $pad($netPrice),
    quantity: $pad('1'),
    unitCode: 'C62',
    taxCategory: $category,
    taxRate: $pad($rate),
    grossPrice: $grossPrice === null ? null : $pad($grossPrice),
    subLines: $subLines,
);

$subtotal = static fn (Closure $pad, string $category = 'S', string $rate = '19.00', string $taxableAmount = '100.00', string $taxAmount = '19.00'): TaxSubtotal => new TaxSubtotal(
    category: $category,
    rate: $pad($rate),
    taxableAmount: $pad($taxableAmount),
    taxAmount: $pad($taxAmount),
);

$violationIds = static fn (ValidationResult $validationResult): array => array_map(static fn (Violation $violation): string => $violation->ruleId, $validationResult->violations);

it('reads XML whitespace around BT-117 and BT-110 for BR-CO-14', function (string $taxTotal, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(
        totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: $taxTotal, grandTotal: '119.00', paidAmount: '0.00', payableAmount: '119.00'),
        taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: $taxAmount)],
    ));

    expect($validationResult->hasViolation('BR-CO-14'))->toBe($violates);
})->with([
    'spaces around BT-117' => ['19.00', ' 19.00 ', false],
    'tab and line feed around BT-117' => ['19.00', "\t19.00\n", false],
    'carriage return and line feed around BT-110' => ["\r\n19.00\r\n", '19.00', false],
    'whitespace around BT-110 and BT-117' => [' 19.00 ', "\t19.00\t", false],
    'BT-117 one cent off inside whitespace' => ['19.00', ' 19.01 ', true],
    'BT-110 one cent off inside whitespace' => [' 19.01 ', '19.00', true],
    'no-break space around BT-117 is not an xs:decimal' => ['19.00', "\u{00A0}19.00\u{00A0}", true],
]);

it('reads XML whitespace around BT-116, BT-119 and BT-117 for BR-S/AF/AG-09', function (string $category, string $ruleId, string $rate, string $taxableAmount, string $taxAmount, bool $violates): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: $category,
        rate: $rate,
        taxableAmount: $taxableAmount,
        taxAmount: $taxAmount,
    )]));

    expect($validationResult->hasViolation($ruleId))->toBe($violates);
})->with([
    'S' => ['S', 'BR-S-09'],
    'L' => ['L', 'BR-IG-09'],
    'M' => ['M', 'BR-IP-09'],
])->with([
    'spaces around BT-117' => ['19.00', '100.00', ' 19.00 ', false],
    'tab and line feed around BT-116' => ['19.00', "\t100.00\n", '19.00', false],
    'carriage return around BT-119' => ["\r19.00\r", '100.00', '19.00', false],
    'credit note inside whitespace' => [' 19.00 ', ' -100.00 ', ' -19.00 ', false],
    'one unit off inside whitespace' => ['19.00', ' 100.00 ', ' 20.00 ', true],
    'six units off inside whitespace' => [' 19.00 ', '100.00', "\t25.00\t", true],
]);

it('gives the same verdict when every amount is written in another xs:decimal form', function (Closure $form, Closure $scenario, string $ruleId, bool $violates) use ($violationIds): void {
    $plain = static fn (string $value): string => $value;

    $expected = xr()->validateModel($scenario($plain));
    $actual = xr()->validateModel($scenario($form));

    expect($expected->hasViolation($ruleId))->toBe($violates)
        ->and($actual->hasViolation($ruleId))->toBe($violates)
        ->and($violationIds($actual))->toBe($violationIds($expected));
})->with([
    'framed by XML whitespace' => [static fn (string $value): string => " \t{$value}\r\n"],
    'with a leading plus' => [static fn (string $value): string => str_starts_with($value, '-') ? $value : "+{$value}"],
])->with([
    'balanced invoice (BR-CO-14)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-14', false],
    'BT-110 off the category sum (BR-CO-14)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '19.01', grandTotal: '119.01', payableAmount: '119.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-14', true],
    'BT-106 off the line sum (BR-CO-10)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, lineTotal: '100.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-10', true],
    'BT-109 off (BR-CO-13)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxBasisTotal: '100.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-13', true],
    'BT-112 off (BR-CO-15)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, grandTotal: '119.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-15', true],
    'BT-115 off (BR-CO-16)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, payableAmount: '119.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-CO-16', true],
    'BT-107 off the allowance sum (BR-CO-11)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxBasisTotal: '90.00', taxTotal: '17.10', grandTotal: '107.10', payableAmount: '107.10', allowanceTotal: '10.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxableAmount: '90.00', taxAmount: '17.10')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: $pad('10.00'), taxCategory: 'S', taxRate: $pad('19.00'), reason: 'Rabatt')]), 'BR-CO-11', true],
    'BT-108 off the charge sum (BR-CO-12)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxBasisTotal: '110.00', taxTotal: '20.90', grandTotal: '130.90', payableAmount: '130.90', chargeTotal: '10.01'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxableAmount: '110.00', taxAmount: '20.90')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: true, amount: $pad('10.00'), taxCategory: 'S', taxRate: $pad('19.00'), reason: 'Fracht')]), 'BR-CO-12', true],
    'balanced allowance and charge (BR-S-08)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxBasisTotal: '105.00', taxTotal: '19.95', grandTotal: '124.95', payableAmount: '124.95', allowanceTotal: '5.00', chargeTotal: '10.00'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxableAmount: '105.00', taxAmount: '19.95')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: $pad('5.00'), taxCategory: 'S', taxRate: $pad('19.00'), reason: 'Rabatt'), new DocumentAllowanceCharge(isCharge: true, amount: $pad('10.00'), taxCategory: 'S', taxRate: $pad('19.00'), reason: 'Fracht')]), 'BR-S-08', false],
    'BT-116 off the line sum (BR-S-08)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxableAmount: '100.01')]), 'BR-S-08', true],
    'BT-117 off the product (BR-S-09)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '21.00', grandTotal: '121.00', payableAmount: '121.00'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxAmount: '21.00')]), 'BR-S-09', true],
    'negative item net price (BR-27)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad, netPrice: '-100.00')], taxSubtotals: [$subtotal($pad)]), 'BR-27', true],
    'negative item gross price (BR-28)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad, grossPrice: '-100.00')], taxSubtotals: [$subtotal($pad)]), 'BR-28', true],
    'positive standard line rate (BR-S-05)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)]), 'BR-S-05', false],
    'zero standard line rate (BR-S-05)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '0.00', grandTotal: '100.00', payableAmount: '100.00'), lines: [$line($pad, rate: '0.00')], taxSubtotals: [$subtotal($pad, rate: '0.00', taxAmount: '0.00')]), 'BR-S-05', true],
    'zero-rated line with a rate (BR-Z-05)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '0.00', grandTotal: '100.00', payableAmount: '100.00'), lines: [$line($pad, category: 'Z', rate: '1.00')], taxSubtotals: [$subtotal($pad, category: 'Z', rate: '0.00', taxAmount: '0.00')]), 'BR-Z-05', true],
    'zero-rated line without a rate (BR-Z-05)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '0.00', grandTotal: '100.00', payableAmount: '100.00'), lines: [$line($pad, category: 'Z', rate: '0.00')], taxSubtotals: [$subtotal($pad, category: 'Z', rate: '0.00', taxAmount: '0.00')]), 'BR-Z-05', false],
    'zero-rated group with tax (BR-Z-09)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxTotal: '1.00', grandTotal: '101.00', payableAmount: '101.00'), lines: [$line($pad, category: 'Z', rate: '0.00')], taxSubtotals: [$subtotal($pad, category: 'Z', rate: '0.00', taxAmount: '1.00')]), 'BR-Z-09', true],
    'negative IGIC line rate (BR-IG-05)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad, category: 'L', rate: '-7.00')], taxSubtotals: [$subtotal($pad, category: 'L', rate: '19.00')]), 'BR-IG-05', true],
    'zero standard allowance rate (BR-S-06)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad, taxBasisTotal: '90.00', taxTotal: '17.10', grandTotal: '107.10', payableAmount: '107.10', allowanceTotal: '10.00'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad, taxableAmount: '90.00', taxAmount: '17.10')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: $pad('10.00'), taxCategory: 'S', taxRate: $pad('0.00'), reason: 'Rabatt')]), 'BR-S-06', true],
    'positive amount due without terms (BR-CO-25)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: $totals($pad), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)], paymentDueDate: null, paymentTerms: null), 'BR-CO-25', true],
    'sub lines off the line net amount (BR-DEX-02)' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: $totals($pad), lines: [$line($pad, subLines: [new SubInvoiceLine(netAmount: $pad('60.00'), vatCategoryCount: 1), new SubInvoiceLine(netAmount: $pad('40.01'), vatCategoryCount: 1)])], taxSubtotals: [$subtotal($pad)]), 'BR-DEX-02', true],
    'sub lines matching the line net amount (BR-DEX-02)' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: $totals($pad), lines: [$line($pad, subLines: [new SubInvoiceLine(netAmount: $pad('60.00'), vatCategoryCount: 1), new SubInvoiceLine(netAmount: $pad('40.00'), vatCategoryCount: 1)])], taxSubtotals: [$subtotal($pad)]), 'BR-DEX-02', false],
    'third party payment balanced (BR-DEX-09)' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: $totals($pad, payableAmount: '129.00'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)], thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: $pad('10.00'), description: 'Porto', currency: 'EUR')]), 'BR-DEX-09', false],
    'third party payment off (BR-DEX-09)' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: $totals($pad, payableAmount: '129.00'), lines: [$line($pad)], taxSubtotals: [$subtotal($pad)], thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: $pad('10.01'), description: 'Porto', currency: 'EUR')]), 'BR-DEX-09', true],
]);
