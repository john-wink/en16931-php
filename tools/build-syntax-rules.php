#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds resources/syntax-rules.json — the UBL-CR / UBL-SR / CII-SR syntax
 * rules the KoSIT validator configuration enforces on top of the XSD schema and
 * the EN 16931 business rules.
 *
 * These rules are plain XPath assertions in the compiled Schematron; the
 * generic {@see JohnWink\En16931\Syntax\SchematronEngine} evaluates them
 * against the DOM. We extract each rule's context (the enclosing
 * <xsl:template match="…">), its pass condition (the failed-assert @test) and
 * its severity, keyed by syntax (ubl / cii).
 *
 * The one XPath 2.0 function in use, upper-case(), is rewritten to the XPath 1.0
 * translate() equivalent so PHP's DOMXPath can evaluate it.
 *
 * Usage:  php tools/build-syntax-rules.php [config-dir]
 * Default config-dir: build/kosit/config (populated by tools/kosit-setup.sh).
 */
const SYNTAX_PREFIXES = ['UBL-CR-', 'UBL-SR-', 'CII-SR-', 'CII-DT-'];

const LOWER = 'abcdefghijklmnopqrstuvwxyz';
const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const FN_NS = 'JohnWink\\En16931\\Syntax\\XPathFunctions';

/**
 * Rewrite the few XPath 2.0 constructs the KoSIT syntax rules use into a form
 * PHP's DOMXPath (XPath 1.0 + registered php: functions) can evaluate.
 */
function rewrite_xpath2(string $test): string
{
    // Element/attribute compared to the boolean literal true()/false(): XPath 2.0
    // atomizes the untyped content ("true"/"false") and casts, so it matches the
    // string; XPath 1.0 would convert the node set to a boolean (existence). Use
    // the string comparison to reproduce the XPath 2.0 untyped semantics.
    $test = (string) preg_replace(
        ['/(!?=)\s*true\(\)/', '/(!?=)\s*false\(\)/'],
        ["$1 'true'", "$1 'false'"],
        $test,
    );

    // count(distinct-values(EXPR)) → php:function(...::distinctCount, EXPR)
    $test = (string) preg_replace_callback(
        '/count\(\s*distinct-values\(\s*(.+?)\s*\)\s*\)/',
        static fn (array $m): string => "php:function('".FN_NS."::distinctCount', ".$m[1].')',
        $test,
    );

    // matches(ARG, 'PATTERN') → php:function(...::matches, string(ARG), 'PATTERN')
    $test = (string) preg_replace_callback(
        "/matches\(\s*(.+?)\s*,\s*('(?:[^']|'')*')\s*\)/",
        static fn (array $m): string => "php:function('".FN_NS."::matches', string(".$m[1].'), '.$m[2].')',
        $test,
    );

    // PATH/upper-case(LEAF) → translate(PATH/LEAF, 'a…z','A…Z'); upper-case(X) → translate(X,…).
    // Folding the preceding location path into translate's argument keeps it
    // valid XPath 1.0 (a bare function step is not).
    $test = (string) preg_replace_callback(
        '/((?:[\w:.\-]+\/)*)upper-case\(\s*([^()]+?)\s*\)/',
        static fn (array $m): string => "translate({$m[1]}{$m[2]},'".LOWER."','".UPPER."')",
        $test,
    );

    return $test;
}

/**
 * @return array{rules: list<array{id: string, syntax: string, context: string, test: string, flag: string}>, namespaces: array<string, string>}
 */
function extract_syntax_rules(string $file, string $syntax): array
{
    $xsl = (string) file_get_contents($file);

    // Namespaces declared on any element (the compiled XSL repeats them on the
    // svrl elements); collect every xmlns:prefix="uri" so the engine can bind
    // exactly what the rule contexts and tests reference.
    $namespaces = [];

    if (preg_match_all('/xmlns:([A-Za-z0-9]+)="([^"]+)"/', $xsl, $nsMatches, PREG_SET_ORDER) > 0) {
        foreach ($nsMatches as [, $prefix, $uri]) {
            $namespaces[$prefix] = $uri;
        }
    }

    $rules = [];

    // Walk template boundaries so each assert is tied to its context (the
    // nearest preceding <xsl:template match="…" … mode="M…">).
    if (preg_match_all('/<xsl:template\s+match="([^"]*)"[^>]*mode="M\d+">/', $xsl, $templateMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
        return ['rules' => [], 'namespaces' => $namespaces];
    }

    $templates = array_map(static fn (array $m): array => ['context' => html_entity_decode($m[1][0]), 'offset' => $m[0][1]], $templateMatches);

    if (preg_match_all('/<svrl:failed-assert\b.*?<\/svrl:failed-assert>/s', $xsl, $assertBlocks, PREG_OFFSET_CAPTURE) === 0) {
        return ['rules' => [], 'namespaces' => $namespaces];
    }

    foreach ($assertBlocks[0] as [$block, $offset]) {
        if (preg_match('/name="id">([^<]+)</', $block, $idMatch) !== 1) {
            continue;
        }

        $id = $idMatch[1];

        if (! str_starts_with_any($id, SYNTAX_PREFIXES)) {
            continue; // Business rules (BR-*) are implemented natively.
        }

        if (preg_match('/<svrl:failed-assert[^>]*\btest="([^"]*)"/', $block, $testMatch) !== 1) {
            continue;
        }

        // The context is the match of the last template opened before this assert.
        $context = '/';

        foreach ($templates as $template) {
            if ($template['offset'] < $offset) {
                $context = $template['context'];
            } else {
                break;
            }
        }

        $flag = preg_match('/name="flag">([^<]+)</', $block, $flagMatch) === 1 ? $flagMatch[1] : 'fatal';

        $rules[] = [
            'id' => $id,
            'syntax' => $syntax,
            'context' => $context,
            'test' => rewrite_xpath2(html_entity_decode($testMatch[1])),
            'flag' => $flag === 'error' ? 'fatal' : $flag,
        ];
    }

    return ['rules' => $rules, 'namespaces' => $namespaces];
}

function str_starts_with_any(string $haystack, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($haystack, $prefix)) {
            return true;
        }
    }

    return false;
}

$configDir = $argv[1] ?? dirname(__DIR__).'/build/kosit/config';

if (! is_dir($configDir.'/resources')) {
    fwrite(STDERR, "KoSIT config not found at {$configDir} — run tools/kosit-setup.sh (needs a JRE) first.\n");
    exit(1);
}

$resources = $configDir.'/resources';

$find = static function (string $name) use ($resources): ?string {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resources, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getFilename() === $name) {
            return $file->getPathname();
        }
    }

    return null;
};

$rules = [];
$namespaces = [];
$seen = [];

foreach ([
    ['EN16931-UBL-validation.xsl', 'ubl'],
    ['EN16931-CII-validation.xsl', 'cii'],
    ['XRechnung-UBL-validation.xsl', 'ubl'],
    ['XRechnung-CII-validation.xsl', 'cii'],
] as [$name, $syntax]) {
    $file = $find($name);

    if ($file === null) {
        continue;
    }

    $extracted = extract_syntax_rules($file, $syntax);
    $namespaces = [...$namespaces, ...$extracted['namespaces']];

    foreach ($extracted['rules'] as $rule) {
        $key = $rule['syntax'].'|'.$rule['id'];

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $rules[] = $rule;
    }
}

usort($rules, static function (array $a, array $b): int {
    return $a['syntax'] <=> $b['syntax'] ?: strnatcasecmp($a['id'], $b['id']);
});

// Only namespaces actually referenced by contexts/tests are worth shipping.
$used = [];

foreach ($rules as $rule) {
    if (preg_match_all('/([A-Za-z][A-Za-z0-9]*):/', $rule['context'].' '.$rule['test'], $prefixMatches) > 0) {
        foreach ($prefixMatches[1] as $prefix) {
            $used[$prefix] = true;
        }
    }
}

$axes = ['ancestor' => true, 'ancestor-or-self' => true, 'descendant' => true, 'descendant-or-self' => true, 'following' => true, 'following-sibling' => true, 'preceding' => true, 'preceding-sibling' => true, 'self' => true, 'parent' => true, 'child' => true, 'attribute' => true, 'namespace' => true, 'xs' => true];
$namespaces = array_filter($namespaces, static fn (string $prefix): bool => isset($used[$prefix]) && ! isset($axes[$prefix]), ARRAY_FILTER_USE_KEY);
ksort($namespaces);

$reference = [
    'generated_by' => 'tools/build-syntax-rules.php',
    'source' => 'validator-configuration-xrechnung 3.0.2 (release 2025-03-21)',
    'namespaces' => $namespaces,
    'rules' => $rules,
];

$target = dirname(__DIR__).'/resources/syntax-rules.json';
file_put_contents($target, json_encode($reference, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

$ubl = count(array_filter($rules, static fn (array $r): bool => $r['syntax'] === 'ubl'));
echo 'Wrote '.count($rules)." syntax rules ({$ubl} UBL, ".(count($rules) - $ubl)." CII) to {$target}\n";
