<?php

declare(strict_types=1);

namespace JohnWink\En16931\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
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
use JohnWink\En16931\Model\Totals;
use RuntimeException;

/**
 * Builds the normalized {@see Invoice} model from a UN/CEFACT Cross-Industry
 * Invoice (CII) document — the syntax ZUGFeRD/Factur-X and XRechnung-CII use.
 * Values are read as the raw XML text (exact decimal strings), never floats.
 */
final class CiiInvoiceReader
{
    use HandlesXmlNodes;

    private const string RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const string RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const string UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    public function read(string $xml): Invoice
    {
        $xpath = $this->xpath($xml);

        $currency = $this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode');
        $taxCurrency = $this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode');

        return new Invoice(
            number: $this->value($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID'),
            typeCode: $this->value($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'),
            issueDate: $this->date($this->value($xpath, '//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString')),
            currency: $currency,
            taxCurrency: $taxCurrency,
            buyerReference: $this->value($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference'),
            customizationId: $this->value($xpath, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'),
            seller: $this->party($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty'),
            buyer: $this->party($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty'),
            totals: $this->totals($xpath, $currency, $taxCurrency),
            lines: $this->lines($xpath),
            taxSubtotals: $this->taxSubtotals($xpath),
            notes: $this->notes($xpath),
            allowanceCharges: $this->allowanceCharges($xpath),
            paymentDueDate: $this->date($this->value($xpath, '//ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString')),
            paymentTerms: $this->rawValue($xpath, '//ram:SpecifiedTradePaymentTerms/ram:Description'),
            taxRepresentative: $this->taxRepresentative($xpath),
            paymentMeans: $this->paymentMeans($xpath),
            sepaCreditorId: $this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID'),
            hasInvoicingPeriod: $this->node($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod') instanceof DOMElement,
            invoicingPeriodStart: $this->date($this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString')),
            invoicingPeriodEnd: $this->date($this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString')),
            actualDeliveryDate: $this->date($this->value($xpath, '//ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString')),
            deliverTo: $this->deliverTo($xpath),
            payee: $this->node($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty') instanceof DOMElement ? $this->party($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty') : null,
            taxPointDate: $this->date($this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:TaxPointDate/udt:DateString')),
            attachments: $this->attachments($xpath),
            precedingInvoiceReferences: $this->precedingInvoiceReferences($xpath),
            amountCurrencyCodes: $this->amountCurrencyCodes($xpath),
        );
    }

    /**
     * Every distinct @currencyID used on an amount (BR-CL-03).
     *
     * @return list<string>
     */
    private function amountCurrencyCodes(DOMXPath $domxPath): array
    {
        $codes = [];
        $list = $domxPath->query('//@currencyID');

        if ($list !== false) {
            foreach ($list as $attributeNode) {
                $value = mb_trim($attributeNode->nodeValue ?? '');

                if ($value !== '') {
                    $codes[$value] = true;
                }
            }
        }

        return array_keys($codes);
    }

    /**
     * Every additional referenced document — supporting documents (type 916)
     * and invoiced-object references (type 130) alike.
     *
     * @return list<Attachment>
     */
    private function attachments(DOMXPath $domxPath): array
    {
        $attachments = [];

        foreach ($this->nodes($domxPath, '//ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument') as $domElement) {
            $attachments[] = new Attachment(
                reference: $this->value($domxPath, 'ram:IssuerAssignedID', $domElement),
                filename: $this->attribute($domxPath, 'ram:AttachmentBinaryObject', 'filename', $domElement),
                mimeCode: $this->attribute($domxPath, 'ram:AttachmentBinaryObject', 'mimeCode', $domElement),
                typeCode: $this->value($domxPath, 'ram:TypeCode', $domElement),
                scheme: $this->value($domxPath, 'ram:ReferenceTypeCode', $domElement),
            );
        }

        return $attachments;
    }

    /**
     * @return list<string|null>
     */
    private function precedingInvoiceReferences(DOMXPath $domxPath): array
    {
        $references = [];

        foreach ($this->nodes($domxPath, '//ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument') as $domElement) {
            $references[] = $this->value($domxPath, 'ram:IssuerAssignedID', $domElement);
        }

        return $references;
    }

    /**
     * The deliver-to address (BG-15) — in CII the group counts as present when
     * the ShipToTradeParty carries a postal address.
     */
    private function deliverTo(DOMXPath $domxPath): ?Party
    {
        $address = $this->node($domxPath, '//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress');

        if (! $address instanceof DOMElement) {
            return null;
        }

        return new Party(
            name: $this->value($domxPath, '//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:Name'),
            countryCode: $this->value($domxPath, 'ram:CountryID', $address),
            street: $this->value($domxPath, 'ram:LineOne', $address),
            city: $this->value($domxPath, 'ram:CityName', $address),
            postCode: $this->value($domxPath, 'ram:PostcodeCode', $address),
        );
    }

    /**
     * BG-16. In CII the direct debit group (BG-19) is spread across the
     * settlement: the mandate reference (BT-89) lives in the payment terms,
     * the debited account (BT-91) inside the payment means.
     *
     * @return list<PaymentMeans>
     */
    private function paymentMeans(DOMXPath $domxPath): array
    {
        $hasMandate = $this->node($domxPath, '//ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID') instanceof DOMElement;
        $result = [];

        foreach ($this->nodes($domxPath, '//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans') as $domElement) {
            $debtorAccount = $this->node($domxPath, 'ram:PayerPartyDebtorFinancialAccount', $domElement);

            $result[] = new PaymentMeans(
                typeCode: $this->value($domxPath, 'ram:TypeCode', $domElement),
                accountId: $this->value($domxPath, 'ram:PayeePartyCreditorFinancialAccount/ram:IBANID | ram:PayeePartyCreditorFinancialAccount/ram:ProprietaryID', $domElement),
                hasCreditTransfer: $this->node($domxPath, 'ram:PayeePartyCreditorFinancialAccount', $domElement) instanceof DOMElement,
                hasCardInformation: $this->node($domxPath, 'ram:ApplicableTradeSettlementFinancialCard', $domElement) instanceof DOMElement,
                cardNumber: $this->value($domxPath, 'ram:ApplicableTradeSettlementFinancialCard/ram:ID', $domElement),
                hasDirectDebit: $hasMandate || $debtorAccount instanceof DOMElement,
                debitedAccountId: $this->value($domxPath, 'ram:PayerPartyDebtorFinancialAccount/ram:IBANID', $domElement),
            );
        }

        return $result;
    }

    /**
     * The Seller tax representative party (BG-11) — null when the group is absent.
     */
    private function taxRepresentative(DOMXPath $domxPath): ?Party
    {
        $base = '//ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty';

        return $this->node($domxPath, $base) instanceof DOMElement
            ? $this->party($domxPath, $base)
            : null;
    }

    /**
     * @return list<DocumentAllowanceCharge>
     */
    private function allowanceCharges(DOMXPath $domxPath): array
    {
        $result = [];

        foreach ($this->nodes($domxPath, '//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge') as $domElement) {
            $indicator = $this->value($domxPath, 'ram:ChargeIndicator/udt:Indicator', $domElement);

            $result[] = new DocumentAllowanceCharge(
                isCharge: $indicator === 'true' || $indicator === '1',
                amount: $this->value($domxPath, 'ram:ActualAmount', $domElement),
                taxCategory: $this->value($domxPath, 'ram:CategoryTradeTax/ram:CategoryCode', $domElement),
                taxRate: $this->value($domxPath, 'ram:CategoryTradeTax/ram:RateApplicablePercent', $domElement),
                reason: $this->value($domxPath, 'ram:Reason', $domElement),
                reasonCode: $this->value($domxPath, 'ram:ReasonCode', $domElement),
                baseAmount: $this->value($domxPath, 'ram:BasisAmount', $domElement),
            );
        }

        return $result;
    }

    private function xpath(string $xml): DOMXPath
    {
        $domDocument = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        try {
            if (! $domDocument->loadXML($xml)) {
                throw new RuntimeException('The CII payload is not well-formed XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $domxPath = new DOMXPath($domDocument);
        $domxPath->registerNamespace('rsm', self::RSM);
        $domxPath->registerNamespace('ram', self::RAM);
        $domxPath->registerNamespace('udt', self::UDT);

        return $domxPath;
    }

    private function party(DOMXPath $domxPath, string $base): Party
    {
        $node = $this->node($domxPath, $base);
        if (! $node instanceof DOMElement) {
            return new Party;
        }

        return new Party(
            name: $this->value($domxPath, 'ram:Name', $node),
            countryCode: $this->value($domxPath, 'ram:PostalTradeAddress/ram:CountryID', $node),
            vatId: $this->value($domxPath, "ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']", $node),
            identifier: $this->value($domxPath, 'ram:ID', $node),
            legalRegistrationId: $this->value($domxPath, 'ram:SpecifiedLegalOrganization/ram:ID', $node),
            contactName: $this->value($domxPath, 'ram:DefinedTradeContact/ram:PersonName', $node),
            contactPhone: $this->value($domxPath, 'ram:DefinedTradeContact/ram:TelephoneUniversalCommunication/ram:CompleteNumber', $node),
            contactEmail: $this->value($domxPath, 'ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID', $node),
            taxRegistrationId: $this->value($domxPath, "ram:SpecifiedTaxRegistration/ram:ID[@schemeID='FC']", $node),
            street: $this->value($domxPath, 'ram:PostalTradeAddress/ram:LineOne', $node),
            city: $this->value($domxPath, 'ram:PostalTradeAddress/ram:CityName', $node),
            postCode: $this->value($domxPath, 'ram:PostalTradeAddress/ram:PostcodeCode', $node),
            electronicAddress: $this->value($domxPath, 'ram:URIUniversalCommunication/ram:URIID', $node),
            electronicAddressScheme: $this->attribute($domxPath, 'ram:URIUniversalCommunication/ram:URIID', 'schemeID', $node),
            identifierScheme: $this->attribute($domxPath, 'ram:GlobalID', 'schemeID', $node),
            legalRegistrationIdScheme: $this->attribute($domxPath, 'ram:SpecifiedLegalOrganization/ram:ID', 'schemeID', $node),
        );
    }

    private function totals(DOMXPath $domxPath, ?string $currency, ?string $taxCurrency): Totals
    {
        $base = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        return new Totals(
            lineTotal: $this->value($domxPath, "{$base}/ram:LineTotalAmount"),
            allowanceTotal: $this->value($domxPath, "{$base}/ram:AllowanceTotalAmount"),
            chargeTotal: $this->value($domxPath, "{$base}/ram:ChargeTotalAmount"),
            taxBasisTotal: $this->value($domxPath, "{$base}/ram:TaxBasisTotalAmount"),
            taxTotal: $this->taxTotalInCurrency($domxPath, "{$base}/ram:TaxTotalAmount", $currency),
            grandTotal: $this->value($domxPath, "{$base}/ram:GrandTotalAmount"),
            paidAmount: $this->value($domxPath, "{$base}/ram:TotalPrepaidAmount"),
            roundingAmount: $this->value($domxPath, "{$base}/ram:RoundingAmount"),
            payableAmount: $this->value($domxPath, "{$base}/ram:DuePayableAmount"),
            taxTotalAccounting: $taxCurrency !== null ? $this->value($domxPath, "{$base}/ram:TaxTotalAmount[@currencyID=\"{$taxCurrency}\"]") : null,
        );
    }

    /**
     * BT-110 is the TaxTotalAmount in the invoice currency; a second one in the
     * tax/accounting currency (BT-111) may also be present, so match by @currencyID.
     */
    private function taxTotalInCurrency(DOMXPath $domxPath, string $query, ?string $currency): ?string
    {
        $fallback = null;

        foreach ($this->nodes($domxPath, $query) as $domElement) {
            $text = $this->text($domElement);
            $fallback ??= $text;

            if ($currency !== null && $domElement->getAttribute('currencyID') === $currency) {
                return $text;
            }
        }

        return $fallback;
    }

    /**
     * @return list<InvoiceLine>
     */
    private function lines(DOMXPath $domxPath): array
    {
        $lines = [];

        foreach ($this->nodes($domxPath, '//ram:IncludedSupplyChainTradeLineItem') as $domElement) {
            $quantityNode = $this->node($domxPath, 'ram:SpecifiedLineTradeDelivery/ram:BilledQuantity', $domElement);

            $lines[] = new InvoiceLine(
                id: $this->value($domxPath, 'ram:AssociatedDocumentLineDocument/ram:LineID', $domElement),
                name: $this->value($domxPath, 'ram:SpecifiedTradeProduct/ram:Name', $domElement),
                netAmount: $this->value($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount', $domElement),
                netPrice: $this->value($domxPath, 'ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount', $domElement),
                quantity: $quantityNode instanceof DOMElement ? $this->text($quantityNode) : null,
                unitCode: $quantityNode instanceof DOMElement ? ($quantityNode->getAttribute('unitCode') ?: null) : null,
                taxCategory: $this->value($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode', $domElement),
                taxRate: $this->value($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent', $domElement),
                allowanceCharges: $this->lineAllowanceCharges($domxPath, $domElement),
                hasPeriod: $this->node($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod', $domElement) instanceof DOMElement,
                periodStart: $this->date($this->value($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString', $domElement)),
                periodEnd: $this->date($this->value($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString', $domElement)),
                grossPrice: $this->value($domxPath, 'ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:ChargeAmount', $domElement),
                itemStandardId: $this->value($domxPath, 'ram:SpecifiedTradeProduct/ram:GlobalID', $domElement),
                itemStandardIdScheme: $this->attribute($domxPath, 'ram:SpecifiedTradeProduct/ram:GlobalID', 'schemeID', $domElement),
                itemClassifications: $this->itemClassifications($domxPath, $domElement),
                attributes: $this->itemAttributes($domxPath, $domElement),
                originCountryCode: $this->value($domxPath, 'ram:SpecifiedTradeProduct/ram:OriginTradeCountry/ram:ID', $domElement),
            );
        }

        return $lines;
    }

    /**
     * @return list<LineAllowanceCharge>
     */
    private function lineAllowanceCharges(DOMXPath $domxPath, DOMElement $domElement): array
    {
        $result = [];

        foreach ($this->nodes($domxPath, 'ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge', $domElement) as $node) {
            $indicator = $this->value($domxPath, 'ram:ChargeIndicator/udt:Indicator', $node);

            $result[] = new LineAllowanceCharge(
                isCharge: $indicator === 'true' || $indicator === '1',
                amount: $this->value($domxPath, 'ram:ActualAmount', $node),
                reason: $this->value($domxPath, 'ram:Reason', $node),
                reasonCode: $this->value($domxPath, 'ram:ReasonCode', $node),
                baseAmount: $this->value($domxPath, 'ram:BasisAmount', $node),
            );
        }

        return $result;
    }

    /**
     * @return list<ItemClassification>
     */
    private function itemClassifications(DOMXPath $domxPath, DOMElement $domElement): array
    {
        $classifications = [];

        foreach ($this->nodes($domxPath, 'ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode', $domElement) as $node) {
            $classifications[] = new ItemClassification(
                code: $this->text($node),
                scheme: $node->hasAttribute('listID') ? $node->getAttribute('listID') : null,
            );
        }

        return $classifications;
    }

    /**
     * @return list<ItemAttribute>
     */
    private function itemAttributes(DOMXPath $domxPath, DOMElement $domElement): array
    {
        $attributes = [];

        foreach ($this->nodes($domxPath, 'ram:SpecifiedTradeProduct/ram:ApplicableProductCharacteristic', $domElement) as $node) {
            $attributes[] = new ItemAttribute(
                name: $this->value($domxPath, 'ram:Description', $node),
                value: $this->value($domxPath, 'ram:Value', $node),
            );
        }

        return $attributes;
    }

    /**
     * @return list<TaxSubtotal>
     */
    private function taxSubtotals(DOMXPath $domxPath): array
    {
        $subtotals = [];

        foreach ($this->nodes($domxPath, '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax') as $domElement) {
            $subtotals[] = new TaxSubtotal(
                category: $this->value($domxPath, 'ram:CategoryCode', $domElement),
                rate: $this->value($domxPath, 'ram:RateApplicablePercent', $domElement),
                taxableAmount: $this->value($domxPath, 'ram:BasisAmount', $domElement),
                taxAmount: $this->value($domxPath, 'ram:CalculatedAmount', $domElement),
                exemptionReason: $this->value($domxPath, 'ram:ExemptionReason', $domElement),
                exemptionReasonCode: $this->value($domxPath, 'ram:ExemptionReasonCode', $domElement),
            );
        }

        return $subtotals;
    }

    /**
     * @return list<string>
     */
    private function notes(DOMXPath $domxPath): array
    {
        $notes = [];

        foreach ($this->nodes($domxPath, '//rsm:ExchangedDocument/ram:IncludedNote/ram:Content') as $domElement) {
            $text = $this->text($domElement);
            if ($text !== null) {
                $notes[] = $text;
            }
        }

        return $notes;
    }

    /**
     * Normalize a CII date (format 102 = YYYYMMDD) to Y-m-d; pass anything else
     * through untouched.
     */
    private function date(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $matches) === 1) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        return $raw;
    }
}
