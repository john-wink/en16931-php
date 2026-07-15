<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use DOMDocument;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Reader\CiiInvoiceReader;
use JohnWink\En16931\Reader\UblInvoiceReader;
use JohnWink\En16931\Syntax\SchematronEngine;
use JohnWink\En16931\Syntax\SchemaValidator;

/**
 * The package entry point. Validating XML runs the full three-stage pipeline the
 * official KoSIT validator applies — XSD schema, then the syntax rules
 * (UBL-CR/SR, CII-SR/DT), then the EN 16931 (and optional XRechnung CIUS)
 * business rules — natively, without Java. Both CII and UBL syntax are
 * supported; {@see self::validate()} auto-detects which.
 *
 * ```php
 * $result = En16931Validator::xrechnung()->validate($xml);
 * $result->isValid();      // bool (no fatal violation across all three stages)
 * $result->violations;     // list<Violation>
 * ```
 *
 * {@see self::validateModel()} runs the business rules only — there is no XML to
 * schema- or syntax-check.
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
        private SchemaValidator $schemaValidator,
        private SchematronEngine $schematronEngine,
    ) {}

    /**
     * Validate against the EN 16931 core rule set only.
     */
    public static function en16931(): self
    {
        return self::build(new Validator(RuleSets::en16931()));
    }

    /**
     * Validate against EN 16931 plus the German XRechnung CIUS (BR-DE-*).
     */
    public static function xrechnung(): self
    {
        return self::build(new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()]));
    }

    private static function build(Validator $validator): self
    {
        return new self(
            new CiiInvoiceReader,
            new UblInvoiceReader,
            $validator,
            new SchemaValidator,
            SchematronEngine::fromBundledRules(),
        );
    }

    /**
     * Validate an e-invoice in either syntax; the CII vs. UBL syntax is detected
     * from the document's root namespace.
     */
    public function validate(string $xml): ValidationResult
    {
        return $this->runPipeline($xml, $this->isUbl($xml) ? 'ubl' : 'cii');
    }

    public function validateCii(string $xml): ValidationResult
    {
        return $this->runPipeline($xml, 'cii');
    }

    public function validateUbl(string $xml): ValidationResult
    {
        return $this->runPipeline($xml, 'ubl');
    }

    public function validateModel(Invoice $invoice): ValidationResult
    {
        return $this->validator->validate($invoice);
    }

    /**
     * The three-stage pipeline: XSD schema → syntax rules → business rules.
     * A payload that is not well-formed XML fails immediately.
     */
    private function runPipeline(string $xml, string $syntax): ValidationResult
    {
        $document = $this->parse($xml);

        if (! $document instanceof DOMDocument) {
            return new ValidationResult([new Violation('XML', Severity::Fatal, 'The payload is not well-formed XML.', 'XML')]);
        }

        $reader = $syntax === 'ubl' ? $this->ublInvoiceReader : $this->ciiInvoiceReader;

        return new ValidationResult([
            ...$this->schemaValidator->validate($document, $syntax),
            ...$this->schematronEngine->evaluate($document, $syntax),
            ...$this->validator->validate($reader->read($xml))->violations,
        ]);
    }

    private function parse(string $xml): ?DOMDocument
    {
        $domDocument = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            return $domDocument->loadXML($xml) ? $domDocument : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function isUbl(string $xml): bool
    {
        $document = $this->parse($xml);

        return in_array($document?->documentElement?->namespaceURI, self::UBL_NAMESPACES, true);
    }
}
