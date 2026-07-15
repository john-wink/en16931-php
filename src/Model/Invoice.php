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
        /** @var list<PaymentMeans> */
        public array $paymentMeans = [],        // BG-16
        public ?string $sepaCreditorId = null,  // BT-90 bank assigned creditor identifier
        public bool $hasInvoicingPeriod = false, // BG-14 group present
        public ?string $invoicingPeriodStart = null, // BT-73 (normalized to Y-m-d)
        public ?string $invoicingPeriodEnd = null,   // BT-74 (normalized to Y-m-d)
        public ?string $taxPointDateCode = null,     // BT-8 (UBL: InvoicePeriod/DescriptionCode)
        public ?string $actualDeliveryDate = null,   // BT-72
        public ?Party $deliverTo = null,             // BG-15 (BT-70 name / BT-75..80 address)
        public ?Party $payee = null,                 // BG-10 (BT-59 name / BT-60 id / BT-61 legal reg)
        public ?string $taxPointDate = null,         // BT-7
        /** @var list<Attachment> */
        public array $attachments = [],              // BG-24 (and other document references)
        /** @var list<string|null> */
        public array $precedingInvoiceReferences = [], // BG-3 (BT-25 per entry)
        /** @var list<string> */
        public array $amountCurrencyCodes = [],      // every @currencyID used on amounts (BR-CL-03)
        public ?string $contractReference = null,    // BT-12
        public ?string $tenderReference = null,      // BT-17
        /** @var list<ThirdPartyPayment> */
        public array $thirdPartyPayments = [],       // BG-DEX-09 (XRechnung extension)
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
