<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\CodeList\CodeLists;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Rules\CalculationRule;
use JohnWink\En16931\Rules\CodeListRule;
use JohnWink\En16931\Rules\ConditionalRule;
use JohnWink\En16931\Rules\LineRule;
use JohnWink\En16931\Rules\PresenceRule;

/**
 * The assembled rule sets. {@see self::en16931()} is the EN 16931 core (a
 * curated, high-value subset — presence, the tolerance-free calculation rules,
 * VAT-category rules and code lists); {@see self::xrechnung()} adds the German
 * CIUS (BR-DE-*). Rule ids match the official rule sets so messages line up.
 *
 * This is a growing subset, not yet full KoSIT parity — see the README.
 */
final class RuleSets
{
    /**
     * @return list<Rule>
     */
    public static function en16931(): array
    {
        return [
            new PresenceRule('BR-02', 'BT-1', 'An Invoice shall have an Invoice number (BT-1).', static fn (Invoice $invoice): bool => self::filled($invoice->number)),
            new PresenceRule('BR-03', 'BT-2', 'An Invoice shall have an Invoice issue date (BT-2).', static fn (Invoice $invoice): bool => self::filled($invoice->issueDate)),
            new PresenceRule('BR-04', 'BT-3', 'An Invoice shall have an Invoice type code (BT-3).', static fn (Invoice $invoice): bool => self::filled($invoice->typeCode)),
            new PresenceRule('BR-05', 'BT-5', 'An Invoice shall have an Invoice currency code (BT-5).', static fn (Invoice $invoice): bool => self::filled($invoice->currency)),
            new PresenceRule('BR-06', 'BT-27', 'An Invoice shall contain the Seller name (BT-27).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->name)),
            new PresenceRule('BR-07', 'BT-44', 'An Invoice shall contain the Buyer name (BT-44).', static fn (Invoice $invoice): bool => self::filled($invoice->buyer->name)),
            new PresenceRule('BR-09', 'BT-40', 'The Seller postal address shall contain a country code (BT-40).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->countryCode)),
            new PresenceRule('BR-11', 'BT-55', 'The Buyer postal address shall contain a country code (BT-55).', static fn (Invoice $invoice): bool => self::filled($invoice->buyer->countryCode)),
            new PresenceRule('BR-16', 'BG-25', 'An Invoice shall have at least one Invoice line (BG-25).', static fn (Invoice $invoice): bool => $invoice->lines !== []),

            new CalculationRule('BR-CO-10', 'BT-106', 'Sum of Invoice line net amounts does not equal BT-106', static fn (Invoice $invoice): string => self::sumLineNet($invoice), static fn (Invoice $invoice): ?string => $invoice->totals->lineTotal),
            new CalculationRule('BR-CO-13', 'BT-109', 'Invoice total without VAT (BT-109) must equal BT-106 − BT-107 + BT-108', static fn (Invoice $invoice): string => Decimal::add(Decimal::sub(self::amount($invoice->totals->lineTotal), self::amount($invoice->totals->allowanceTotal)), self::amount($invoice->totals->chargeTotal)), static fn (Invoice $invoice): ?string => $invoice->totals->taxBasisTotal),
            new CalculationRule('BR-CO-14', 'BT-110', 'Total VAT (BT-110) must equal the sum of category tax amounts', static fn (Invoice $invoice): string => self::sumTax($invoice), static fn (Invoice $invoice): ?string => $invoice->totals->taxTotal),
            new CalculationRule('BR-CO-15', 'BT-112', 'Invoice total with VAT (BT-112) must equal BT-109 + BT-110', static fn (Invoice $invoice): string => Decimal::add(self::amount($invoice->totals->taxBasisTotal), self::amount($invoice->totals->taxTotal)), static fn (Invoice $invoice): ?string => $invoice->totals->grandTotal),
            new CalculationRule('BR-CO-16', 'BT-115', 'Amount due for payment (BT-115) must equal BT-112 − BT-113 + BT-114', static fn (Invoice $invoice): string => Decimal::add(Decimal::sub(self::amount($invoice->totals->grandTotal), self::amount($invoice->totals->paidAmount)), self::amount($invoice->totals->roundingAmount)), static fn (Invoice $invoice): ?string => $invoice->totals->payableAmount),

            new LineRule('BR-27', 'BT-146', 'The item net price (BT-146) shall not be negative.', static fn (InvoiceLine $invoiceLine): bool => ! self::isNegative($invoiceLine->netPrice)),
            new LineRule('BR-S-05', 'BT-152', 'A Standard-rated line (S) shall have a VAT rate (BT-152) greater than zero.', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory !== 'S' || self::isPositive($invoiceLine->taxRate)),
            new LineRule('BR-CL-17', 'BT-151', 'The line VAT category code (BT-151) shall be a valid UNCL5305 code.', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory === null || CodeLists::isVatCategory($invoiceLine->taxCategory)),

            new CodeListRule('BR-CL-01', 'BT-3', 'The Invoice type code (BT-3) shall be a valid UNTDID 1001 code', static fn (Invoice $invoice): ?string => $invoice->typeCode, CodeLists::INVOICE_TYPES),

            new ConditionalRule('BR-AE-02', 'BT-31', 'A reverse-charge invoice (AE) requires the Seller VAT identifier (BT-31).', static fn (Invoice $invoice): bool => $invoice->hasCategory('AE'), static fn (Invoice $invoice): bool => $invoice->seller->hasVatId()),
            new ConditionalRule('BR-AE-03', 'BT-48', 'A reverse-charge invoice (AE) requires the Buyer VAT identifier (BT-48).', static fn (Invoice $invoice): bool => $invoice->hasCategory('AE'), static fn (Invoice $invoice): bool => $invoice->buyer->hasVatId()),
        ];
    }

    /**
     * @return list<Rule>
     */
    public static function xrechnung(): array
    {
        return [
            new PresenceRule('BR-DE-1', 'BT-10', 'An XRechnung shall contain the Buyer reference / Leitweg-ID (BT-10).', static fn (Invoice $invoice): bool => self::filled($invoice->buyerReference)),
            new PresenceRule('BR-DE-5', 'BT-41', 'An XRechnung shall contain the Seller contact point (BT-41).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactName)),
            new PresenceRule('BR-DE-6', 'BT-42', 'An XRechnung shall contain the Seller contact telephone number (BT-42).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactPhone)),
            new PresenceRule('BR-DE-7', 'BT-43', 'An XRechnung shall contain the Seller contact email address (BT-43).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactEmail)),
        ];
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    /**
     * @return numeric-string
     */
    private static function amount(?string $value): string
    {
        return Decimal::isNumeric($value) ? $value : '0';
    }

    private static function isNegative(?string $value): bool
    {
        return Decimal::isNumeric($value) && Decimal::isNegative($value);
    }

    private static function isPositive(?string $value): bool
    {
        return Decimal::isNumeric($value) && Decimal::compare($value, '0') > 0;
    }

    private static function sumLineNet(Invoice $invoice): string
    {
        $sum = '0';
        foreach ($invoice->lines as $line) {
            $sum = Decimal::add($sum, self::amount($line->netAmount));
        }

        return $sum;
    }

    private static function sumTax(Invoice $invoice): string
    {
        $sum = '0';
        foreach ($invoice->taxSubtotals as $subtotal) {
            $sum = Decimal::add($sum, self::amount($subtotal->taxAmount));
        }

        return $sum;
    }
}
