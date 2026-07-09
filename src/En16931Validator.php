<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Reader\CiiInvoiceReader;

/**
 * The package entry point: validates an EN 16931 invoice (CII syntax) against
 * the EN 16931 core rules, optionally plus the German XRechnung CIUS.
 *
 * ```php
 * $result = En16931Validator::xrechnung()->validateCii($xml);
 * $result->isValid();      // bool (no fatal violation)
 * $result->violations;     // list<Violation>
 * ```
 */
final readonly class En16931Validator
{
    public function __construct(
        private CiiInvoiceReader $ciiInvoiceReader,
        private Validator $validator,
    ) {}

    /**
     * Validate against the EN 16931 core rule set only.
     */
    public static function en16931(): self
    {
        return new self(new CiiInvoiceReader, new Validator(RuleSets::en16931()));
    }

    /**
     * Validate against EN 16931 plus the German XRechnung CIUS (BR-DE-*).
     */
    public static function xrechnung(): self
    {
        return new self(
            new CiiInvoiceReader,
            new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()]),
        );
    }

    public function validateCii(string $xml): ValidationResult
    {
        return $this->validator->validate($this->ciiInvoiceReader->read($xml));
    }

    public function validateModel(Invoice $invoice): ValidationResult
    {
        return $this->validator->validate($invoice);
    }
}
