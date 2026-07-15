<?php

declare(strict_types=1);

use JohnWink\En16931\Syntax\SchematronEngine;

function engine(): SchematronEngine
{
    return SchematronEngine::fromBundledRules();
}

function syntaxCodes(string $xml, string $syntax): array
{
    $document = new DOMDocument;
    $document->loadXML($xml);

    return array_values(array_unique(array_map(
        static fn ($violation): string => $violation->ruleId,
        engine()->evaluate($document, $syntax),
    )));
}

it('reports no syntax violations on a clean UBL invoice', function (): void {
    $xml = (string) file_get_contents(dirname(__DIR__).'/Fixtures/valid-ubl.xml');

    // Whatever fires must be a real KoSIT rule; a clean invoice trips none of
    // the fatal cardinality rules. (Warnings, if any, are covered by parity.)
    expect(syntaxCodes($xml, 'ubl'))->toBe([]);
});

it('fires a fatal cardinality rule with a match-anywhere context (UBL-SR-33)', function (): void {
    // UBL-SR-33 (context cac:AdditionalDocumentReference, matched anywhere):
    // at most one DocumentDescription. Inject a reference with two → fires.
    $xml = (string) file_get_contents(dirname(__DIR__).'/Fixtures/valid-ubl.xml');
    $withDuplicate = str_replace(
        '<cac:PaymentMeans>',
        '<cac:AdditionalDocumentReference><cbc:ID>D1</cbc:ID>'
            .'<cbc:DocumentDescription>A</cbc:DocumentDescription>'
            .'<cbc:DocumentDescription>B</cbc:DocumentDescription>'
            .'</cac:AdditionalDocumentReference><cac:PaymentMeans>',
        $xml,
    );

    expect($withDuplicate)->not->toBe($xml)
        ->and(syntaxCodes($withDuplicate, 'ubl'))->toContain('UBL-SR-33');
});

it('evaluates a regex (matches) rule via the php function bridge (CII-DT-097)', function (): void {
    // CII-DT-097: a format="102" date must match YYYYMMDD. Break it → fires.
    $xml = (string) file_get_contents(dirname(__DIR__).'/Fixtures/valid-cii.xml');
    $badDate = str_replace('format="102">20260120', 'format="102">2026-01-20', $xml);

    expect($badDate)->not->toBe($xml)
        ->and(syntaxCodes($badDate, 'cii'))->toContain('CII-DT-097');
});

it('leaves the clean CII fixture free of fatal syntax violations', function (): void {
    $document = new DOMDocument;
    $document->loadXML((string) file_get_contents(dirname(__DIR__).'/Fixtures/valid-cii.xml'));

    $fatals = array_values(array_filter(
        engine()->evaluate($document, 'cii'),
        static fn ($violation): bool => $violation->severity === JohnWink\En16931\Severity::Fatal,
    ));

    expect($fatals)->toBe([]);
});
