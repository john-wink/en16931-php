<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\CodeList\CodeLists;
use JohnWink\En16931\CodeList\CountryCodes;
use JohnWink\En16931\CodeList\CurrencyCodes;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Rules\CalculationRule;
use JohnWink\En16931\Rules\CodeListRule;
use JohnWink\En16931\Rules\ConditionalRule;
use JohnWink\En16931\Rules\InvoiceRule;
use JohnWink\En16931\Rules\LineRule;
use JohnWink\En16931\Rules\PresenceRule;
use JohnWink\En16931\Rules\SubtotalRule;
use JohnWink\En16931\Rules\TaxableSumRule;

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
            // BR-CO-16 is informational in the KoSIT/XRechnung configuration (a
            // mismatching amount due does not reject), so it is a warning here.
            new CalculationRule('BR-CO-16', 'BT-115', 'Amount due for payment (BT-115) must equal BT-112 − BT-113 + BT-114', static fn (Invoice $invoice): string => Decimal::add(Decimal::sub(self::amount($invoice->totals->grandTotal), self::amount($invoice->totals->paidAmount)), self::amount($invoice->totals->roundingAmount)), static fn (Invoice $invoice): ?string => $invoice->totals->payableAmount, Severity::Warning),

            new LineRule('BR-27', 'BT-146', 'The item net price (BT-146) shall not be negative.', static fn (InvoiceLine $invoiceLine): bool => ! self::isNegative($invoiceLine->netPrice)),
            new LineRule('BR-S-05', 'BT-152', 'A Standard-rated line (S) shall have a VAT rate (BT-152) greater than zero.', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory !== 'S' || self::isPositive($invoiceLine->taxRate)),
            new LineRule('BR-CL-17', 'BT-151', 'The line VAT category code (BT-151) shall be a valid UNCL5305 code.', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory === null || CodeLists::isVatCategory($invoiceLine->taxCategory)),

            new CodeListRule('BR-CL-01', 'BT-3', 'The Invoice type code (BT-3) shall be a valid UNTDID 1001 code', static fn (Invoice $invoice): ?string => $invoice->typeCode, CodeLists::INVOICE_TYPES),

            new ConditionalRule('BR-AE-02', 'BT-31', 'A reverse-charge invoice (AE) requires the Seller VAT identifier (BT-31).', static fn (Invoice $invoice): bool => $invoice->hasCategory('AE'), static fn (Invoice $invoice): bool => $invoice->seller->hasVatId()),
            new ConditionalRule('BR-AE-03', 'BT-48', 'A reverse-charge invoice (AE) requires the Buyer VAT identifier (BT-48).', static fn (Invoice $invoice): bool => $invoice->hasCategory('AE'), static fn (Invoice $invoice): bool => $invoice->buyer->hasVatId()),

            new PresenceRule('BR-01', 'BT-24', 'An Invoice shall have a Specification identifier (BT-24).', static fn (Invoice $invoice): bool => self::filled($invoice->customizationId)),
            new PresenceRule('BR-CO-18', 'BG-23', 'An Invoice shall have at least one VAT breakdown group (BG-23).', static fn (Invoice $invoice): bool => $invoice->taxSubtotals !== []),

            new LineRule('BR-21', 'BT-126', 'Each Invoice line shall have a line identifier (BT-126).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->id)),
            new LineRule('BR-22', 'BT-129', 'Each Invoice line shall have an invoiced quantity (BT-129).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->quantity)),
            new LineRule('BR-23', 'BT-130', 'Each Invoice line shall have a unit of measure code (BT-130).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->unitCode)),
            new LineRule('BR-24', 'BT-131', 'Each Invoice line shall have a net amount (BT-131).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->netAmount)),
            new LineRule('BR-25', 'BT-153', 'Each Invoice line shall have an item name (BT-153).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->name)),
            new LineRule('BR-26', 'BT-146', 'Each Invoice line shall have an item net price (BT-146).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->netPrice)),
            new LineRule('BR-CO-04', 'BT-151', 'Each Invoice line shall have a VAT category code (BT-151).', static fn (InvoiceLine $invoiceLine): bool => self::filled($invoiceLine->taxCategory)),

            new SubtotalRule('BR-45', 'BT-116', 'Each VAT breakdown group shall have a taxable amount (BT-116).', static fn (TaxSubtotal $taxSubtotal): bool => self::filled($taxSubtotal->taxableAmount)),
            new SubtotalRule('BR-46', 'BT-117', 'Each VAT breakdown group shall have a tax amount (BT-117).', static fn (TaxSubtotal $taxSubtotal): bool => self::filled($taxSubtotal->taxAmount)),
            new SubtotalRule('BR-47', 'BT-118', 'Each VAT breakdown group shall have a VAT category code (BT-118).', static fn (TaxSubtotal $taxSubtotal): bool => self::filled($taxSubtotal->category)),
            new SubtotalRule('BR-CO-17', 'BT-117', 'The category tax amount (BT-117) must equal the taxable amount (BT-116) × rate (BT-119).', static fn (TaxSubtotal $taxSubtotal): bool => self::categoryTaxConsistent($taxSubtotal)),
            new SubtotalRule('BR-E-10', 'BT-120', 'A VAT-exempt (E) breakdown group shall have a VAT exemption reason (BT-120/BT-121).', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== 'E' || self::hasExemptionReason($taxSubtotal)),
            new SubtotalRule('BR-AE-10', 'BT-120', 'A reverse-charge (AE) breakdown group shall have a VAT exemption reason (BT-120/BT-121).', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== 'AE' || self::hasExemptionReason($taxSubtotal)),

            new CodeListRule('BR-CL-03', 'BT-5', 'The Invoice currency code (BT-5) shall be a valid ISO 4217 code', static fn (Invoice $invoice): ?string => $invoice->currency, CurrencyCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-40', 'The Seller country code (BT-40) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->seller->countryCode, CountryCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-55', 'The Buyer country code (BT-55) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->buyer->countryCode, CountryCodes::CODES),

            new CalculationRule('BR-CO-11', 'BT-107', 'Sum of allowances on document level (BT-107) must equal the sum of the document allowance amounts (BT-92)', static fn (Invoice $invoice): string => self::sumAllowanceCharges($invoice, false), static fn (Invoice $invoice): ?string => $invoice->totals->allowanceTotal),
            new CalculationRule('BR-CO-12', 'BT-108', 'Sum of charges on document level (BT-108) must equal the sum of the document charge amounts (BT-99)', static fn (Invoice $invoice): string => self::sumAllowanceCharges($invoice, true), static fn (Invoice $invoice): ?string => $invoice->totals->chargeTotal),

            new TaxableSumRule,

            ...self::decimalRules(),
            ...self::categoryRules(),
        ];
    }

    /**
     * BR-DEC-*: monetary amounts shall not carry more than two decimal places.
     *
     * @return list<Rule>
     */
    private static function decimalRules(): array
    {
        return [
            new InvoiceRule('BR-DEC-09', 'BT-106', 'The sum of line net amounts (BT-106) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->lineTotal)),
            new InvoiceRule('BR-DEC-10', 'BT-107', 'The document allowance total (BT-107) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->allowanceTotal)),
            new InvoiceRule('BR-DEC-11', 'BT-108', 'The document charge total (BT-108) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->chargeTotal)),
            new InvoiceRule('BR-DEC-12', 'BT-109', 'The invoice total without VAT (BT-109) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->taxBasisTotal)),
            new InvoiceRule('BR-DEC-13', 'BT-110', 'The invoice total VAT (BT-110) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->taxTotal)),
            new InvoiceRule('BR-DEC-14', 'BT-112', 'The invoice total with VAT (BT-112) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->grandTotal)),
            new InvoiceRule('BR-DEC-16', 'BT-113', 'The paid amount (BT-113) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->paidAmount)),
            new InvoiceRule('BR-DEC-17', 'BT-114', 'The rounding amount (BT-114) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->roundingAmount)),
            new InvoiceRule('BR-DEC-18', 'BT-115', 'The amount due for payment (BT-115) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->payableAmount)),
            new LineRule('BR-DEC-23', 'BT-131', 'The line net amount (BT-131) shall not have more than two decimals.', static fn (InvoiceLine $invoiceLine): bool => self::maxTwoDecimals($invoiceLine->netAmount)),
            new SubtotalRule('BR-DEC-19', 'BT-116', 'The VAT category taxable amount (BT-116) shall not have more than two decimals.', static fn (TaxSubtotal $taxSubtotal): bool => self::maxTwoDecimals($taxSubtotal->taxableAmount)),
            new SubtotalRule('BR-DEC-20', 'BT-117', 'The VAT category tax amount (BT-117) shall not have more than two decimals.', static fn (TaxSubtotal $taxSubtotal): bool => self::maxTwoDecimals($taxSubtotal->taxAmount)),
        ];
    }

    /**
     * Per-VAT-category consistency rules (BR-S/Z/E/AE/IC/G/O). Kept free of
     * false positives: they do not assume document-level allowances/charges
     * (not modelled here), so the taxable-sum rules (BR-*-08) are deferred and
     * only the category/tax/reason invariants are enforced.
     *
     * @return list<Rule>
     */
    private static function categoryRules(): array
    {
        $categoryPrefix = ['S' => 'BR-S', 'Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O'];
        $rules = [];

        // BR-*-01: a used line VAT category must have a matching breakdown group.
        foreach ($categoryPrefix as $category => $id) {
            $rules[] = new ConditionalRule("{$id}-01", 'BG-23', "A line with VAT category {$category} requires a matching VAT breakdown group.", static fn (Invoice $invoice): bool => self::lineHasCategory($invoice, $category), static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, $category));
        }

        // BR-*-09: categories that carry no VAT must have a category tax amount of 0.
        foreach (['Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-09", 'BT-117', "A {$category} VAT breakdown group shall have a category tax amount (BT-117) of 0.", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || self::isZeroOrMissing($taxSubtotal->taxAmount));
        }

        // BR-{IC,G,O}-10: exemption reason required (E and AE covered in the core set).
        foreach (['K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-10", 'BT-120', "A {$category} VAT breakdown group shall have a VAT exemption reason (BT-120/BT-121).", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || self::hasExemptionReason($taxSubtotal));
        }

        // BR-S-10 / BR-Z-10: Standard/Zero groups must NOT carry an exemption reason.
        $rules[] = new SubtotalRule('BR-S-10', 'BT-120', 'A Standard-rated (S) VAT breakdown group shall not have a VAT exemption reason.', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== 'S' || ! self::hasExemptionReason($taxSubtotal));
        $rules[] = new SubtotalRule('BR-Z-10', 'BT-120', 'A Zero-rated (Z) VAT breakdown group shall not have a VAT exemption reason.', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== 'Z' || ! self::hasExemptionReason($taxSubtotal));

        return $rules;
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

    private static function isZeroOrMissing(?string $value): bool
    {
        if (! Decimal::isNumeric($value)) {
            return true;
        }

        return Decimal::compare($value, '0') === 0;
    }

    private static function maxTwoDecimals(?string $value): bool
    {
        if (! Decimal::isNumeric($value)) {
            return true;
        }

        return preg_match('/^-?\d+(\.\d{1,2})?$/', trim($value)) === 1;
    }

    private static function sumAllowanceCharges(Invoice $invoice, bool $charges): string
    {
        $sum = '0';

        foreach ($invoice->allowanceCharges as $allowanceCharge) {
            if ($allowanceCharge->isCharge === $charges && Decimal::isNumeric($allowanceCharge->amount)) {
                $sum = Decimal::add($sum, $allowanceCharge->amount);
            }
        }

        return $sum;
    }

    private static function lineHasCategory(Invoice $invoice, string $category): bool
    {
        return array_any($invoice->lines, fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory === $category);
    }

    private static function subtotalHasCategory(Invoice $invoice, string $category): bool
    {
        return array_any($invoice->taxSubtotals, fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category === $category);
    }

    private static function hasExemptionReason(TaxSubtotal $taxSubtotal): bool
    {
        if (self::filled($taxSubtotal->exemptionReason)) {
            return true;
        }

        return self::filled($taxSubtotal->exemptionReasonCode);
    }

    /**
     * BR-CO-17: the category tax amount must equal the taxable amount × the rate
     * (rounded to two decimals). Skipped when a value is missing — the presence
     * rules own that.
     */
    private static function categoryTaxConsistent(TaxSubtotal $taxSubtotal): bool
    {
        if (! Decimal::isNumeric($taxSubtotal->taxableAmount) || ! Decimal::isNumeric($taxSubtotal->rate) || ! Decimal::isNumeric($taxSubtotal->taxAmount)) {
            return true;
        }

        $expected = Decimal::round(Decimal::mul($taxSubtotal->taxableAmount, Decimal::mul($taxSubtotal->rate, '0.01')));

        return Decimal::equals($expected, $taxSubtotal->taxAmount);
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
