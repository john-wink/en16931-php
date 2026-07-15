<?php

declare(strict_types=1);

namespace JohnWink\En16931\Syntax;

use DOMDocument;
use JohnWink\En16931\Severity;
use JohnWink\En16931\Violation;

/**
 * Stage 1 of validation: the XSD schema check the KoSIT validator runs before
 * any Schematron. It proves the document is structurally a well-formed UBL 2.1
 * Invoice/CreditNote or a CII D16B CrossIndustryInvoice — the layer the business
 * and syntax rules take for granted. The official schemas are bundled under
 * resources/xsd so no download or Java is needed.
 */
final class SchemaValidator
{
    private const string XSD_DIR = __DIR__.'/../../resources/xsd';

    /**
     * Schema-validate the document for the given syntax ('ubl' | 'cii') and
     * return one violation per schema error (empty when it is schema-valid).
     *
     * @return list<Violation>
     */
    public function validate(DOMDocument $domDocument, string $syntax): array
    {
        $schema = $this->schemaFor($domDocument, $syntax);

        if ($schema === null) {
            return [new Violation('XSD', Severity::Fatal, 'Unknown document type — no matching XSD schema.', 'XSD')];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            if ($domDocument->schemaValidate($schema)) {
                return [];
            }

            $violations = [];

            foreach (libxml_get_errors() as $libXMLError) {
                $violations[] = new Violation(
                    'XSD',
                    Severity::Fatal,
                    'Schema violation: '.trim($libXMLError->message).' (line '.$libXMLError->line.')',
                    'XSD',
                );
            }

            return $violations !== [] ? $violations : [new Violation('XSD', Severity::Fatal, 'Document is not schema-valid.', 'XSD')];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function schemaFor(DOMDocument $domDocument, string $syntax): ?string
    {
        if ($syntax === 'cii') {
            return self::XSD_DIR.'/cii/CrossIndustryInvoice_100pD16B.xsd';
        }

        return match ($domDocument->documentElement?->localName) {
            'Invoice' => self::XSD_DIR.'/ubl/maindoc/UBL-Invoice-2.1.xsd',
            'CreditNote' => self::XSD_DIR.'/ubl/maindoc/UBL-CreditNote-2.1.xsd',
            default => null,
        };
    }
}
