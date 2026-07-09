<?php

declare(strict_types=1);

namespace JohnWink\En16931\Reader;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
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
    private const string RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const string RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const string UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    public function read(string $xml): Invoice
    {
        $xpath = $this->xpath($xml);

        $currency = $this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode');

        return new Invoice(
            number: $this->value($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID'),
            typeCode: $this->value($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'),
            issueDate: $this->date($this->value($xpath, '//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString')),
            currency: $currency,
            taxCurrency: $this->value($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode'),
            buyerReference: $this->value($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference'),
            customizationId: $this->value($xpath, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'),
            seller: $this->party($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty'),
            buyer: $this->party($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty'),
            totals: $this->totals($xpath, $currency),
            lines: $this->lines($xpath),
            taxSubtotals: $this->taxSubtotals($xpath),
            notes: $this->notes($xpath),
            allowanceCharges: $this->allowanceCharges($xpath),
        );
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
            contactName: $this->value($domxPath, 'ram:DefinedTradeContact/ram:PersonName', $node),
            contactPhone: $this->value($domxPath, 'ram:DefinedTradeContact/ram:TelephoneUniversalCommunication/ram:CompleteNumber', $node),
            contactEmail: $this->value($domxPath, 'ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID', $node),
        );
    }

    private function totals(DOMXPath $domxPath, ?string $currency): Totals
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
            );
        }

        return $lines;
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

    private function value(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): ?string
    {
        $node = $this->node($domxPath, $query, $domNode);

        if (! $node instanceof DOMElement) {
            return null;
        }

        return $this->text($node);
    }

    private function node(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): ?DOMElement
    {
        $list = $domNode instanceof DOMNode ? $domxPath->query($query, $domNode) : $domxPath->query($query);

        if ($list === false) {
            return null;
        }

        $node = $list->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * @return list<DOMElement>
     */
    private function nodes(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): array
    {
        $list = $domNode instanceof DOMNode ? $domxPath->query($query, $domNode) : $domxPath->query($query);

        if ($list === false) {
            return [];
        }

        $elements = [];
        foreach ($list as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function text(DOMElement $domElement): ?string
    {
        $text = trim($domElement->nodeValue ?? '');

        return $text === '' ? null : $text;
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
