#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Regenerates COVERAGE.md and the README coverage summary from the rule sets
 * and resources/rules-reference.json. Kept in sync by CoverageDocTest.
 *
 * Usage: php tools/generate-coverage.php
 */

use JohnWink\En16931\Tools\CoverageMatrix;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);

file_put_contents($root.'/COVERAGE.md', CoverageMatrix::renderCoverage());

$readmePath = $root.'/README.md';
$readme = (string) file_get_contents($readmePath);
$pattern = '/'.preg_quote(CoverageMatrix::README_START, '/').'.*?'.preg_quote(CoverageMatrix::README_END, '/').'/s';

if (preg_match($pattern, $readme) !== 1) {
    fwrite(STDERR, "README.md is missing the coverage markers — add them once, then re-run.\n");
    exit(1);
}

file_put_contents(
    $readmePath,
    (string) preg_replace_callback($pattern, static fn (): string => CoverageMatrix::readmeBlock(), $readme),
);

echo "Wrote COVERAGE.md and updated the README coverage summary.\n";
