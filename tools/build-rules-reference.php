#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Builds resources/rules-reference.json — the machine-readable list of every
 * business rule the library measures itself against.
 *
 * The reference is the **KoSIT validator configuration** (validator-configuration-
 * xrechnung 3.0.2), i.e. the compiled Schematron the official validator actually
 * runs — the legal conformance target for XRechnung in Germany and the very
 * oracle the parity test compares against. It is NOT the raw schematron source
 * repositories: those drift from the shipped configuration (e.g. the IGIC/IPSI
 * rules are BR-IG/BR-IP in the configuration, and BR-DE-TMP-32 is not shipped).
 *
 * Each rule's effective severity is the flag from the compiled assert, lowered
 * by any customLevel override in scenarios.xml.
 *
 * The JSON is consumed by tools/generate-coverage.php to render COVERAGE.md and
 * the README summary, guarded by tests/Feature/CoverageDocTest.php.
 *
 * Usage:  php tools/build-rules-reference.php [config-dir]
 * Default config-dir: build/kosit/config (populated by tools/kosit-setup.sh).
 */
const CONFIG_VERSION = 'validator-configuration-xrechnung 3.0.2 (release 2025-03-21)';

/**
 * The lowered severities declared per scenario in scenarios.xml — anything not
 * "error"/"fatal" means the rule does not reject.
 *
 * @return array<string, string>
 */
function custom_levels(string $configDir): array
{
    $xml = (string) @file_get_contents($configDir.'/scenarios.xml');
    $levels = [];

    if (preg_match_all('/<customLevel\s+level="([a-z]+)">([A-Za-z0-9-]+)<\/customLevel>/', $xml, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as [, $level, $id]) {
            // Keep the least severe declaration when a rule appears twice.
            if (! isset($levels[$id]) || severity_rank($level) < severity_rank($levels[$id])) {
                $levels[$id] = $level;
            }
        }
    }

    return $levels;
}

function severity_rank(string $level): int
{
    return match ($level) {
        'information', 'info' => 0,
        'warning' => 1,
        default => 2, // error / fatal
    };
}

/**
 * Extract every compiled BR-* assert from one KoSIT validation XSL.
 *
 * @param  array<string, string>  $customLevels
 * @return array<string, array{id: string, set: string, flag: string, text: string}>
 */
function extract_rules(string $file, string $set, array $customLevels): array
{
    $xsl = (string) file_get_contents($file);
    $rules = [];

    if (preg_match_all('/<svrl:failed-assert\b.*?<\/svrl:failed-assert>/s', $xsl, $blocks) === 0) {
        return $rules;
    }

    foreach ($blocks[0] as $block) {
        if (preg_match('/name="id">([^<]+)</', $block, $idMatch) !== 1) {
            continue;
        }

        $id = $idMatch[1];

        // The KoSIT build is internally inconsistent: the UBL profile renamed
        // IGIC/IPSI to BR-IG/BR-IP while the CII profile still emits the old
        // BR-AF/BR-AG. The rules are character-for-character identical, so we
        // collapse them onto the newer canonical UBL naming.
        $id = (string) preg_replace(['/^BR-AF-/', '/^BR-AG-/'], ['BR-IG-', 'BR-IP-'], $id);

        if (! str_starts_with($id, 'BR-')) {
            continue;
        }

        if (isset($rules[$id])) {
            continue;
        }

        $flag = preg_match('/name="flag">([^<]+)</', $block, $flagMatch) === 1 ? $flagMatch[1] : 'fatal';

        if (isset($customLevels[$id])) {
            $flag = $customLevels[$id];
        }

        $text = '';

        if (preg_match('/<svrl:text>(.*?)<\/svrl:text>/s', $block, $textMatch) === 1) {
            $text = trim((string) preg_replace('/[\s\p{Zs}]+/u', ' ', strip_tags($textMatch[1])));
            $text = (string) preg_replace('/^\['.preg_quote($id, '/').'\]\s*-?\s*/', '', $text);
        }

        $rules[$id] = [
            'id' => $id,
            'set' => $set,
            'flag' => $flag === 'error' ? 'fatal' : $flag,
            'text' => $text,
        ];
    }

    return $rules;
}

$configDir = $argv[1] ?? dirname(__DIR__).'/build/kosit/config';

if (! is_file($configDir.'/scenarios.xml')) {
    fwrite(STDERR, "KoSIT config not found at {$configDir} — run tools/kosit-setup.sh (needs a JRE) first.\n");
    exit(1);
}

$resources = $configDir.'/resources';
$customLevels = custom_levels($configDir);

$find = static function (string $name) use ($resources): ?string {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resources, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getFilename() === $name) {
            return $file->getPathname();
        }
    }

    return null;
};

// EN 16931 rules render into both syntaxes identically; prefer the UBL file and
// fill any CII-only rules from the CII file. XRechnung likewise.
$rules = [];

foreach ([
    ['EN16931-UBL-validation.xsl', 'en16931'],
    ['EN16931-CII-validation.xsl', 'en16931'],
    ['XRechnung-UBL-validation.xsl', 'xrechnung'],
    ['XRechnung-CII-validation.xsl', 'xrechnung'],
] as [$name, $set]) {
    $file = $find($name);

    if ($file === null) {
        fwrite(STDERR, "Missing {$name} under {$resources}\n");
        exit(1);
    }

    foreach (extract_rules($file, $set, $customLevels) as $id => $rule) {
        $rules[$id] ??= $rule;
    }
}

$rules = array_values($rules);

usort($rules, static function (array $a, array $b): int {
    return $a['set'] <=> $b['set'] ?: strnatcasecmp($a['id'], $b['id']);
});

$reference = [
    'generated_by' => 'tools/build-rules-reference.php',
    'source' => CONFIG_VERSION,
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
