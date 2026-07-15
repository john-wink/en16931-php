<?php

declare(strict_types=1);

use JohnWink\En16931\En16931Validator;
use JohnWink\En16931\Tests\Support\KositRunner;

/**
 * Conformance harness: run each corpus invoice through BOTH our native, Java-free
 * validator AND the official KoSIT validator (the Java oracle) and prove they
 * agree. Skipped automatically when Java or the KoSIT tools are absent, so the
 * normal suite stays green everywhere; CI sets them up (see .github/workflows).
 */
function kositRunner(): KositRunner
{
    $runner = KositRunner::fromBuildDir();

    if (! KositRunner::javaAvailable() || $runner === null) {
        test()->markTestSkipped('KoSIT oracle not set up — run tools/kosit-setup.sh with a JRE present.');
    }

    return $runner;
}

function parityValidatorFor(string $xml): En16931Validator
{
    return str_contains($xml, ':xrechnung_')
        ? En16931Validator::xrechnung()
        : En16931Validator::en16931();
}

it('agrees with the KoSIT validator on real schema-valid invoices', function (string $path): void {
    $runner = kositRunner();
    $xml = (string) file_get_contents($path);

    $kosit = $runner->validate($xml);
    $ours = parityValidatorFor($xml)->validate($xml);

    // 1. Verdict parity (the corpus is schema-valid, so KoSIT's accept reflects
    //    the business rules — the layer we implement).
    expect($ours->isValid())->toBe($kosit['accept']);

    // 2. No false positives: every rule WE fire, KoSIT fired too. This is the
    //    core correctness guarantee for a subset validator.
    $ourCodes = array_values(array_unique(array_map(static fn ($violation): string => $violation->ruleId, $ours->violations)));
    expect(array_values(array_diff($ourCodes, $kosit['codes'])))->toBe([]);
})->with(fn (): array => glob(__DIR__.'/../Fixtures/corpus/*.xml') ?: []);

it('agrees with the KoSIT validator across the full official instance suite', function (): void {
    $runner = kositRunner();
    $instances = glob(dirname(__DIR__, 2).'/build/kosit/testsuite/instances/*/*.xml') ?: [];

    if ($instances === []) {
        test()->markTestSkipped('KoSIT test suite not downloaded — run tools/kosit-setup.sh.');
    }

    // One JVM invocation for the whole suite (UBL + CII, standard/extension/
    // technical-cases), then compare verdicts and fired codes per instance.
    $kositResults = $runner->validateBatch($instances);

    $verdictDiffs = [];
    $falsePositives = [];

    foreach ($instances as $instance) {
        $name = basename($instance);
        $kosit = $kositResults[$name] ?? null;

        if ($kosit === null) {
            continue;
        }

        $xml = (string) file_get_contents($instance);
        $ours = parityValidatorFor($xml)->validate($xml);

        if ($ours->isValid() !== $kosit['accept']) {
            $verdictDiffs[$name] = ['ours' => $ours->isValid(), 'kosit' => $kosit['accept']];
        }

        $ourCodes = array_values(array_unique(array_map(static fn ($violation): string => $violation->ruleId, $ours->violations)));
        $extra = array_values(array_diff($ourCodes, $kosit['codes']));

        if ($extra !== []) {
            $falsePositives[$name] = $extra;
        }
    }

    // Verdict parity AND no false positives on every real instance in both
    // syntaxes — the broad bidirectional conformance proof.
    expect($verdictDiffs)->toBe([])
        ->and($falsePositives)->toBe([]);
});

it('fatally rejects none of the official KoSIT XRechnung positive test suite', function (): void {
    $instances = glob(dirname(__DIR__, 2).'/build/kosit/testsuite/instances/*/*.xml') ?: [];

    if ($instances === []) {
        test()->markTestSkipped('KoSIT test suite not downloaded — run tools/kosit-setup.sh.');
    }

    // Every instance in the official suite is a valid XRechnung, so a subset
    // validator must never FATALLY reject one (warnings are allowed). This is a
    // broad false-positive proof over real instances, without needing Java.
    $rejected = [];
    foreach ($instances as $instance) {
        $xml = (string) file_get_contents($instance);
        $result = parityValidatorFor($xml)->validate($xml);

        if (! $result->isValid()) {
            $rejected[basename($instance)] = array_values(array_unique(array_map(static fn ($violation): string => $violation->ruleId, $result->fatals())));
        }
    }

    expect($rejected)->toBe([]);
});

it('fires the same rule as KoSIT for a targeted violation', function (string $ruleId, string $search, string $replace): void {
    $runner = kositRunner();
    $xml = (string) file_get_contents(__DIR__.'/../Fixtures/corpus/en16931-cii.xml');
    $tampered = str_replace($search, $replace, $xml);

    expect($tampered)->not->toBe($xml); // the mutation actually applied

    $kosit = $runner->validate($tampered);
    $ours = En16931Validator::en16931()->validate($tampered);

    // Both reject, and both fire the targeted rule — negative-direction parity.
    expect($kosit['accept'])->toBeFalse()
        ->and(in_array($ruleId, $kosit['codes'], true))->toBeTrue()
        ->and($ours->isValid())->toBeFalse()
        ->and($ours->hasViolation($ruleId))->toBeTrue();
})->with([
    'BR-CO-15 (grand total)' => ['BR-CO-15', '<ram:GrandTotalAmount>957.87', '<ram:GrandTotalAmount>9999.99'],
    'BR-CO-13 (tax basis)' => ['BR-CO-13', '<ram:TaxBasisTotalAmount>873.00', '<ram:TaxBasisTotalAmount>999.00'],
    'BR-CO-14 (total VAT)' => ['BR-CO-14', '<ram:TaxTotalAmount currencyID="EUR">84.87', '<ram:TaxTotalAmount currencyID="EUR">99.99'],
    'BR-27 (negative net price)' => ['BR-27', '<ram:ChargeAmount>9.90', '<ram:ChargeAmount>-9.90'],
]);
