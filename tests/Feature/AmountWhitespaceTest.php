<?php

declare(strict_types=1);

use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\SubInvoiceLine;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\ThirdPartyPayment;
use JohnWink\En16931\Model\Totals;
use JohnWink\En16931\Severity;
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

$hasFatal = static fn (ValidationResult $validationResult, string $ruleId): bool => array_any($validationResult->violations, static fn (Violation $violation): bool => $violation->ruleId === $ruleId && $violation->severity === Severity::Fatal);

$noBreakSpace = static fn (string $value): string => "\u{00A0}{$value}\u{00A0}";

$allTotals = [
    'lineTotal' => '100.00',
    'allowanceTotal' => '0.00',
    'chargeTotal' => '0.00',
    'taxBasisTotal' => '100.00',
    'taxTotal' => '19.00',
    'taxTotalAccounting' => '19.00',
    'grandTotal' => '119.00',
    'paidAmount' => '0.00',
    'roundingAmount' => '0.00',
    'payableAmount' => '119.00',
];

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

    $validationResult = xr()->validateModel($scenario($plain));
    $actual = xr()->validateModel($scenario($form));

    expect($validationResult->hasViolation($ruleId))->toBe($violates)
        ->and($actual->hasViolation($ruleId))->toBe($violates)
        ->and($violationIds($actual))->toBe($violationIds($validationResult));
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

it('fails closed on a filled total that is not an xs:decimal', function (array $framed, string $ruleId) use ($allTotals, $hasFatal): void {
    $validationResult = core()->validateModel(makeInvoice(totals: new Totals(...$allTotals)));
    $actual = core()->validateModel(makeInvoice(totals: new Totals(...[...$allTotals, ...$framed])));

    expect($validationResult->isValid())->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, $ruleId))->toBeTrue();
})->with([
    'BT-106 (BR-CO-10)' => [['lineTotal' => "\u{00A0}100.00\u{00A0}"], 'BR-CO-10'],
    'BT-106 as an operand (BR-CO-13)' => [['lineTotal' => "\u{00A0}100.00\u{00A0}"], 'BR-CO-13'],
    'BT-107 (BR-CO-11)' => [['allowanceTotal' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-11'],
    'BT-107 as an operand (BR-CO-13)' => [['allowanceTotal' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-13'],
    'BT-108 (BR-CO-12)' => [['chargeTotal' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-12'],
    'BT-108 as an operand (BR-CO-13)' => [['chargeTotal' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-13'],
    'BT-109 (BR-CO-13)' => [['taxBasisTotal' => "\u{00A0}100.00\u{00A0}"], 'BR-CO-13'],
    'BT-109 as an operand (BR-CO-15)' => [['taxBasisTotal' => "\u{00A0}100.00\u{00A0}"], 'BR-CO-15'],
    'BT-110 (BR-CO-14)' => [['taxTotal' => "\u{00A0}19.00\u{00A0}"], 'BR-CO-14'],
    'BT-110 as an operand (BR-CO-15)' => [['taxTotal' => "\u{00A0}19.00\u{00A0}"], 'BR-CO-15'],
    'BT-111 (BR-DEC-15)' => [['taxTotalAccounting' => "\u{00A0}19\u{00A0}"], 'BR-DEC-15'],
    'BT-112 (BR-CO-15)' => [['grandTotal' => "\u{00A0}119.00\u{00A0}"], 'BR-CO-15'],
    'BT-112 as an operand (BR-CO-16)' => [['grandTotal' => "\u{00A0}119.00\u{00A0}"], 'BR-CO-16'],
    'BT-113 as an operand (BR-CO-16)' => [['paidAmount' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-16'],
    'BT-114 as an operand (BR-CO-16)' => [['roundingAmount' => "\u{00A0}0.00\u{00A0}"], 'BR-CO-16'],
    'BT-115 (BR-CO-16)' => [['payableAmount' => "\u{00A0}119.00\u{00A0}"], 'BR-CO-16'],
    'BT-115 (BR-CO-25)' => [['payableAmount' => "\u{00A0}119.00\u{00A0}"], 'BR-CO-25'],
    'BT-112 and BT-115 both 999.00 (BR-CO-15)' => [['grandTotal' => "\u{00A0}999.00\u{00A0}", 'payableAmount' => "\u{00A0}999.00\u{00A0}"], 'BR-CO-15'],
]);

it('fails closed on a filled line, allowance, charge or category tax amount that is not an xs:decimal', function (Closure $scenario, string $ruleId) use ($hasFatal, $noBreakSpace): void {
    $validationResult = core()->validateModel($scenario(static fn (string $value): string => $value));
    $actual = core()->validateModel($scenario($noBreakSpace));

    expect($validationResult->isValid())->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, $ruleId))->toBeTrue();
})->with([
    'BT-131 (BR-CO-10)' => [static fn (Closure $pad): Invoice => makeInvoice(lines: [$line($pad)]), 'BR-CO-10'],
    'BT-92 (BR-CO-11)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: new Totals(lineTotal: '100.00', allowanceTotal: '10.00', taxBasisTotal: '90.00', taxTotal: '17.10', grandTotal: '107.10', paidAmount: '0.00', payableAmount: '107.10'), taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '90.00', taxAmount: '17.10')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: $pad('10.00'), taxCategory: 'S', taxRate: '19.00', reason: 'Rabatt')]), 'BR-CO-11'],
    'BT-99 (BR-CO-12)' => [static fn (Closure $pad): Invoice => makeInvoice(totals: new Totals(lineTotal: '100.00', chargeTotal: '10.00', taxBasisTotal: '110.00', taxTotal: '20.90', grandTotal: '130.90', paidAmount: '0.00', payableAmount: '130.90'), taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '110.00', taxAmount: '20.90')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: true, amount: $pad('10.00'), taxCategory: 'S', taxRate: '19.00', reason: 'Fracht')]), 'BR-CO-12'],
    'BT-117 as an operand (BR-CO-14)' => [static fn (Closure $pad): Invoice => makeInvoice(taxSubtotals: [$subtotal($pad)]), 'BR-CO-14'],
]);

it('fails closed on a filled BR-DEX-09 amount that is not an xs:decimal', function (Closure $scenario) use ($hasFatal, $noBreakSpace): void {
    $validationResult = xr()->validateModel($scenario(static fn (string $value): string => $value));
    $actual = xr()->validateModel($scenario($noBreakSpace));

    expect($validationResult->hasViolation('BR-DEX-09'))->toBeFalse()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, 'BR-DEX-09'))->toBeTrue();
})->with([
    'BT-112' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: $pad('119.00'), paidAmount: '0.00', roundingAmount: '0.00', payableAmount: '129.00'), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: '10.00', description: 'Porto', currency: 'EUR')])],
    'BT-113' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: $pad('0.00'), roundingAmount: '0.00', payableAmount: '129.00'), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: '10.00', description: 'Porto', currency: 'EUR')])],
    'BT-114' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: '0.00', roundingAmount: $pad('0.00'), payableAmount: '129.00'), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: '10.00', description: 'Porto', currency: 'EUR')])],
    'BT-115' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: '0.00', roundingAmount: '0.00', payableAmount: $pad('129.00')), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: '10.00', description: 'Porto', currency: 'EUR')])],
    'BT-DEX-002' => [static fn (Closure $pad): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: '0.00', roundingAmount: '0.00', payableAmount: '129.00'), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: $pad('10.00'), description: 'Porto', currency: 'EUR')])],
]);

it('counts the decimals of the xs:decimal reading (BR-DEC-20, BR-DEC-23, BR-DEX-13)', function (Closure $form, Closure $scenario, string $ruleId) use ($violationIds): void {
    $validationResult = xr()->validateModel($scenario(static fn (string $value): string => $value));
    $actual = xr()->validateModel($scenario($form));

    expect($validationResult->hasViolation($ruleId))->toBeTrue()
        ->and($actual->hasViolation($ruleId))->toBeTrue()
        ->and($violationIds($actual))->toBe($violationIds($validationResult));
})->with([
    'with a leading plus' => [static fn (string $value): string => "+{$value}"],
    'without integer digits' => [static fn (string $value): string => mb_substr($value, 1)],
])->with([
    'BT-131 (BR-DEC-23)' => [static fn (Closure $form): Invoice => makeInvoice(lines: [$line(static fn (string $value): string => $value, netAmount: $form('0.505'))]), 'BR-DEC-23'],
    'BT-117 (BR-DEC-20)' => [static fn (Closure $form): Invoice => makeInvoice(taxSubtotals: [$subtotal(static fn (string $value): string => $value, taxAmount: $form('0.195'))]), 'BR-DEC-20'],
    'BT-DEX-002 (BR-DEX-13)' => [static fn (Closure $form): Invoice => makeInvoice(customizationId: $extensionId, totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: '0.00', payableAmount: '119.505'), thirdPartyPayments: [new ThirdPartyPayment(id: 'DL', amount: $form('0.505'), description: 'Porto', currency: 'EUR')]), 'BR-DEX-13'],
]);

it('fails closed on a zero-tax BT-117 that is not an xs:decimal (BR-CO-17, BR-*-09)', function (string $category, string $ruleId, ?string $rate, string $taxAmount, bool $violates) use ($hasFatal): void {
    $validationResult = core()->validateModel(makeInvoice(taxSubtotals: [new TaxSubtotal(
        category: $category,
        rate: $rate,
        taxableAmount: '100.00',
        taxAmount: $taxAmount,
    )]));

    expect($hasFatal($validationResult, 'BR-CO-17'))->toBe($violates)
        ->and($hasFatal($validationResult, $ruleId))->toBe($violates);
})->with([
    'Z' => ['Z', 'BR-Z-09', '0.00'],
    'E' => ['E', 'BR-E-09', '0.00'],
    'AE' => ['AE', 'BR-AE-09', '0.00'],
    'K' => ['K', 'BR-IC-09', '0.00'],
    'G' => ['G', 'BR-G-09', '0.00'],
    'O' => ['O', 'BR-O-09', null],
])->with([
    'plain zero' => ['0.00', false],
    'zero framed by XML whitespace' => [" 0.00\t", false],
    'zero framed by a no-break space' => ["\u{00A0}0.00\u{00A0}", true],
    'one framed by a no-break space' => ["\u{00A0}1.00\u{00A0}", true],
]);

it('fails closed on a filled amount without a decimal point that is not an xs:decimal', function (Closure $frame, Closure $scenario, string $ruleId) use ($hasFatal): void {
    $validationResult = core()->validateModel($scenario(static fn (string $value): string => $value));
    $actual = core()->validateModel($scenario($frame));

    expect($validationResult->isValid())->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, $ruleId))->toBeTrue();
})->with([
    'framed by no-break spaces' => [static fn (string $value): string => "\u{00A0}{$value}\u{00A0}"],
    'framed by ideographic spaces' => [static fn (string $value): string => "\u{3000}{$value}\u{3000}"],
])->with([
    'BT-146 (BR-27)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: $frame('100'), quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00')]), 'BR-27'],
    'BT-148 (BR-28)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', grossPrice: $frame('100'))]), 'BR-28'],
    'BT-111 (BR-DEC-15)' => [static fn (Closure $frame): Invoice => makeInvoice(taxCurrency: 'USD', totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', taxTotalAccounting: $frame('19'), grandTotal: '119.00', paidAmount: '0.00', payableAmount: '119.00')), 'BR-DEC-15'],
    'BT-93 (BR-DEC-02)' => [static fn (Closure $frame): Invoice => makeInvoice(totals: new Totals(lineTotal: '100.00', allowanceTotal: '10.00', taxBasisTotal: '90.00', taxTotal: '17.10', grandTotal: '107.10', paidAmount: '0.00', payableAmount: '107.10'), taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '90.00', taxAmount: '17.10')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reason: 'Rabatt', baseAmount: $frame('100'))]), 'BR-DEC-02'],
    'BT-100 (BR-DEC-06)' => [static fn (Closure $frame): Invoice => makeInvoice(totals: new Totals(lineTotal: '100.00', chargeTotal: '10.00', taxBasisTotal: '110.00', taxTotal: '20.90', grandTotal: '130.90', paidAmount: '0.00', payableAmount: '130.90'), taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '110.00', taxAmount: '20.90')], allowanceCharges: [new DocumentAllowanceCharge(isCharge: true, amount: '10.00', taxCategory: 'S', taxRate: '19.00', reason: 'Fracht', baseAmount: $frame('100'))]), 'BR-DEC-06'],
    'BT-136 (BR-DEC-24)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', allowanceCharges: [new LineAllowanceCharge(isCharge: false, amount: $frame('5'), reason: 'Rabatt', baseAmount: '10.00')])]), 'BR-DEC-24'],
    'BT-137 (BR-DEC-25)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', allowanceCharges: [new LineAllowanceCharge(isCharge: false, amount: '5.00', reason: 'Rabatt', baseAmount: $frame('10'))])]), 'BR-DEC-25'],
    'BT-141 (BR-DEC-27)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', allowanceCharges: [new LineAllowanceCharge(isCharge: true, amount: $frame('5'), reason: 'Zuschlag', baseAmount: '10.00')])]), 'BR-DEC-27'],
    'BT-142 (BR-DEC-28)' => [static fn (Closure $frame): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', allowanceCharges: [new LineAllowanceCharge(isCharge: true, amount: '5.00', reason: 'Zuschlag', baseAmount: $frame('10'))])]), 'BR-DEC-28'],
]);

it('treats a mandatory total of only U+00A0 or U+3000 as missing (BR-12 to BR-15)', function (string $blank, string $field, string $ruleId) use ($allTotals, $hasFatal): void {
    $validationResult = core()->validateModel(makeInvoice(totals: new Totals(...$allTotals)));
    $actual = core()->validateModel(makeInvoice(totals: new Totals(...[...$allTotals, $field => $blank])));

    expect($validationResult->isValid())->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($hasFatal($actual, $ruleId))->toBeTrue();
})->with([
    'only a no-break space' => ["\u{00A0}"],
    'only an ideographic space' => ["\u{3000}"],
    'no-break and ideographic spaces' => ["\u{00A0}\u{3000}\u{00A0}"],
])->with([
    'BT-106 (BR-12)' => ['lineTotal', 'BR-12'],
    'BT-109 (BR-13)' => ['taxBasisTotal', 'BR-13'],
    'BT-112 (BR-14)' => ['grandTotal', 'BR-14'],
    'BT-115 (BR-15)' => ['payableAmount', 'BR-15'],
]);

it('treats an optional BT-110 of only U+00A0 or U+3000 as absent', function (string $blank) use ($allTotals, $violationIds): void {
    $absent = core()->validateModel(makeInvoice(totals: new Totals(...[...$allTotals, 'taxTotal' => null])));
    $actual = core()->validateModel(makeInvoice(totals: new Totals(...[...$allTotals, 'taxTotal' => $blank])));

    expect($absent->hasViolation('BR-CO-15'))->toBeTrue()
        ->and($actual->isValid())->toBeFalse()
        ->and($violationIds($actual))->toBe($violationIds($absent));
})->with([
    'only a no-break space' => ["\u{00A0}"],
    'only an ideographic space' => ["\u{3000}"],
    'no-break and ideographic spaces' => ["\u{00A0}\u{3000}\u{00A0}"],
]);
