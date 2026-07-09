<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use DOMDocument;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Reader\CiiInvoiceReader;
use JohnWink\En16931\Reader\UblInvoiceReader;

/**
 * The package entry point: validates an EN 16931 invoice against the EN 16931
 * core rules, optionally plus the German XRechnung CIUS. Both CII and UBL syntax
 * are supported natively; {@see self::validate()} auto-detects which.
 *
 * ```php
 * $result = En16931Validator::xrechnung()->validate($xml);
 * $result->isValid();      // bool (no fatal violation)
 * $result->violations;     // list<Violation>
 * ```
 */
final readonly class En16931Validator
{
    /**
     * @var list<string>
     */
    private const array UBL_NAMESPACES = [
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
    ];

    public function __construct(
        private CiiInvoiceReader $ciiInvoiceReader,
        private UblInvoiceReader $ublInvoiceReader,
        private Validator $validator,
    ) {}

    /**
     * Validate against the EN 16931 core rule set only.
     */
    public static function en16931(): self
    {
        return new self(new CiiInvoiceReader, new UblInvoiceReader, new Validator(RuleSets::en16931()));
    }

    /**
     * Validate against EN 16931 plus the German XRechnung CIUS (BR-DE-*).
     */
    public static function xrechnung(): self
    {
        return new self(
            new CiiInvoiceReader,
            new UblInvoiceReader,
            new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()]),
        );
    }

    /**
     * Validate an e-invoice in either syntax; the CII vs. UBL syntax is detected
     * from the document's root namespace.
     */
    public function validate(string $xml): ValidationResult
    {
        return $this->validator->validate(
            $this->isUbl($xml) ? $this->ublInvoiceReader->read($xml) : $this->ciiInvoiceReader->read($xml),
        );
    }

    public function validateCii(string $xml): ValidationResult
    {
        return $this->validator->validate($this->ciiInvoiceReader->read($xml));
    }

    public function validateUbl(string $xml): ValidationResult
    {
        return $this->validator->validate($this->ublInvoiceReader->read($xml));
    }

    public function validateModel(Invoice $invoice): ValidationResult
    {
        return $this->validator->validate($invoice);
    }

    private function isUbl(string $xml): bool
    {
        $domDocument = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        try {
            if (! $domDocument->loadXML($xml)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return in_array($domDocument->documentElement?->namespaceURI, self::UBL_NAMESPACES, true);
    }
}
