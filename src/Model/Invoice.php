<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * The normalized, syntax-agnostic invoice the rules run against. A reader
 * (e.g. {@see \JohnWink\En16931\Reader\CiiInvoiceReader}) builds it from the XML;
 * every amount is the raw decimal string as declared, so the calculation rules
 * stay exact.
 */
final readonly class Invoice
{
    /**
     * @param  list<InvoiceLine>  $lines
     * @param  list<TaxSubtotal>  $taxSubtotals
     * @param  list<string>  $notes
     * @param  list<DocumentAllowanceCharge>  $allowanceCharges
     */
    public function __construct(
        public ?string $number,           // BT-1
        public ?string $typeCode,         // BT-3
        public ?string $issueDate,        // BT-2 (normalized to Y-m-d when possible)
        public ?string $currency,         // BT-5
        public ?string $taxCurrency,      // BT-6
        public ?string $buyerReference,   // BT-10
        public ?string $customizationId,  // BT-24
        public Party $seller,
        public Party $buyer,
        public Totals $totals,
        public array $lines,
        public array $taxSubtotals,
        public array $notes = [],
        public array $allowanceCharges = [],
        public ?string $paymentDueDate = null,  // BT-9
        public ?string $paymentTerms = null,    // BT-20
        public ?Party $taxRepresentative = null, // BG-11 (BT-62 name / BT-63 vatId / BT-69 countryCode)
    ) {}

    public function hasCategory(string $category): bool
    {
        foreach ($this->taxSubtotals as $taxSubtotal) {
            if ($taxSubtotal->category === $category) {
                return true;
            }
        }

        return array_any($this->lines, fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory === $category);
    }
}
