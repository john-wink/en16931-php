<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Decimal;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Violation;

/**
 * BR-S-08 / BR-Z-08 / BR-E-08 / BR-AE-08 / BR-IC-08 / BR-G-08 / BR-O-08: for each
 * VAT breakdown group, the taxable amount (BT-116) must equal the sum of the
 * matching line net amounts (BT-131) minus document allowances (BT-92) plus
 * document charges (BT-99) of the same category and rate.
 */
final readonly class TaxableSumRule implements Rule
{
    /**
     * @var array<string, string>
     */
    private const array RULE_ID = [
        'S' => 'BR-S-08', 'Z' => 'BR-Z-08', 'E' => 'BR-E-08',
        'AE' => 'BR-AE-08', 'K' => 'BR-IC-08', 'G' => 'BR-G-08', 'O' => 'BR-O-08',
        'L' => 'BR-IG-08', 'M' => 'BR-IP-08',
    ];

    public function id(): string
    {
        return 'BR-CAT-08';
    }

    public function evaluate(Invoice $invoice): array
    {
        $violations = [];

        foreach ($invoice->taxSubtotals as $subtotal) {
            $taxableAmount = Decimal::fromXsDecimal($subtotal->taxableAmount);

            if ($taxableAmount === null) {
                continue;
            }

            $id = self::RULE_ID[(string) $subtotal->category] ?? null;
            if ($id === null) {
                continue;
            }

            $expected = $this->expectedTaxable($invoice, $subtotal);

            if (! Decimal::equals($expected, $taxableAmount)) {
                $violations[] = Violation::fatal($id, "The category taxable amount (BT-116) must equal its lines net − allowances + charges (expected {$expected}, declared {$taxableAmount}).", 'BT-116');
            }
        }

        return $violations;
    }

    /**
     * @return numeric-string
     */
    private function expectedTaxable(Invoice $invoice, TaxSubtotal $taxSubtotal): string
    {
        $sum = '0';

        foreach ($invoice->lines as $line) {
            $netAmount = Decimal::fromXsDecimal($line->netAmount);

            if ($netAmount !== null && $this->matches($line->taxCategory, $line->taxRate, $taxSubtotal)) {
                $sum = Decimal::add($sum, $netAmount);
            }
        }

        foreach ($invoice->allowanceCharges as $allowanceCharge) {
            if (! $this->matches($allowanceCharge->taxCategory, $allowanceCharge->taxRate, $taxSubtotal)) {
                continue;
            }
            $amount = Decimal::fromXsDecimal($allowanceCharge->amount);
            if ($amount === null) {
                continue;
            }
            $sum = $allowanceCharge->isCharge
                ? Decimal::add($sum, $amount)
                : Decimal::sub($sum, $amount);
        }

        return $sum;
    }

    private function matches(?string $category, ?string $rate, TaxSubtotal $taxSubtotal): bool
    {
        if ($category !== $taxSubtotal->category) {
            return false;
        }

        $lineRate = Decimal::fromXsDecimal($rate);
        $subtotalRate = Decimal::fromXsDecimal($taxSubtotal->rate);

        if ($lineRate !== null && $subtotalRate !== null) {
            return Decimal::equals($lineRate, $subtotalRate);
        }

        return true;
    }
}
