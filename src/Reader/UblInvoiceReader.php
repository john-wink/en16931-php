<?php

declare(strict_types=1);

namespace JohnWink\En16931\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
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
        );
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
            );
        }

        return $lines;
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
