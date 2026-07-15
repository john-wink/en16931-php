<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\CodeList\CodeLists;
use JohnWink\En16931\CodeList\CountryCodes;
use JohnWink\En16931\CodeList\CurrencyCodes;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\ItemAttribute;
use JohnWink\En16931\Model\ItemClassification;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Rules\AllowanceRule;
use JohnWink\En16931\Rules\CalculationRule;
use JohnWink\En16931\Rules\CodeListRule;
use JohnWink\En16931\Rules\ConditionalRule;
use JohnWink\En16931\Rules\InvoiceRule;
use JohnWink\En16931\Rules\LineAllowanceRule;
use JohnWink\En16931\Rules\LineRule;
use JohnWink\En16931\Rules\PaymentMeansRule;
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
     * BR-DE-21: the base specification identifier (BT-24) of XRechnung 3.0.
     */
    private const string XRECHNUNG_CIUS_ID = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

    /**
     * BR-DE-21: every specification identifier XRechnung 3.0 accepts — the
     * CIUS itself, the extension and the CVD profile.
     *
     * @var list<string>
     */
    private const array XRECHNUNG_SPECIFICATION_IDS = [
        self::XRECHNUNG_CIUS_ID,
        self::XRECHNUNG_CIUS_ID.'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0',
        self::XRECHNUNG_CIUS_ID.'#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9',
    ];

    /**
     * BR-DE-17: the UNTDID 1001 codes XRechnung recommends for BT-3.
     *
     * @var list<string>
     */
    private const array XRECHNUNG_TYPE_CODES = ['326', '380', '381', '384', '389', '875', '876', '877'];

    /**
     * BR-DE-16: using any of these VAT categories requires seller tax identification.
     *
     * @var list<string>
     */
    private const array XRECHNUNG_IDENTIFIED_CATEGORIES = ['S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M'];

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
            new PresenceRule('BR-08', 'BG-5', 'An Invoice shall contain the Seller postal address (BG-5).', static fn (Invoice $invoice): bool => $invoice->seller->hasPostalAddress()),
            new PresenceRule('BR-09', 'BT-40', 'The Seller postal address shall contain a country code (BT-40).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->countryCode)),
            new PresenceRule('BR-10', 'BG-8', 'An Invoice shall contain the Buyer postal address (BG-8).', static fn (Invoice $invoice): bool => $invoice->buyer->hasPostalAddress()),
            new PresenceRule('BR-11', 'BT-55', 'The Buyer postal address shall contain a country code (BT-55).', static fn (Invoice $invoice): bool => self::filled($invoice->buyer->countryCode)),
            new ConditionalRule('BR-62', 'BT-34', 'The Seller electronic address (BT-34) shall have a scheme identifier.', static fn (Invoice $invoice): bool => $invoice->seller->electronicAddress !== null, static fn (Invoice $invoice): bool => $invoice->seller->electronicAddressScheme !== null),
            new ConditionalRule('BR-63', 'BT-49', 'The Buyer electronic address (BT-49) shall have a scheme identifier.', static fn (Invoice $invoice): bool => $invoice->buyer->electronicAddress !== null, static fn (Invoice $invoice): bool => $invoice->buyer->electronicAddressScheme !== null),

            new PaymentMeansRule('BR-49', 'BT-81', 'Each payment instruction (BG-16) shall specify the payment means type code (BT-81).', static fn (PaymentMeans $paymentMeans): bool => self::filled($paymentMeans->typeCode)),
            new PaymentMeansRule('BR-50', 'BT-84', 'A payment account identifier (BT-84) shall be present when credit transfer information is given.', static fn (PaymentMeans $paymentMeans): bool => ! $paymentMeans->hasCreditTransfer || ! in_array(mb_trim((string) $paymentMeans->typeCode), ['30', '58'], true) || self::filled($paymentMeans->accountId)),
            new PaymentMeansRule('BR-51', 'BT-87', 'An invoice should never include a full card primary account number (BT-87) — at most 10 characters.', static fn (PaymentMeans $paymentMeans): bool => $paymentMeans->cardNumber === null || mb_strlen(mb_trim($paymentMeans->cardNumber)) <= 10, Severity::Warning),
            new PaymentMeansRule('BR-61', 'BT-84', 'A credit transfer (payment means code 30 or 58) shall carry a payment account identifier (BT-84).', static fn (PaymentMeans $paymentMeans): bool => ! in_array(mb_trim((string) $paymentMeans->typeCode), ['30', '58'], true) || $paymentMeans->accountId !== null),
            new PaymentMeansRule('BR-CL-16', 'BT-81', 'The payment means type code (BT-81) shall be a valid UNTDID 4461 code.', static fn (PaymentMeans $paymentMeans): bool => $paymentMeans->typeCode === null || in_array($paymentMeans->typeCode, CodeLists::PAYMENT_MEANS_CODES, true)),

            new InvoiceRule('BR-29', 'BT-73', 'When both invoicing period dates are given, the end date (BT-74) shall not precede the start date (BT-73).', static fn (Invoice $invoice): bool => self::periodOrdered($invoice->invoicingPeriodStart, $invoice->invoicingPeriodEnd)),
            new LineRule('BR-30', 'BT-134', 'When both line period dates are given, the end date (BT-135) shall not precede the start date (BT-134).', static fn (InvoiceLine $invoiceLine): bool => self::periodOrdered($invoiceLine->periodStart, $invoiceLine->periodEnd)),
            new ConditionalRule('BR-CO-19', 'BG-14', 'A used invoicing period (BG-14) shall have a start date (BT-73), an end date (BT-74) or a description code (BT-8).', static fn (Invoice $invoice): bool => $invoice->hasInvoicingPeriod, static fn (Invoice $invoice): bool => self::filled($invoice->invoicingPeriodStart) || self::filled($invoice->invoicingPeriodEnd) || self::filled($invoice->taxPointDateCode)),
            new LineRule('BR-CO-20', 'BG-26', 'A used line period (BG-26) shall have a start date (BT-134) or an end date (BT-135).', static fn (InvoiceLine $invoiceLine): bool => ! $invoiceLine->hasPeriod || self::filled($invoiceLine->periodStart) || self::filled($invoiceLine->periodEnd)),
            new ConditionalRule('BR-57', 'BT-80', 'Each deliver-to address (BG-15) shall contain a country code (BT-80).', static fn (Invoice $invoice): bool => $invoice->deliverTo instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->deliverTo?->countryCode)),
            new ConditionalRule('BR-IC-11', 'BT-72', 'An intra-community invoice (K) shall contain the actual delivery date (BT-72) or the invoicing period (BG-14).', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'K'), static fn (Invoice $invoice): bool => self::filled($invoice->actualDeliveryDate) || self::filled($invoice->invoicingPeriodStart) || self::filled($invoice->invoicingPeriodEnd) || self::filled($invoice->taxPointDateCode)),
            new ConditionalRule('BR-IC-12', 'BT-80', 'An intra-community invoice (K) shall contain the deliver-to country code (BT-80).', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'K'), static fn (Invoice $invoice): bool => self::filled($invoice->deliverTo?->countryCode)),

            new ConditionalRule('BR-17', 'BT-59', 'The Payee name (BT-59) shall be provided and differ from the Seller (BG-4).', static fn (Invoice $invoice): bool => $invoice->payee instanceof Party, static fn (Invoice $invoice): bool => self::payeeDiffersFromSeller($invoice)),
            new ConditionalRule('BR-19', 'BG-12', 'The Seller tax representative postal address (BG-12) shall be provided when a tax representative party (BG-11) is present.', static fn (Invoice $invoice): bool => $invoice->taxRepresentative instanceof Party, static fn (Invoice $invoice): bool => $invoice->taxRepresentative?->hasPostalAddress() ?? false),
            new LineRule('BR-28', 'BT-148', 'The item gross price (BT-148) shall not be negative.', static fn (InvoiceLine $invoiceLine): bool => ! self::isNegative($invoiceLine->grossPrice)),
            new InvoiceRule('BR-52', 'BT-122', 'Each additional supporting document (BG-24) shall contain a supporting document reference (BT-122).', static fn (Invoice $invoice): bool => array_all($invoice->attachments, fn (Attachment $attachment): bool => self::filled($attachment->reference))),
            new ConditionalRule('BR-53', 'BT-111', 'When the VAT accounting currency code (BT-6) is present, the invoice total VAT amount in accounting currency (BT-111) shall be provided.', static fn (Invoice $invoice): bool => self::filled($invoice->taxCurrency), static fn (Invoice $invoice): bool => self::filled($invoice->totals->taxTotalAccounting)),
            new LineRule('BR-54', 'BG-32', 'Each item attribute (BG-32) shall contain a name (BT-160) and a value (BT-161).', static fn (InvoiceLine $invoiceLine): bool => array_all($invoiceLine->attributes, fn (ItemAttribute $itemAttribute): bool => self::filled($itemAttribute->name) && self::filled($itemAttribute->value))),
            new InvoiceRule('BR-55', 'BT-25', 'Each preceding invoice reference (BG-3) shall contain the preceding invoice number (BT-25).', static fn (Invoice $invoice): bool => array_all($invoice->precedingInvoiceReferences, fn (?string $reference): bool => self::filled($reference))),
            new LineRule('BR-64', 'BT-157', 'The item standard identifier (BT-157) shall have a scheme identifier.', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->itemStandardId === null || $invoiceLine->itemStandardIdScheme !== null),
            new LineRule('BR-65', 'BT-158', 'Each item classification identifier (BT-158) shall have a scheme identifier.', static fn (InvoiceLine $invoiceLine): bool => array_all($invoiceLine->itemClassifications, fn (ItemClassification $itemClassification): bool => $itemClassification->code === null || $itemClassification->scheme !== null)),
            new InvoiceRule('BR-CO-03', 'BT-7', 'The VAT point date (BT-7) and the VAT point date code (BT-8) are mutually exclusive.', static fn (Invoice $invoice): bool => ! self::filled($invoice->taxPointDate) || ! self::filled($invoice->taxPointDateCode)),
            new InvoiceRule('BR-CL-24', 'BT-125', 'The attachment MIME code shall be from the MIMEMediaType list.', static fn (Invoice $invoice): bool => array_all($invoice->attachments, fn (Attachment $attachment): bool => $attachment->mimeCode === null || in_array($attachment->mimeCode, CodeLists::MIME_CODES, true))),

            // BR-CO-21..24 restate the reason requirements of BR-33/38/42/44
            // under their own ids — the official artefacts carry both.
            new AllowanceRule('BR-CO-21', 'BT-97', 'Each document level allowance shall contain a reason (BT-97) or a reason code (BT-98).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->reason) || self::filled($documentAllowanceCharge->reasonCode), charges: false),
            new AllowanceRule('BR-CO-22', 'BT-104', 'Each document level charge shall contain a reason (BT-104) or a reason code (BT-105).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->reason) || self::filled($documentAllowanceCharge->reasonCode), charges: true),
            new LineAllowanceRule('BR-CO-23', 'BT-139', 'Each line allowance shall contain a reason (BT-139) or a reason code (BT-140).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->reason) || self::filled($lineAllowanceCharge->reasonCode)),
            new LineAllowanceRule('BR-CO-24', 'BT-144', 'Each line charge shall contain a reason (BT-144) or a reason code (BT-145).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => ! $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->reason) || self::filled($lineAllowanceCharge->reasonCode)),
            new PresenceRule('BR-16', 'BG-25', 'An Invoice shall have at least one Invoice line (BG-25).', static fn (Invoice $invoice): bool => $invoice->lines !== []),
            new PresenceRule('BR-12', 'BT-106', 'An Invoice shall have the Sum of Invoice line net amount (BT-106).', static fn (Invoice $invoice): bool => self::filled($invoice->totals->lineTotal)),
            new PresenceRule('BR-13', 'BT-109', 'An Invoice shall have the Invoice total amount without VAT (BT-109).', static fn (Invoice $invoice): bool => self::filled($invoice->totals->taxBasisTotal)),
            new PresenceRule('BR-14', 'BT-112', 'An Invoice shall have the Invoice total amount with VAT (BT-112).', static fn (Invoice $invoice): bool => self::filled($invoice->totals->grandTotal)),
            new PresenceRule('BR-15', 'BT-115', 'An Invoice shall have the Amount due for payment (BT-115).', static fn (Invoice $invoice): bool => self::filled($invoice->totals->payableAmount)),
            new InvoiceRule('BR-CO-26', 'BT-29', 'The Seller must be identifiable: a Seller identifier (BT-29), legal registration (BT-30) or VAT identifier (BT-31) is required.', static fn (Invoice $invoice): bool => $invoice->seller->hasAnyIdentifier()),
            new InvoiceRule('BR-CO-25', 'BT-115', 'When the Amount due for payment (BT-115) is positive, a Payment due date (BT-9) or Payment terms (BT-20) shall be present.', static fn (Invoice $invoice): bool => ! self::isPositive($invoice->totals->payableAmount) || self::filled($invoice->paymentDueDate) || self::filled($invoice->paymentTerms)),

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

            new InvoiceRule('BR-CO-09', 'BT-31', 'VAT identifiers (BT-31, BT-48, BT-63) shall be prefixed with a country code (ISO 3166-1 alpha-2, EL or XI).', static fn (Invoice $invoice): bool => self::vatIdentifiersHaveCountryPrefix($invoice)),

            new ConditionalRule('BR-18', 'BT-62', 'The Seller tax representative name (BT-62) shall be provided when a tax representative party (BG-11) is present.', static fn (Invoice $invoice): bool => $invoice->taxRepresentative instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->taxRepresentative?->name)),
            new ConditionalRule('BR-20', 'BT-69', 'The Seller tax representative postal address shall contain a country code (BT-69) when a tax representative party (BG-11) is present.', static fn (Invoice $invoice): bool => $invoice->taxRepresentative instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->taxRepresentative?->countryCode)),
            new ConditionalRule('BR-56', 'BT-63', 'Each Seller tax representative party (BG-11) shall have a VAT identifier (BT-63).', static fn (Invoice $invoice): bool => $invoice->taxRepresentative instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->taxRepresentative?->vatId)),

            ...self::vatIdentificationRules(),
            ...self::documentAllowanceChargeRules(),

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
            new SubtotalRule('BR-48', 'BT-119', 'Each VAT breakdown group shall have a VAT category rate (BT-119), except for category O.', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category === 'O' || self::filled($taxSubtotal->rate)),

            new CodeListRule('BR-CL-03', 'BT-5', 'The Invoice currency code (BT-5) shall be a valid ISO 4217 code', static fn (Invoice $invoice): ?string => $invoice->currency, CurrencyCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-40', 'The Seller country code (BT-40) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->seller->countryCode, CountryCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-55', 'The Buyer country code (BT-55) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->buyer->countryCode, CountryCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-69', 'The Tax representative country code (BT-69) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->taxRepresentative?->countryCode, CountryCodes::CODES),
            new CodeListRule('BR-CL-14', 'BT-80', 'The Deliver-to country code (BT-80) shall be a valid ISO 3166-1 code', static fn (Invoice $invoice): ?string => $invoice->deliverTo?->countryCode, CountryCodes::CODES),
            new CodeListRule('BR-CL-25', 'BT-34', 'The Seller electronic address scheme (BT-34) shall be from the EAS code list', static fn (Invoice $invoice): ?string => $invoice->seller->electronicAddressScheme, CodeLists::ELECTRONIC_ADDRESS_SCHEMES),
            new CodeListRule('BR-CL-25', 'BT-49', 'The Buyer electronic address scheme (BT-49) shall be from the EAS code list', static fn (Invoice $invoice): ?string => $invoice->buyer->electronicAddressScheme, CodeLists::ELECTRONIC_ADDRESS_SCHEMES),

            // BR-CL-17 covers every VAT category code field: BT-151 is checked
            // as a line rule above; BT-118 / BT-95 / BT-102 here.
            new SubtotalRule('BR-CL-17', 'BT-118', 'The VAT category code (BT-118) shall be a valid UNCL5305 code.', static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category === null || CodeLists::isVatCategory($taxSubtotal->category)),
            new AllowanceRule('BR-CL-17', 'BT-95', 'The document-level allowance VAT category code (BT-95) shall be a valid UNCL5305 code.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory === null || CodeLists::isVatCategory($documentAllowanceCharge->taxCategory), charges: false),
            new AllowanceRule('BR-CL-17', 'BT-102', 'The document-level charge VAT category code (BT-102) shall be a valid UNCL5305 code.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory === null || CodeLists::isVatCategory($documentAllowanceCharge->taxCategory), charges: true),

            new CalculationRule('BR-CO-11', 'BT-107', 'Sum of allowances on document level (BT-107) must equal the sum of the document allowance amounts (BT-92)', static fn (Invoice $invoice): string => self::sumAllowanceCharges($invoice, false), static fn (Invoice $invoice): ?string => $invoice->totals->allowanceTotal),
            new CalculationRule('BR-CO-12', 'BT-108', 'Sum of charges on document level (BT-108) must equal the sum of the document charge amounts (BT-99)', static fn (Invoice $invoice): string => self::sumAllowanceCharges($invoice, true), static fn (Invoice $invoice): ?string => $invoice->totals->chargeTotal),

            new TaxableSumRule,

            new LineAllowanceRule('BR-41', 'BT-136', 'Each line allowance shall have an amount (BT-136).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->amount)),
            new LineAllowanceRule('BR-42', 'BT-139', 'Each line allowance shall have a reason (BT-139) or a reason code (BT-140).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->reason) || self::filled($lineAllowanceCharge->reasonCode)),
            new LineAllowanceRule('BR-43', 'BT-141', 'Each line charge shall have an amount (BT-141).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => ! $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->amount)),
            new LineAllowanceRule('BR-44', 'BT-144', 'Each line charge shall have a reason (BT-144) or a reason code (BT-145).', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => ! $lineAllowanceCharge->isCharge || self::filled($lineAllowanceCharge->reason) || self::filled($lineAllowanceCharge->reasonCode)),

            ...self::decimalRules(),
            ...self::categoryRules(),
        ];
    }

    /**
     * @return list<Rule>
     */
    public static function xrechnung(): array
    {
        return [
            new PresenceRule('BR-DE-1', 'BG-16', 'An XRechnung shall contain payment instructions (BG-16).', static fn (Invoice $invoice): bool => $invoice->paymentMeans !== []),
            new PresenceRule('BR-DE-15', 'BT-10', 'An XRechnung shall contain the Buyer reference / Leitweg-ID (BT-10).', static fn (Invoice $invoice): bool => self::filled($invoice->buyerReference)),
            new PresenceRule('BR-DE-2', 'BG-6', 'An XRechnung shall contain the Seller contact group (BG-6).', static fn (Invoice $invoice): bool => self::hasSellerContact($invoice)),
            new ConditionalRule('BR-DE-3', 'BT-37', 'The Seller postal address shall contain a city (BT-37).', static fn (Invoice $invoice): bool => $invoice->seller->hasPostalAddress(), static fn (Invoice $invoice): bool => self::filled($invoice->seller->city)),
            new ConditionalRule('BR-DE-4', 'BT-38', 'The Seller postal address shall contain a post code (BT-38).', static fn (Invoice $invoice): bool => $invoice->seller->hasPostalAddress(), static fn (Invoice $invoice): bool => self::filled($invoice->seller->postCode)),
            new ConditionalRule('BR-DE-8', 'BT-52', 'The Buyer postal address shall contain a city (BT-52).', static fn (Invoice $invoice): bool => $invoice->buyer->hasPostalAddress(), static fn (Invoice $invoice): bool => self::filled($invoice->buyer->city)),
            new ConditionalRule('BR-DE-9', 'BT-53', 'The Buyer postal address shall contain a post code (BT-53).', static fn (Invoice $invoice): bool => $invoice->buyer->hasPostalAddress(), static fn (Invoice $invoice): bool => self::filled($invoice->buyer->postCode)),
            new ConditionalRule('BR-DE-10', 'BT-77', 'The deliver-to address shall contain a city (BT-77).', static fn (Invoice $invoice): bool => $invoice->deliverTo instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->deliverTo?->city)),
            new ConditionalRule('BR-DE-11', 'BT-78', 'The deliver-to address shall contain a post code (BT-78).', static fn (Invoice $invoice): bool => $invoice->deliverTo instanceof Party, static fn (Invoice $invoice): bool => self::filled($invoice->deliverTo?->postCode)),
            new InvoiceRule('BR-DE-TMP-32', 'BT-72', 'An invoice should contain the actual delivery date (BT-72), an invoicing period (BG-14) or a period on every line (BG-26).', static fn (Invoice $invoice): bool => self::filled($invoice->actualDeliveryDate) || $invoice->hasInvoicingPeriod || array_all($invoice->lines, fn (InvoiceLine $invoiceLine): bool => $invoiceLine->hasPeriod), Severity::Warning),
            new PresenceRule('BR-DE-5', 'BT-41', 'An XRechnung shall contain the Seller contact point (BT-41).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactName)),
            new PresenceRule('BR-DE-6', 'BT-42', 'An XRechnung shall contain the Seller contact telephone number (BT-42).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactPhone)),
            new PresenceRule('BR-DE-7', 'BT-43', 'An XRechnung shall contain the Seller contact email address (BT-43).', static fn (Invoice $invoice): bool => self::filled($invoice->seller->contactEmail)),
            new ConditionalRule('BR-DE-16', 'BT-31', 'When VAT categories S, Z, E, AE, K, G, L or M are used, the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or a tax representative party (BG-11) shall be present.', static fn (Invoice $invoice): bool => self::usesAnyVatCategoryOf($invoice, self::XRECHNUNG_IDENTIFIED_CATEGORIES), static fn (Invoice $invoice): bool => $invoice->seller->hasVatId() || self::filled($invoice->seller->taxRegistrationId) || $invoice->taxRepresentative instanceof Party),
            new InvoiceRule('BR-DE-17', 'BT-3', 'An XRechnung should only use the invoice type codes 326, 380, 381, 384, 389, 875, 876 or 877 (BT-3).', static fn (Invoice $invoice): bool => $invoice->typeCode === null || in_array($invoice->typeCode, self::XRECHNUNG_TYPE_CODES, true), Severity::Warning),
            new InvoiceRule('BR-DE-18', 'BT-20', 'Skonto entries in the payment terms (BT-20) shall match #SKONTO#TAGE=n#PROZENT=n.nn#(#BASISBETRAG=n.nn#), each terminated by a line break.', static fn (Invoice $invoice): bool => self::skontoTermsWellFormed($invoice->paymentTerms)),
            new InvoiceRule('BR-DE-21', 'BT-24', 'The Specification identifier (BT-24) should be the XRechnung CIUS, extension or CVD identifier.', static fn (Invoice $invoice): bool => $invoice->customizationId === null || in_array($invoice->customizationId, self::XRECHNUNG_SPECIFICATION_IDS, true), Severity::Warning),
            new InvoiceRule('BR-DE-27', 'BT-42', 'The Seller contact telephone number (BT-42) should contain at least three digits.', static fn (Invoice $invoice): bool => ! self::hasSellerContact($invoice) || preg_match_all('/\d/', $invoice->seller->contactPhone ?? '') >= 3, Severity::Warning),
            new InvoiceRule('BR-DE-28', 'BT-43', 'The Seller contact email address (BT-43) should contain exactly one @, a dotted domain and no whitespace.', static fn (Invoice $invoice): bool => ! self::hasSellerContact($invoice) || preg_match('/^[^@\s]+@([^@.\s]+\.)+[^@.\s]+$/D', mb_trim((string) preg_replace('/[\s\p{Zs}]+/u', ' ', $invoice->seller->contactEmail ?? ''))) === 1, Severity::Warning),

            new PaymentMeansRule('BR-DE-19', 'BT-84', 'For SEPA credit transfers (code 58) the payment account identifier (BT-84) should be a valid IBAN.', static fn (PaymentMeans $paymentMeans): bool => mb_trim((string) $paymentMeans->typeCode) !== '58' || self::isValidIban($paymentMeans->accountId), Severity::Warning),
            new PaymentMeansRule('BR-DE-20', 'BT-91', 'For SEPA direct debits (code 59) the debited account identifier (BT-91) should be a valid IBAN.', static fn (PaymentMeans $paymentMeans): bool => mb_trim((string) $paymentMeans->typeCode) !== '59' || self::isValidIban($paymentMeans->debitedAccountId), Severity::Warning),
            new PaymentMeansRule('BR-DE-23-a', 'BT-81', 'A credit transfer (code 30/58) shall carry the CREDIT TRANSFER group (BG-17).', static fn (PaymentMeans $paymentMeans): bool => ! in_array(mb_trim((string) $paymentMeans->typeCode), ['30', '58'], true) || $paymentMeans->hasCreditTransfer),
            new PaymentMeansRule('BR-DE-23-b', 'BT-81', 'A credit transfer (code 30/58) shall not carry the card or direct debit groups (BG-18/BG-19).', static fn (PaymentMeans $paymentMeans): bool => ! in_array(mb_trim((string) $paymentMeans->typeCode), ['30', '58'], true) || (! $paymentMeans->hasCardInformation && ! $paymentMeans->hasDirectDebit)),
            new PaymentMeansRule('BR-DE-24-a', 'BT-81', 'A card payment (code 48/54/55) shall carry the PAYMENT CARD INFORMATION group (BG-18).', static fn (PaymentMeans $paymentMeans): bool => ! in_array(mb_trim((string) $paymentMeans->typeCode), ['48', '54', '55'], true) || $paymentMeans->hasCardInformation),
            new PaymentMeansRule('BR-DE-24-b', 'BT-81', 'A card payment (code 48/54/55) shall not carry the credit transfer or direct debit groups (BG-17/BG-19).', static fn (PaymentMeans $paymentMeans): bool => ! in_array(mb_trim((string) $paymentMeans->typeCode), ['48', '54', '55'], true) || (! $paymentMeans->hasCreditTransfer && ! $paymentMeans->hasDirectDebit)),
            new PaymentMeansRule('BR-DE-25-a', 'BT-81', 'A direct debit (code 59) shall carry the DIRECT DEBIT group (BG-19).', static fn (PaymentMeans $paymentMeans): bool => mb_trim((string) $paymentMeans->typeCode) !== '59' || $paymentMeans->hasDirectDebit),
            new PaymentMeansRule('BR-DE-25-b', 'BT-81', 'A direct debit (code 59) shall not carry the credit transfer or card groups (BG-17/BG-18).', static fn (PaymentMeans $paymentMeans): bool => mb_trim((string) $paymentMeans->typeCode) !== '59' || (! $paymentMeans->hasCreditTransfer && ! $paymentMeans->hasCardInformation)),
            new InvoiceRule('BR-DE-22', 'BT-125', 'The filenames of all embedded attachments shall be unique.', static fn (Invoice $invoice): bool => self::attachmentFilenamesUnique($invoice)),
            new InvoiceRule('BR-DE-26', 'BT-3', 'A corrected invoice (type code 384) should reference the preceding invoice (BG-3).', static fn (Invoice $invoice): bool => $invoice->typeCode !== '384' || $invoice->precedingInvoiceReferences !== [], Severity::Warning),
            new InvoiceRule('BR-DE-30', 'BT-90', 'A direct debit (BG-19) requires the bank assigned creditor identifier (BT-90).', static fn (Invoice $invoice): bool => ! self::hasDirectDebitGroup($invoice) || self::filled($invoice->sepaCreditorId)),
            new InvoiceRule('BR-DE-31', 'BT-91', 'A direct debit (BG-19) requires the debited account identifier (BT-91).', static fn (Invoice $invoice): bool => ! self::hasDirectDebitGroup($invoice) || array_any($invoice->paymentMeans, fn (PaymentMeans $paymentMeans): bool => self::filled($paymentMeans->debitedAccountId))),
        ];
    }

    /**
     * BR-*-02/-03/-04: the VAT-identification requirements each category places
     * on the parties, triggered by lines (-02), document allowances (-03) and
     * document charges (-04) — derived from the official Schematron asserts.
     *
     * @return list<Rule>
     */
    private static function vatIdentificationRules(): array
    {
        $sellerIdentified = static fn (Invoice $invoice): bool => self::sellerTaxIdentified($invoice);
        $reverseCharge = static fn (Invoice $invoice): bool => self::sellerTaxIdentified($invoice) && ($invoice->buyer->hasVatId() || self::filled($invoice->buyer->legalRegistrationId));
        $intraCommunity = static fn (Invoice $invoice): bool => self::sellerVatOrRepresentativeVat($invoice) && $invoice->buyer->hasVatId();
        $export = static fn (Invoice $invoice): bool => self::sellerVatOrRepresentativeVat($invoice);
        $notSubject = static fn (Invoice $invoice): bool => ! $invoice->seller->hasVatId() && ! ($invoice->taxRepresentative?->hasVatId() ?? false) && ! $invoice->buyer->hasVatId();

        $requirements = [
            'S' => ['BR-S', $sellerIdentified, 'the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or tax representative VAT identifier (BT-63)'],
            'Z' => ['BR-Z', $sellerIdentified, 'the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or tax representative VAT identifier (BT-63)'],
            'E' => ['BR-E', $sellerIdentified, 'the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or tax representative VAT identifier (BT-63)'],
            'L' => ['BR-AF', $sellerIdentified, 'the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or tax representative VAT identifier (BT-63)'],
            'M' => ['BR-AG', $sellerIdentified, 'the Seller VAT identifier (BT-31), tax registration identifier (BT-32) or tax representative VAT identifier (BT-63)'],
            'AE' => ['BR-AE', $reverseCharge, 'a Seller VAT/tax identification (BT-31, BT-32 or BT-63) and a Buyer VAT (BT-48) or legal registration identifier (BT-47)'],
            'K' => ['BR-IC', $intraCommunity, 'the Seller (BT-31) or tax representative VAT identifier (BT-63) and the Buyer VAT identifier (BT-48)'],
            'G' => ['BR-G', $export, 'the Seller VAT identifier (BT-31) or tax representative VAT identifier (BT-63)'],
            'O' => ['BR-O', $notSubject, 'no Seller (BT-31), tax representative (BT-63) or Buyer VAT identifier (BT-48)'],
        ];

        $rules = [];

        foreach ($requirements as $category => [$id, $satisfied, $requirement]) {
            $rules[] = new ConditionalRule("{$id}-02", 'BT-151', "An invoice with a {$category} line shall contain {$requirement}.", static fn (Invoice $invoice): bool => self::lineHasCategory($invoice, $category), $satisfied);
            $rules[] = new ConditionalRule("{$id}-03", 'BT-95', "An invoice with a {$category} document allowance shall contain {$requirement}.", static fn (Invoice $invoice): bool => self::allowanceChargeHasCategory($invoice, $category, false), $satisfied);
            $rules[] = new ConditionalRule("{$id}-04", 'BT-102', "An invoice with a {$category} document charge shall contain {$requirement}.", static fn (Invoice $invoice): bool => self::allowanceChargeHasCategory($invoice, $category, true), $satisfied);
        }

        return $rules;
    }

    /**
     * BR-31..33 / BR-36..38: the mandatory fields of document level allowances
     * (BG-20) and charges (BG-21).
     *
     * @return list<Rule>
     */
    private static function documentAllowanceChargeRules(): array
    {
        return [
            new AllowanceRule('BR-31', 'BT-92', 'Each document level allowance shall have an amount (BT-92).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->amount), charges: false),
            new AllowanceRule('BR-32', 'BT-95', 'Each document level allowance shall have a VAT category code (BT-95).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->taxCategory), charges: false),
            new AllowanceRule('BR-33', 'BT-97', 'Each document level allowance shall have a reason (BT-97) or a reason code (BT-98).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->reason) || self::filled($documentAllowanceCharge->reasonCode), charges: false),
            new AllowanceRule('BR-36', 'BT-99', 'Each document level charge shall have an amount (BT-99).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->amount), charges: true),
            new AllowanceRule('BR-37', 'BT-102', 'Each document level charge shall have a VAT category code (BT-102).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->taxCategory), charges: true),
            new AllowanceRule('BR-38', 'BT-104', 'Each document level charge shall have a reason (BT-104) or a reason code (BT-105).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::filled($documentAllowanceCharge->reason) || self::filled($documentAllowanceCharge->reasonCode), charges: true),
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
            new AllowanceRule('BR-DEC-01', 'BT-92', 'A document-level allowance amount (BT-92) shall not have more than two decimals.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::maxTwoDecimals($documentAllowanceCharge->amount), charges: false),
            new AllowanceRule('BR-DEC-02', 'BT-93', 'A document-level allowance base amount (BT-93) shall not have more than two decimals.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::maxTwoDecimals($documentAllowanceCharge->baseAmount), charges: false),
            new AllowanceRule('BR-DEC-05', 'BT-99', 'A document-level charge amount (BT-99) shall not have more than two decimals.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::maxTwoDecimals($documentAllowanceCharge->amount), charges: true),
            new AllowanceRule('BR-DEC-06', 'BT-100', 'A document-level charge base amount (BT-100) shall not have more than two decimals.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => self::maxTwoDecimals($documentAllowanceCharge->baseAmount), charges: true),
            new InvoiceRule('BR-DEC-15', 'BT-111', 'The invoice total VAT amount in accounting currency (BT-111) shall not have more than two decimals.', static fn (Invoice $invoice): bool => self::maxTwoDecimals($invoice->totals->taxTotalAccounting)),
            new LineAllowanceRule('BR-DEC-24', 'BT-136', 'A line allowance amount (BT-136) shall not have more than two decimals.', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => $lineAllowanceCharge->isCharge || self::maxTwoDecimals($lineAllowanceCharge->amount)),
            new LineAllowanceRule('BR-DEC-25', 'BT-137', 'A line allowance base amount (BT-137) shall not have more than two decimals.', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => $lineAllowanceCharge->isCharge || self::maxTwoDecimals($lineAllowanceCharge->baseAmount)),
            new LineAllowanceRule('BR-DEC-27', 'BT-141', 'A line charge amount (BT-141) shall not have more than two decimals.', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => ! $lineAllowanceCharge->isCharge || self::maxTwoDecimals($lineAllowanceCharge->amount)),
            new LineAllowanceRule('BR-DEC-28', 'BT-142', 'A line charge base amount (BT-142) shall not have more than two decimals.', static fn (LineAllowanceCharge $lineAllowanceCharge): bool => ! $lineAllowanceCharge->isCharge || self::maxTwoDecimals($lineAllowanceCharge->baseAmount)),
        ];
    }

    /**
     * Per-VAT-category consistency rules (BR-S/Z/E/AE/IC/G/O): breakdown-group
     * existence (-01), line rates (-05), document allowance/charge rates
     * (-06/-07), zero tax amounts (-09) and exemption reasons (-10). The
     * taxable-sum reconciliation (BR-*-08) lives in {@see TaxableSumRule}.
     *
     * @return list<Rule>
     */
    private static function categoryRules(): array
    {
        $categoryPrefix = ['S' => 'BR-S', 'Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O', 'L' => 'BR-AF', 'M' => 'BR-AG'];
        $rules = [];

        // BR-*-01: a used VAT category (line, document allowance or charge)
        // must have a matching breakdown group. Officially S, L and M work both
        // ways (usage ⇔ at least one group); every other category requires
        // exactly one group whenever the category appears anywhere.
        foreach (['S' => 'BR-S', 'L' => 'BR-AF', 'M' => 'BR-AG'] as $category => $id) {
            $rules[] = new ConditionalRule("{$id}-01", 'BG-23', "An invoice using VAT category {$category} shall contain at least one matching VAT breakdown group — and none without such usage.", static fn (Invoice $invoice): bool => self::usesCategory($invoice, $category) || self::subtotalHasCategory($invoice, $category), static fn (Invoice $invoice): bool => self::usesCategory($invoice, $category) && self::subtotalHasCategory($invoice, $category));
        }

        foreach (array_diff_key($categoryPrefix, ['S' => true, 'L' => true, 'M' => true]) as $category => $id) {
            $rules[] = new ConditionalRule("{$id}-01", 'BG-23', "An invoice using VAT category {$category} shall contain exactly one matching VAT breakdown group.", static fn (Invoice $invoice): bool => self::usesCategory($invoice, $category) || self::subtotalHasCategory($invoice, $category), static fn (Invoice $invoice): bool => self::subtotalCategoryCount($invoice, $category) === 1);
        }

        // BR-*-09: categories that carry no VAT must have a category tax amount of 0.
        foreach (['Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-09", 'BT-117', "A {$category} VAT breakdown group shall have a category tax amount (BT-117) of 0.", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || self::isZeroOrMissing($taxSubtotal->taxAmount));
        }

        // BR-{IC,G,O}-10: exemption reason required (E and AE covered in the core set).
        foreach (['K' => 'BR-IC', 'G' => 'BR-G', 'O' => 'BR-O'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-10", 'BT-120', "A {$category} VAT breakdown group shall have a VAT exemption reason (BT-120/BT-121).", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || self::hasExemptionReason($taxSubtotal));
        }

        // BR-{S,Z,AF,AG}-10: these groups must NOT carry an exemption reason.
        foreach (['S' => 'BR-S', 'Z' => 'BR-Z', 'L' => 'BR-AF', 'M' => 'BR-AG'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-10", 'BT-120', "A {$category} VAT breakdown group shall not have a VAT exemption reason.", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || ! self::hasExemptionReason($taxSubtotal));
        }

        // BR-{S,AF,AG}-09: the category tax amount must equal taxable × rate.
        // The official asserts allow the result to be off by strictly less
        // than one unit (abs(BT-117) within ±1 of round(|BT-116| × BT-119)).
        foreach (['S' => 'BR-S', 'L' => 'BR-AF', 'M' => 'BR-AG'] as $category => $id) {
            $rules[] = new SubtotalRule("{$id}-09", 'BT-117', "A {$category} category tax amount (BT-117) shall equal the taxable amount (BT-116) multiplied by the rate (BT-119).", static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category !== $category || self::categoryTaxWithinTolerance($taxSubtotal));
        }

        // BR-{Z,E,AE,IC,G}-05: a line of a zero-VAT category must carry a rate of
        // exactly 0 — officially an absent rate fails the assert too.
        foreach (['Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G'] as $category => $id) {
            $rules[] = new LineRule("{$id}-05", 'BT-152', "A line with VAT category {$category} shall have a VAT rate (BT-152) of 0.", static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory !== $category || self::isZero($invoiceLine->taxRate));
        }

        // BR-*-06 / BR-*-07: document-level allowance (-06, BT-96) and charge
        // (-07, BT-103) VAT rates per category — distinct official rule ids.
        $rules[] = new AllowanceRule('BR-S-06', 'BT-96', 'A Standard-rated (S) document-level allowance shall have a VAT rate (BT-96) greater than 0.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== 'S' || self::isPositive($documentAllowanceCharge->taxRate), charges: false);
        $rules[] = new AllowanceRule('BR-S-07', 'BT-103', 'A Standard-rated (S) document-level charge shall have a VAT rate (BT-103) greater than 0.', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== 'S' || self::isPositive($documentAllowanceCharge->taxRate), charges: true);

        foreach (['Z' => 'BR-Z', 'E' => 'BR-E', 'AE' => 'BR-AE', 'K' => 'BR-IC', 'G' => 'BR-G'] as $category => $id) {
            $rules[] = new AllowanceRule("{$id}-06", 'BT-96', "A document-level allowance with VAT category {$category} shall have a VAT rate (BT-96) of 0.", static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== $category || self::isZero($documentAllowanceCharge->taxRate), charges: false);
            $rules[] = new AllowanceRule("{$id}-07", 'BT-103', "A document-level charge with VAT category {$category} shall have a VAT rate (BT-103) of 0.", static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== $category || self::isZero($documentAllowanceCharge->taxRate), charges: true);
        }

        // BR-AF/AG-05/-06/-07: IGIC (L) and IPSI (M) rates must be present and
        // 0 or greater — these are real taxes with positive rates.
        foreach (['L' => 'BR-AF', 'M' => 'BR-AG'] as $category => $id) {
            $rules[] = new LineRule("{$id}-05", 'BT-152', "A line with VAT category {$category} shall have a VAT rate (BT-152) of 0 or greater.", static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory !== $category || self::isZeroOrGreater($invoiceLine->taxRate));
            $rules[] = new AllowanceRule("{$id}-06", 'BT-96', "A document-level allowance with VAT category {$category} shall have a VAT rate (BT-96) of 0 or greater.", static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== $category || self::isZeroOrGreater($documentAllowanceCharge->taxRate), charges: false);
            $rules[] = new AllowanceRule("{$id}-07", 'BT-103', "A document-level charge with VAT category {$category} shall have a VAT rate (BT-103) of 0 or greater.", static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== $category || self::isZeroOrGreater($documentAllowanceCharge->taxRate), charges: true);
        }

        // BR-O-05/-06/-07: Not-subject-to-VAT items must not carry a rate at all.
        $rules[] = new LineRule('BR-O-05', 'BT-152', 'A line with VAT category O shall not contain a VAT rate (BT-152).', static fn (InvoiceLine $invoiceLine): bool => $invoiceLine->taxCategory !== 'O' || $invoiceLine->taxRate === null);
        $rules[] = new AllowanceRule('BR-O-06', 'BT-96', 'A document-level allowance with VAT category O shall not contain a VAT rate (BT-96).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== 'O' || $documentAllowanceCharge->taxRate === null, charges: false);
        $rules[] = new AllowanceRule('BR-O-07', 'BT-103', 'A document-level charge with VAT category O shall not contain a VAT rate (BT-103).', static fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->taxCategory !== 'O' || $documentAllowanceCharge->taxRate === null, charges: true);

        // BR-O-11..14: an O breakdown group excludes every other category.
        $hasOtherThanO = static fn (?string $category): bool => $category !== null && $category !== 'O';
        $rules[] = new ConditionalRule('BR-O-11', 'BG-23', 'An invoice with a Not-subject-to-VAT (O) breakdown group shall not contain other VAT breakdown groups.', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'O'), static fn (Invoice $invoice): bool => ! array_any($invoice->taxSubtotals, fn (TaxSubtotal $taxSubtotal): bool => $hasOtherThanO($taxSubtotal->category)));
        $rules[] = new ConditionalRule('BR-O-12', 'BT-151', 'An invoice with a Not-subject-to-VAT (O) breakdown group shall not contain lines with another VAT category.', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'O'), static fn (Invoice $invoice): bool => ! array_any($invoice->lines, fn (InvoiceLine $invoiceLine): bool => $hasOtherThanO($invoiceLine->taxCategory)));
        $rules[] = new ConditionalRule('BR-O-13', 'BT-95', 'An invoice with a Not-subject-to-VAT (O) breakdown group shall not contain document allowances with another VAT category.', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'O'), static fn (Invoice $invoice): bool => ! array_any($invoice->allowanceCharges, fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => ! $documentAllowanceCharge->isCharge && $hasOtherThanO($documentAllowanceCharge->taxCategory)));
        $rules[] = new ConditionalRule('BR-O-14', 'BT-102', 'An invoice with a Not-subject-to-VAT (O) breakdown group shall not contain document charges with another VAT category.', static fn (Invoice $invoice): bool => self::subtotalHasCategory($invoice, 'O'), static fn (Invoice $invoice): bool => ! array_any($invoice->allowanceCharges, fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => $documentAllowanceCharge->isCharge && $hasOtherThanO($documentAllowanceCharge->taxCategory)));

        // BR-B-01/-02: split payment (B) is domestic Italian only and excludes S.
        $rules[] = new ConditionalRule('BR-B-01', 'BT-151', 'An invoice using VAT category B (split payment) shall be a domestic Italian invoice.', static fn (Invoice $invoice): bool => self::categoryAppearsAnywhere($invoice, 'B'), static fn (Invoice $invoice): bool => self::allCountryCodesAre($invoice, 'IT'));
        $rules[] = new ConditionalRule('BR-B-02', 'BT-151', 'An invoice using VAT category B (split payment) shall not also use category S (standard rated).', static fn (Invoice $invoice): bool => self::categoryAppearsAnywhere($invoice, 'B'), static fn (Invoice $invoice): bool => ! self::categoryAppearsAnywhere($invoice, 'S'));

        return $rules;
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && mb_trim($value) !== '';
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

    private static function isZero(?string $value): bool
    {
        return Decimal::isNumeric($value) && Decimal::compare($value, '0') === 0;
    }

    private static function isZeroOrGreater(?string $value): bool
    {
        return Decimal::isNumeric($value) && Decimal::compare($value, '0') >= 0;
    }

    /**
     * @return numeric-string
     */
    private static function absolute(string $value): string
    {
        if (! Decimal::isNumeric($value)) {
            return '0';
        }

        return Decimal::isNegative($value) ? Decimal::sub('0', $value) : $value;
    }

    /**
     * BR-{S,AF,AG}-09: |BT-117| must lie strictly within ±1 of
     * round(|BT-116| × BT-119 / 100) — the tolerance the official asserts use.
     * Skipped when a value is missing (presence is owned by BR-45/46/48).
     */
    private static function categoryTaxWithinTolerance(TaxSubtotal $taxSubtotal): bool
    {
        if (! Decimal::isNumeric($taxSubtotal->taxableAmount) || ! Decimal::isNumeric($taxSubtotal->rate) || ! Decimal::isNumeric($taxSubtotal->taxAmount)) {
            return true;
        }

        $expected = Decimal::round(Decimal::mul(self::absolute($taxSubtotal->taxableAmount), Decimal::mul($taxSubtotal->rate, '0.01')));
        $difference = Decimal::sub(self::absolute($taxSubtotal->taxAmount), $expected);

        return Decimal::compare(self::absolute($difference), '1') < 0;
    }

    /**
     * BT-31 or BT-32 present on the seller, or a tax representative VAT id (BT-63).
     */
    private static function sellerTaxIdentified(Invoice $invoice): bool
    {
        if ($invoice->seller->hasVatId() || self::filled($invoice->seller->taxRegistrationId)) {
            return true;
        }

        return $invoice->taxRepresentative?->hasVatId() ?? false;
    }

    private static function sellerVatOrRepresentativeVat(Invoice $invoice): bool
    {
        if ($invoice->seller->hasVatId()) {
            return true;
        }

        return $invoice->taxRepresentative?->hasVatId() ?? false;
    }

    /**
     * BR-CO-09: every present VAT identifier must start with an ISO 3166-1
     * alpha-2 code (or the EL / XI specials).
     */
    private static function vatIdentifiersHaveCountryPrefix(Invoice $invoice): bool
    {
        foreach ([$invoice->seller->vatId, $invoice->buyer->vatId, $invoice->taxRepresentative?->vatId] as $vatId) {
            if (! self::filled($vatId)) {
                continue;
            }

            $prefix = mb_substr(mb_trim((string) $vatId), 0, 2);

            if (! in_array($prefix, CountryCodes::CODES, true) && ! in_array($prefix, CodeLists::VAT_PREFIX_EXTRAS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * BR-DE-18: every payment-terms line starting with '#' must be a complete
     * #SKONTO#TAGE=n#PROZENT=n.nn#(#BASISBETRAG=n.nn#) entry, and the last
     * entry must be terminated by a line break — mirroring the official assert.
     */
    private static function skontoTermsWellFormed(?string $paymentTerms): bool
    {
        if ($paymentTerms === null) {
            return true;
        }

        $skontoSeen = false;

        foreach (preg_split('/\r?\n/', $paymentTerms) ?: [] as $line) {
            $normalized = mb_trim((string) preg_replace('/[\s\p{Zs}]+/u', ' ', $line));

            if (! str_starts_with($normalized, '#')) {
                continue;
            }

            $skontoSeen = true;

            if (preg_match('/^#SKONTO#TAGE=[0-9]+#PROZENT=[0-9]+\.[0-9]{2}(#BASISBETRAG=-?[0-9]+\.[0-9]{2})?#$/D', $normalized) !== 1) {
                return false;
            }
        }

        if (! $skontoSeen) {
            return true;
        }

        $segments = preg_split('/#.+#/', $paymentTerms) ?: [];

        return preg_match('/^\s*\n/', (string) end($segments)) === 1;
    }

    /**
     * @param  list<string>  $categories
     */
    private static function usesAnyVatCategoryOf(Invoice $invoice, array $categories): bool
    {
        return array_any($categories, fn (string $category): bool => self::usesCategory($invoice, $category));
    }

    /**
     * BR-29/BR-30: when both period dates are present (as normalized Y-m-d),
     * the end shall not precede the start. Anything unparseable passes — date
     * syntax is schema territory.
     */
    private static function periodOrdered(?string $start, ?string $end): bool
    {
        if ($start === null || $end === null) {
            return true;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            return true;
        }

        return $end >= $start;
    }

    /**
     * BR-17: the payee needs a name that differs from the seller, and its
     * identifier (when both are present) must differ too.
     */
    private static function payeeDiffersFromSeller(Invoice $invoice): bool
    {
        $payee = $invoice->payee;

        if (! $payee instanceof Party || ! self::filled($payee->name)) {
            return false;
        }

        if ($payee->name === $invoice->seller->name) {
            return false;
        }

        return $payee->identifier === null || $invoice->seller->identifier === null || $payee->identifier !== $invoice->seller->identifier;
    }

    /**
     * BR-DE-22: every present attachment filename must be unique.
     */
    private static function attachmentFilenamesUnique(Invoice $invoice): bool
    {
        $filenames = [];

        foreach ($invoice->attachments as $attachment) {
            if ($attachment->filename !== null) {
                $filenames[] = $attachment->filename;
            }
        }

        return count($filenames) === count(array_unique($filenames));
    }

    private static function hasDirectDebitGroup(Invoice $invoice): bool
    {
        return array_any($invoice->paymentMeans, fn (PaymentMeans $paymentMeans): bool => $paymentMeans->hasDirectDebit);
    }

    /**
     * BR-DE-19/20: the official IBAN check — the structural pattern plus the
     * ISO 7064 mod-97 checksum, exactly as in the KoSIT asserts.
     */
    private static function isValidIban(?string $value): bool
    {
        $iban = (string) preg_replace('/[\s\p{Zs}]+/u', '', (string) $value);

        if (preg_match('/^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$/D', $iban) !== 1) {
            return false;
        }

        $numeric = '';

        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord(strtoupper($character)) - 55) : $character;
        }

        return bcmod($numeric, '97') === '1';
    }

    private static function hasSellerContact(Invoice $invoice): bool
    {
        if (self::filled($invoice->seller->contactName)) {
            return true;
        }
        if (self::filled($invoice->seller->contactPhone)) {
            return true;
        }

        return self::filled($invoice->seller->contactEmail);
    }

    /**
     * Whether the category appears anywhere at all — usage (BT-151/95/102) or
     * a breakdown group (BT-118).
     */
    private static function categoryAppearsAnywhere(Invoice $invoice, string $category): bool
    {
        if (self::usesCategory($invoice, $category)) {
            return true;
        }

        return self::subtotalHasCategory($invoice, $category);
    }

    /**
     * BR-B-01: every present party country code must equal the given one.
     */
    private static function allCountryCodesAre(Invoice $invoice, string $countryCode): bool
    {
        return array_all([$invoice->seller->countryCode, $invoice->buyer->countryCode, $invoice->taxRepresentative?->countryCode], fn (?string $code): bool => $code === null || $code === $countryCode);
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

        return preg_match('/^-?\d+(\.\d{1,2})?$/', mb_trim($value)) === 1;
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

    /**
     * Whether the category is used anywhere it can be declared: on an invoice
     * line (BT-151) or a document-level allowance/charge (BT-95/BT-102).
     */
    private static function usesCategory(Invoice $invoice, string $category): bool
    {
        if (self::lineHasCategory($invoice, $category)) {
            return true;
        }

        return self::allowanceChargeHasCategory($invoice, $category);
    }

    /**
     * Whether a document-level allowance (charges = false), charge (true) or
     * either (null) carries the given VAT category.
     */
    private static function allowanceChargeHasCategory(Invoice $invoice, string $category, ?bool $charges = null): bool
    {
        return array_any($invoice->allowanceCharges, fn (DocumentAllowanceCharge $documentAllowanceCharge): bool => ($charges === null || $documentAllowanceCharge->isCharge === $charges) && $documentAllowanceCharge->taxCategory === $category);
    }

    private static function subtotalCategoryCount(Invoice $invoice, string $category): int
    {
        return count(array_filter($invoice->taxSubtotals, static fn (TaxSubtotal $taxSubtotal): bool => $taxSubtotal->category === $category));
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
