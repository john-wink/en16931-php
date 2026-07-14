<?php

declare(strict_types=1);

use JohnWink\En16931\Tools\CoverageMatrix;

/*
 * Drift guard: COVERAGE.md and the README coverage summary are generated from
 * the rule sets + resources/rules-reference.json. Whenever a rule is added or
 * changed, run `php tools/generate-coverage.php` — these tests fail otherwise.
 */

it('keeps COVERAGE.md in sync with the registered rule sets', function (): void {
    $path = dirname(__DIR__, 2).'/COVERAGE.md';

    expect(file_exists($path))->toBeTrue('COVERAGE.md is missing — run: php tools/generate-coverage.php');
    expect(file_get_contents($path))->toBe(CoverageMatrix::renderCoverage(), 'COVERAGE.md is stale — run: php tools/generate-coverage.php');
});

it('keeps the README coverage summary in sync with the registered rule sets', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');

    expect($readme)->toContain(CoverageMatrix::readmeBlock());
});
