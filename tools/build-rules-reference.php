#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds resources/rules-reference.json — the machine-readable list of every
 * official BR-* business rule this library measures itself against:
 *
 *  - EN 16931 validation artefacts (UBL preprocessed Schematron), and
 *  - the XRechnung CIUS Schematron (KoSIT), incl. BR-DEX extension rules.
 *
 * The JSON is consumed by tools/generate-coverage.php to render COVERAGE.md
 * and the README coverage summary, guarded by tests/Feature/CoverageDocTest.php.
 *
 * Usage:
 *   php tools/build-rules-reference.php [en16931.sch] [xrechnung.sch]
 *
 * Without arguments the pinned upstream files are downloaded.
 */
const EN16931_VERSION = '1.3.16';
const EN16931_URL = 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/validation-1.3.16/ubl/schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch';

const XRECHNUNG_VERSION = '2.5.0 (XRechnung 3.0.2)';
const XRECHNUNG_URL = 'https://raw.githubusercontent.com/itplr-kosit/xrechnung-schematron/v2.5.0/src/validation/schematron/ubl/XRechnung-UBL-validation.sch';

/**
 * @return list<array{id: string, set: string, flag: string, text: string}>
 */
function extract_rules(string $source, string $set): array
{
    $xml = file_get_contents($source);

    if ($xml === false || $xml === '') {
        fwrite(STDERR, "Could not read {$source}\n");
        exit(1);
    }

    $document = new DOMDocument;

    if (! $document->loadXML($xml)) {
        fwrite(STDERR, "Could not parse {$source} as XML\n");
        exit(1);
    }

    $rules = [];

    foreach ($document->getElementsByTagNameNS('*', 'assert') as $assert) {
        $id = $assert->getAttribute('id');

        if (! str_starts_with($id, 'BR-')) {
            continue;
        }

        if (isset($rules[$id])) {
            continue; // The same rule may be asserted in several contexts.
        }

        $text = trim((string) preg_replace('/[\s\p{Zs}]+/u', ' ', $assert->textContent));
        $text = (string) preg_replace('/^\['.preg_quote($id, '/').'\]\s*-?\s*/', '', $text);

        $rules[$id] = [
            'id' => $id,
            'set' => $set,
            'flag' => $assert->getAttribute('flag') !== '' ? $assert->getAttribute('flag') : 'fatal',
            'text' => $text,
        ];
    }

    return array_values($rules);
}

$en16931Source = $argv[1] ?? EN16931_URL;
$xrechnungSource = $argv[2] ?? XRECHNUNG_URL;

$rules = [
    ...extract_rules($en16931Source, 'en16931'),
    ...extract_rules($xrechnungSource, 'xrechnung'),
];

usort($rules, static function (array $a, array $b): int {
    return $a['set'] <=> $b['set'] ?: strnatcasecmp($a['id'], $b['id']);
});

$reference = [
    'generated_by' => 'tools/build-rules-reference.php',
    'sources' => [
        'en16931' => ['version' => EN16931_VERSION, 'url' => EN16931_URL],
        'xrechnung' => ['version' => XRECHNUNG_VERSION, 'url' => XRECHNUNG_URL],
    ],
    'rules' => $rules,
];

$target = dirname(__DIR__).'/resources/rules-reference.json';

if (! is_dir(dirname($target))) {
    mkdir(dirname($target), 0o755, true);
}

file_put_contents(
    $target,
    json_encode($reference, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

$en16931Count = count(array_filter($rules, static fn (array $rule): bool => $rule['set'] === 'en16931'));
echo 'Wrote '.count($rules)." rules ({$en16931Count} EN 16931, ".(count($rules) - $en16931Count)." XRechnung) to {$target}\n";
