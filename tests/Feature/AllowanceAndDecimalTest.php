<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;

function validate(mixed ...$overrides): JohnWink\En16931\ValidationResult
{
    return En16931Validator::en16931()->validateModel(makeInvoice(...$overrides));
}

it('flags more than two decimals on the grand total (BR-DEC-14)', function (): void {
    expect(validate(totals: new Totals(
        lineTotal: '100.00',
        taxBasisTotal: '100.00',
        taxTotal: '19.00',
        grandTotal: '119.000',
        paidAmount: '0.00',
        payableAmount: '119.00',
    ))->hasViolation('BR-DEC-14'))->toBeTrue();
});

it('flags a taxable amount that does not match the line sum (BR-S-08)', function (): void {
    // The only S line nets 100.00 but the S breakdown claims 200.00 taxable.
    expect(validate(taxSubtotals: [new TaxSubtotal(
        category: 'S',
        rate: '19.00',
        taxableAmount: '200.00',
        taxAmount: '38.00',
    )])->hasViolation('BR-S-08'))->toBeTrue();
});

it('reconciles the taxable amount through a document allowance (no BR-S-08)', function (): void {
    // 100.00 line − 10.00 allowance = 90.00 taxable, VAT 17.10 → a clean invoice.
    $result = validate(
        allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00')],
        totals: new Totals(
            lineTotal: '100.00',
            allowanceTotal: '10.00',
            taxBasisTotal: '90.00',
            taxTotal: '17.10',
            grandTotal: '107.10',
            paidAmount: '0.00',
            payableAmount: '107.10',
        ),
        taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '90.00', taxAmount: '17.10')],
    );

    expect($result->hasViolation('BR-S-08'))->toBeFalse()
        ->and($result->isValid())->toBeTrue();
});

it('flags a document allowance total that does not match the allowances (BR-CO-11)', function (): void {
    expect(validate(
        allowanceCharges: [new DocumentAllowanceCharge(isCharge: false, amount: '10.00', taxCategory: 'S', taxRate: '19.00')],
        totals: new Totals(
            lineTotal: '100.00',
            allowanceTotal: '5.00',
            taxBasisTotal: '90.00',
            taxTotal: '17.10',
            grandTotal: '107.10',
            paidAmount: '0.00',
            payableAmount: '107.10',
        ),
    )->hasViolation('BR-CO-11'))->toBeTrue();
});
