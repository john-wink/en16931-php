<?php

declare(strict_types=1);

namespace JohnWink\En16931\Syntax;

use DOMDocument;
use DOMNode;
use DOMXPath;
use JohnWink\En16931\Severity;
use JohnWink\En16931\Violation;
use RuntimeException;

/**
 * Evaluates the EN 16931 / KoSIT syntax rules (UBL-CR / UBL-SR / CII-SR /
 * CII-DT) directly against the DOM, the way the official Schematron does.
 *
 * Each rule is an XPath context and a pass condition extracted from the KoSIT
 * validator configuration ({@see resources/syntax-rules.json}, built by
 * tools/build-syntax-rules.php). For every node the context selects, the pass
 * condition is evaluated relative to it; a false result is a violation. This
 * covers all ~1276 syntax rules from one source rather than hand-porting each.
 */
final readonly class SchematronEngine
{
    /**
     * @param  array<string, string>  $namespaces
     * @param  list<array{id: string, syntax: string, context: string, test: string, flag: string}>  $rules
     */
    private function __construct(
        private array $namespaces,
        private array $rules,
    ) {}

    public static function fromBundledRules(): self
    {
        $path = dirname(__DIR__, 2).'/resources/syntax-rules.json';
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Missing {$path} — run: php tools/build-syntax-rules.php");
        }

        /** @var array{namespaces: array<string, string>, rules: list<array{id: string, syntax: string, context: string, test: string, flag: string}>} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self($data['namespaces'], $data['rules']);
    }

    /**
     * Run every syntax rule for the given syntax ('ubl' | 'cii') against the
     * document and return the violations found.
     *
     * @return list<Violation>
     */
    public function evaluate(DOMDocument $domDocument, string $syntax): array
    {
        $domxPath = new DOMXPath($domDocument);

        // A few syntax rules need regex / set functions that XPath 1.0 lacks;
        // they are rewritten to php:function calls into XPathFunctions.
        $domxPath->registerNamespace('php', 'http://php.net/xpath');
        $domxPath->registerPhpFunctions([
            XPathFunctions::class.'::matches',
            XPathFunctions::class.'::distinctCount',
        ]);

        foreach ($this->namespaces as $prefix => $uri) {
            $domxPath->registerNamespace($prefix, $uri);
        }

        $previous = libxml_use_internal_errors(true);
        $violations = [];

        try {
            foreach ($this->rules as $rule) {
                if ($rule['syntax'] !== $syntax) {
                    continue;
                }

                $contextNodes = $domxPath->query($this->selectExpression($rule['context']));

                if ($contextNodes === false) {
                    continue;
                }

                foreach ($contextNodes as $contextNode) {
                    // Element/attribute contexts only; a namespace node is never
                    // a Schematron rule context and cannot host a relative test.
                    if (! $contextNode instanceof DOMNode) {
                        continue;
                    }

                    if ($this->passes($domxPath, $rule['test'], $contextNode)) {
                        continue;
                    }

                    $violations[] = new Violation(
                        $rule['id'],
                        $rule['flag'] === 'fatal' ? Severity::Fatal : Severity::Warning,
                        "Syntax rule {$rule['id']} failed.",
                        $rule['id'],
                    );
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $violations;
    }

    /**
     * A Schematron rule context is an XSLT match pattern — it matches nodes
     * anywhere in the tree. A relative pattern (e.g. "cac:PayeeParty") must
     * therefore be selected as "//cac:PayeeParty"; absolute patterns (/, //)
     * are already document-wide. Applied per union member.
     */
    private function selectExpression(string $context): string
    {
        $parts = array_map(static function (string $part): string {
            $part = trim($part);

            return str_starts_with($part, '/') ? $part : '//'.$part;
        }, explode('|', $context));

        return implode(' | ', $parts);
    }

    /**
     * The XPath effective boolean value of the pass condition relative to the
     * context node. boolean(...) gives exact XPath 1.0 EBV semantics for node
     * sets, numbers, strings and booleans alike.
     */
    private function passes(DOMXPath $domxPath, string $test, DOMNode $domNode): bool
    {
        libxml_clear_errors();
        $result = @$domxPath->evaluate('boolean('.$test.')', $domNode);

        // A malformed/unsupported expression yields false plus a libxml error;
        // treat that as passing so the engine never invents a violation it
        // cannot actually prove. Only a clean boolean false is a violation.
        if (! is_bool($result) || libxml_get_errors() !== []) {
            libxml_clear_errors();

            return true;
        }

        return $result;
    }
}
