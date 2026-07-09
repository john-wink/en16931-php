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

it('agrees with KoSIT that a tampered total is rejected (BR-CO-15)', function (): void {
    $runner = kositRunner();
    $xml = (string) file_get_contents(__DIR__.'/../Fixtures/corpus/en16931-cii.xml');
    $tampered = (string) preg_replace('/(<ram:GrandTotalAmount>)[0-9.]+/', '${1}9999.99', $xml, 1);

    $kosit = $runner->validate($tampered);
    $ours = En16931Validator::en16931()->validate($tampered);

    expect($kosit['accept'])->toBeFalse()
        ->and($ours->isValid())->toBeFalse()
        ->and($ours->hasViolation('BR-CO-15'))->toBeTrue()
        ->and(in_array('BR-CO-15', $kosit['codes'], true))->toBeTrue();
});
