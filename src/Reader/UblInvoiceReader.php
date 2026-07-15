<?php

declare(strict_types=1);

namespace JohnWink\En16931\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;
use RuntimeException;

/**
 * Builds the normalized {@see Invoice} model from a UBL document — the syntax
 * XRechnung-UBL and Peppol BIS use. Both the Invoice and the CreditNote root
 * are supported. Values are read as the raw XML text (exact decimal strings).
 */
final class UblInvoiceReader
{
    use HandlesXmlNodes;

    private const string CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const string CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    public function read(string $xml): Invoice
    {
        $xpath = $this->xpath($xml);
        $currency = $this->value($xpath, '/*/cbc:DocumentCurrencyCode');
        $taxTotal = $this->documentTaxTotal($xpath, $currency);

        return new Invoice(
            number: $this->value($xpath, '/*/cbc:ID'),
            typeCode: $this->value($xpath, '/*/cbc:InvoiceTypeCode | /*/cbc:CreditNoteTypeCode'),
            issueDate: $this->value($xpath, '/*/cbc:IssueDate'),
            currency: $currency,
            taxCurrency: $this->value($xpath, '/*/cbc:TaxCurrencyCode'),
            buyerReference: $this->value($xpath, '/*/cbc:BuyerReference'),
            customizationId: $this->value($xpath, '/*/cbc:CustomizationID'),
            seller: $this->party($xpath, '/*/cac:AccountingSupplierParty/cac:Party'),
            buyer: $this->party($xpath, '/*/cac:AccountingCustomerParty/cac:Party'),
            totals: $this->totals($xpath, $taxTotal),
            lines: $this->lines($xpath),
            taxSubtotals: $this->taxSubtotals($xpath, $taxTotal),
            notes: $this->notes($xpath),
            allowanceCharges: $this->allowanceCharges($xpath),
            paymentDueDate: $this->value($xpath, '/*/cbc:DueDate'),
            paymentTerms: $this->rawValue($xpath, '/*/cac:PaymentTerms/cbc:Note'),
            taxRepresentative: $this->taxRepresentative($xpath),
            paymentMeans: $this->paymentMeans($xpath),
            sepaCreditorId: $this->value($xpath, '/*/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID="SEPA"] | /*/cac:PayeeParty/cac:PartyIdentification/cbc:ID[@schemeID="SEPA"]'),
            hasInvoicingPeriod: $this->node($xpath, '/*/cac:InvoicePeriod') instanceof DOMElement,
            invoicingPeriodStart: $this->value($xpath, '/*/cac:InvoicePeriod/cbc:StartDate'),
            invoicingPeriodEnd: $this->value($xpath, '/*/cac:InvoicePeriod/cbc:EndDate'),
            taxPointDateCode: $this->value($xpath, '/*/cac:InvoicePeriod/cbc:DescriptionCode'),
            actualDeliveryDate: $this->value($xpath, '/*/cac:Delivery/cbc:ActualDeliveryDate'),
            deliverTo: $this->deliverTo($xpath),
        );
    }

    /**
     * The deliver-to address (BG-15) — null when the address group is absent.
     */
    private function deliverTo(DOMXPath $domxPath): ?Party
    {
        $address = $this->node($domxPath, '/*/cac:Delivery/cac:DeliveryLocation/cac:Address');

        if (! $address instanceof DOMElement) {
            return null;
        }

        return new Party(
            name: $this->value($domxPath, '/*/cac:Delivery/cac:DeliveryParty/cac:PartyName/cbc:Name'),
            countryCode: $this->value($domxPath, 'cac:Country/cbc:IdentificationCode', $address),
            street: $this->value($domxPath, 'cbc:StreetName', $address),
            city: $this->value($domxPath, 'cbc:CityName', $address),
            postCode: $this->value($domxPath, 'cbc:PostalZone', $address),
        );
    }

    /**
     * @return list<PaymentMeans>
     */
    private function paymentMeans(DOMXPath $domxPath): array
    {
        $result = [];

        foreach ($this->nodes($domxPath, '/*/cac:PaymentMeans') as $domElement) {
            $result[] = new PaymentMeans(
                typeCode: $this->value($domxPath, 'cbc:PaymentMeansCode', $domElement),
                accountId: $this->value($domxPath, 'cac:PayeeFinancialAccount/cbc:ID', $domElement),
                hasCreditTransfer: $this->node($domxPath, 'cac:PayeeFinancialAccount', $domElement) instanceof DOMElement,
                hasCardInformation: $this->node($domxPath, 'cac:CardAccount', $domElement) instanceof DOMElement,
                cardNumber: $this->value($domxPath, 'cac:CardAccount/cbc:PrimaryAccountNumberID', $domElement),
                hasDirectDebit: $this->node($domxPath, 'cac:PaymentMandate', $domElement) instanceof DOMElement,
                debitedAccountId: $this->value($domxPath, 'cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID', $domElement),
            );
        }

        return $result;
    }

    /**
     * The Seller tax representative party (BG-11) — null when the group is absent.
     */
    private function taxRepresentative(DOMXPath $domxPath): ?Party
    {
        return $this->node($domxPath, '/*/cac:TaxRepresentativeParty') instanceof DOMElement
            ? $this->party($domxPath, '/*/cac:TaxRepresentativeParty')
            : null;
    }

    private function xpath(string $xml): DOMXPath
    {
        $domDocument = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        try {
            if (! $domDocument->loadXML($xml)) {
                throw new RuntimeException('The UBL payload is not well-formed XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $domXPath = new DOMXPath($domDocument);
        $domXPath->registerNamespace('cbc', self::CBC);
        $domXPath->registerNamespace('cac', self::CAC);

        return $domXPath;
    }

    private function party(DOMXPath $domxPath, string $base): Party
    {
        $node = $this->node($domxPath, $base);
        if (! $node instanceof DOMElement) {
            return new Party;
        }

        return new Party(
            name: $this->value($domxPath, 'cac:PartyLegalEntity/cbc:RegistrationName', $node) ?? $this->value($domxPath, 'cac:PartyName/cbc:Name', $node),
            countryCode: $this->value($domxPath, 'cac:PostalAddress/cac:Country/cbc:IdentificationCode', $node),
            vatId: $this->value($domxPath, 'cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID', $node),
            identifier: $this->value($domxPath, 'cac:PartyIdentification/cbc:ID', $node),
            legalRegistrationId: $this->value($domxPath, 'cac:PartyLegalEntity/cbc:CompanyID', $node),
            contactName: $this->value($domxPath, 'cac:Contact/cbc:Name', $node),
            contactPhone: $this->value($domxPath, 'cac:Contact/cbc:Telephone', $node),
            contactEmail: $this->value($domxPath, 'cac:Contact/cbc:ElectronicMail', $node),
            taxRegistrationId: $this->value($domxPath, 'cac:PartyTaxScheme[cac:TaxScheme/cbc:ID!="VAT"]/cbc:CompanyID', $node),
            street: $this->value($domxPath, 'cac:PostalAddress/cbc:StreetName', $node),
            city: $this->value($domxPath, 'cac:PostalAddress/cbc:CityName', $node),
            postCode: $this->value($domxPath, 'cac:PostalAddress/cbc:PostalZone', $node),
            electronicAddress: $this->value($domxPath, 'cbc:EndpointID', $node),
            electronicAddressScheme: $this->attribute($domxPath, 'cbc:EndpointID', 'schemeID', $node),
        );
    }

    private function totals(DOMXPath $domxPath, ?DOMElement $domElement): Totals
    {
        $base = '/*/cac:LegalMonetaryTotal';

        return new Totals(
            lineTotal: $this->value($domxPath, "{$base}/cbc:LineExtensionAmount"),
            allowanceTotal: $this->value($domxPath, "{$base}/cbc:AllowanceTotalAmount"),
            chargeTotal: $this->value($domxPath, "{$base}/cbc:ChargeTotalAmount"),
            taxBasisTotal: $this->value($domxPath, "{$base}/cbc:TaxExclusiveAmount"),
            taxTotal: $domElement instanceof DOMElement ? $this->value($domxPath, 'cbc:TaxAmount', $domElement) : null,
            grandTotal: $this->value($domxPath, "{$base}/cbc:TaxInclusiveAmount"),
            paidAmount: $this->value($domxPath, "{$base}/cbc:PrepaidAmount"),
            roundingAmount: $this->value($domxPath, "{$base}/cbc:PayableRoundingAmount"),
            payableAmount: $this->value($domxPath, "{$base}/cbc:PayableAmount"),
        );
    }

    /**
     * The document-level cac:TaxTotal in the invoice currency (BT-110); a second
     * one in the tax currency (BT-111) may also be present, so match by @currencyID.
     */
    private function documentTaxTotal(DOMXPath $domxPath, ?string $currency): ?DOMElement
    {
        $fallback = null;

        foreach ($this->nodes($domxPath, '/*/cac:TaxTotal') as $domElement) {
            $fallback ??= $domElement;

            $amount = $this->node($domxPath, 'cbc:TaxAmount', $domElement);
            if ($amount instanceof DOMElement && $currency !== null && $amount->getAttribute('currencyID') === $currency) {
                return $domElement;
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

        foreach ($this->nodes($domxPath, '/*/cac:InvoiceLine | /*/cac:CreditNoteLine') as $domElement) {
            $quantityNode = $this->node($domxPath, 'cbc:InvoicedQuantity | cbc:CreditedQuantity', $domElement);

            $lines[] = new InvoiceLine(
                id: $this->value($domxPath, 'cbc:ID', $domElement),
                name: $this->value($domxPath, 'cac:Item/cbc:Name', $domElement),
                netAmount: $this->value($domxPath, 'cbc:LineExtensionAmount', $domElement),
                netPrice: $this->value($domxPath, 'cac:Price/cbc:PriceAmount', $domElement),
                quantity: $quantityNode instanceof DOMElement ? $this->text($quantityNode) : null,
                unitCode: $quantityNode instanceof DOMElement ? ($quantityNode->getAttribute('unitCode') ?: null) : null,
                taxCategory: $this->value($domxPath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:ID', $domElement),
                taxRate: $this->value($domxPath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', $domElement),
                allowanceCharges: $this->lineAllowanceCharges($domxPath, $domElement),
                hasPeriod: $this->node($domxPath, 'cac:InvoicePeriod', $domElement) instanceof DOMElement,
                periodStart: $this->value($domxPath, 'cac:InvoicePeriod/cbc:StartDate', $domElement),
                periodEnd: $this->value($domxPath, 'cac:InvoicePeriod/cbc:EndDate', $domElement),
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

        foreach ($this->nodes($domxPath, 'cac:AllowanceCharge', $domElement) as $node) {
            $indicator = $this->value($domxPath, 'cbc:ChargeIndicator', $node);

            $result[] = new LineAllowanceCharge(
                isCharge: $indicator === 'true' || $indicator === '1',
                amount: $this->value($domxPath, 'cbc:Amount', $node),
                reason: $this->value($domxPath, 'cbc:AllowanceChargeReason', $node),
                reasonCode: $this->value($domxPath, 'cbc:AllowanceChargeReasonCode', $node),
            );
        }

        return $result;
    }

    /**
     * @return list<TaxSubtotal>
     */
    private function taxSubtotals(DOMXPath $domxPath, ?DOMElement $domElement): array
    {
        if (! $domElement instanceof DOMElement) {
            return [];
        }

        $subtotals = [];

        foreach ($this->nodes($domxPath, 'cac:TaxSubtotal', $domElement) as $node) {
            $subtotals[] = new TaxSubtotal(
                category: $this->value($domxPath, 'cac:TaxCategory/cbc:ID', $node),
                rate: $this->value($domxPath, 'cac:TaxCategory/cbc:Percent', $node),
                taxableAmount: $this->value($domxPath, 'cbc:TaxableAmount', $node),
                taxAmount: $this->value($domxPath, 'cbc:TaxAmount', $node),
                exemptionReason: $this->value($domxPath, 'cac:TaxCategory/cbc:TaxExemptionReason', $node),
                exemptionReasonCode: $this->value($domxPath, 'cac:TaxCategory/cbc:TaxExemptionReasonCode', $node),
            );
        }

        return $subtotals;
    }

    /**
     * @return list<DocumentAllowanceCharge>
     */
    private function allowanceCharges(DOMXPath $domxPath): array
    {
        $result = [];

        foreach ($this->nodes($domxPath, '/*/cac:AllowanceCharge') as $domElement) {
            $indicator = $this->value($domxPath, 'cbc:ChargeIndicator', $domElement);

            $result[] = new DocumentAllowanceCharge(
                isCharge: $indicator === 'true' || $indicator === '1',
                amount: $this->value($domxPath, 'cbc:Amount', $domElement),
                taxCategory: $this->value($domxPath, 'cac:TaxCategory/cbc:ID', $domElement),
                taxRate: $this->value($domxPath, 'cac:TaxCategory/cbc:Percent', $domElement),
                reason: $this->value($domxPath, 'cbc:AllowanceChargeReason', $domElement),
                reasonCode: $this->value($domxPath, 'cbc:AllowanceChargeReasonCode', $domElement),
            );
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function notes(DOMXPath $domxPath): array
    {
        $notes = [];

        foreach ($this->nodes($domxPath, '/*/cbc:Note') as $domElement) {
            $text = $this->text($domElement);
            if ($text !== null) {
                $notes[] = $text;
            }
        }

        return $notes;
    }
}
