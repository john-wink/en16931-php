<?php

declare(strict_types=1);

use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\CustomerRecord;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\ItemAttribute;
use JohnWink\En16931\Model\ItemClassification;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;
use JohnWink\En16931\ModelEncoding;
use JohnWink\En16931\Reader\CiiInvoiceReader;
use JohnWink\En16931\Reader\UblInvoiceReader;
use JohnWink\En16931\RuleSets;
use JohnWink\En16931\Severity;
use JohnWink\En16931\ValidationResult;
use JohnWink\En16931\Validator;
use JohnWink\En16931\Violation;

$underEncoding = static function (string $encoding, Closure $callback): mixed {
    $previous = mb_internal_encoding();
    mb_internal_encoding($encoding);

    try {
        return $callback();
    } finally {
        mb_internal_encoding($previous);
    }
};

$fixture = static fn (string $name): string => (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);

$cvdInvoice = static fn (string $vehicleCategory, string $cvaValue): Invoice => makeInvoice(
    customizationId: 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9',
    contractReference: 'C-1',
    tenderReference: 'T-1',
    lines: [new InvoiceLine(
        id: '1', name: 'Fahrzeug', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62',
        taxCategory: 'S', taxRate: '19.00',
        itemClassifications: [new ItemClassification(code: $vehicleCategory, scheme: 'CVD')],
        attributes: [new ItemAttribute(name: 'cva', value: $cvaValue)],
    )],
);

$encodings = [
    'UTF-8' => ['UTF-8'],
    'ISO-8859-1' => ['ISO-8859-1'],
];

$syntaxes = [
    'UBL' => [static fn (): UblInvoiceReader => new UblInvoiceReader, 'valid-ubl.xml', '<cbc:CompanyID>DE123456789</cbc:CompanyID>', '<cbc:CompanyID>%s</cbc:CompanyID>', '<cbc:RegistrationName>Seller GmbH</cbc:RegistrationName>', '<cbc:RegistrationName>%s</cbc:RegistrationName>'],
    'CII' => [static fn (): CiiInvoiceReader => new CiiInvoiceReader, 'valid-cii.xml', '<ram:ID schemeID="VA">DE123456789</ram:ID>', '<ram:ID schemeID="VA">%s</ram:ID>', '<ram:Name>Seller GmbH</ram:Name>', '<ram:Name>%s</ram:Name>'],
];

it('reads a seller VAT identifier of only U+00A0 as missing whatever the internal encoding is', function (string $encoding, Closure $reader, string $file, string $vatId, string $vatIdTemplate) use ($underEncoding, $fixture): void {
    $xml = str_replace($vatId, sprintf($vatIdTemplate, "\u{00A0}"), $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->seller->vatId)->toBeNull()
        ->and($invoice->seller->hasVatId())->toBeFalse();
})->with($encodings)->with($syntaxes);

it('reads a seller name framed by U+00A0 without the frame whatever the internal encoding is', function (string $encoding, Closure $reader, string $file, string $vatId, string $vatIdTemplate, string $name, string $nameTemplate) use ($underEncoding, $fixture): void {
    $xml = str_replace($name, sprintf($nameTemplate, "\u{00A0}Seller GmbH\u{00A0}"), $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->seller->name)->toBe('Seller GmbH');
})->with($encodings)->with($syntaxes);

it('reads a currencyID framed by U+00A0 without the frame whatever the internal encoding is', function (string $encoding, Closure $reader, string $file) use ($underEncoding, $fixture): void {
    $xml = str_replace('currencyID="EUR"', "currencyID=\"\u{00A0}EUR\u{00A0}\"", $fixture($file));

    $invoice = $underEncoding($encoding, static fn (): Invoice => $reader()->read($xml));

    expect($invoice->amountCurrencyCodes)->toBe(['EUR']);
})->with($encodings)->with($syntaxes);

it('trims rule inputs framed by U+00A0 as UTF-8 whatever the internal encoding is', function (string $encoding, Closure $scenario, string $ruleId, bool $violates) use ($underEncoding): void {
    $hasViolation = $underEncoding($encoding, static fn (): bool => xr()->validateModel($scenario())->hasViolation($ruleId));

    expect($hasViolation)->toBe($violates);
})->with($encodings)->with([
    'credit transfer code without an account (BR-61)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}30\u{00A0}")]), 'BR-61', true],
    'SEPA credit transfer code without an account (BR-50)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}58\u{00A0}", hasCreditTransfer: true)]), 'BR-50', true],
    'SEPA credit transfer code with an account that is no IBAN (BR-DE-19)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}58\u{00A0}", accountId: 'fixture-account-not-iban', hasCreditTransfer: true)]), 'BR-DE-19', true],
    'SEPA direct debit code with a debited account that is no IBAN (BR-DE-20)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}59\u{00A0}", hasDirectDebit: true, debitedAccountId: 'fixture-account-not-iban')]), 'BR-DE-20', true],
    'credit transfer code without a credit transfer group (BR-DE-23-a)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}30\u{00A0}")]), 'BR-DE-23-a', true],
    'credit transfer code with a card group (BR-DE-23-b)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}30\u{00A0}", hasCreditTransfer: true, hasCardInformation: true)]), 'BR-DE-23-b', true],
    'card payment code without a card group (BR-DE-24-a)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}48\u{00A0}")]), 'BR-DE-24-a', true],
    'card payment code with a direct debit group (BR-DE-24-b)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}48\u{00A0}", hasCardInformation: true, hasDirectDebit: true)]), 'BR-DE-24-b', true],
    'direct debit code without a direct debit group (BR-DE-25-a)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}59\u{00A0}")]), 'BR-DE-25-a', true],
    'direct debit code with a credit transfer group (BR-DE-25-b)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: "\u{00A0}59\u{00A0}", hasCreditTransfer: true, hasDirectDebit: true)]), 'BR-DE-25-b', true],
    'CVD vehicle category (BR-DE-CVD-04)' => [static fn (): Invoice => $cvdInvoice("\u{00A0}M1\u{00A0}", 'clean'), 'BR-DE-CVD-04', false],
    'cva attribute value (BR-DE-CVD-05)' => [static fn (): Invoice => $cvdInvoice('M1', "\u{00A0}clean\u{00A0}"), 'BR-DE-CVD-05', false],
    'seller VAT identifier with a country prefix (BR-CO-09)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: "\u{00A0}DE123456789\u{00A0}", contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'BR-CO-09', false],
    'card number of ten digits (BR-51)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: "\u{00A0}1234567890\u{00A0}")]), 'BR-51', false],
    'VATEX code (BR-CL-22)' => [static fn (): Invoice => makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00', exemptionReasonCode: "\u{00A0}VATEX-EU-O\u{00A0}")]), 'BR-CL-22', false],
    'external document location (BR-TMP-2)' => [static fn (): Invoice => makeInvoice(attachments: [new Attachment(reference: 'DOC-1', externalUri: "\u{00A0}https://example.com/doc.pdf\u{00A0}")]), 'BR-TMP-2', false],
]);

it('counts, upper-cases and cuts rule inputs as UTF-8 whatever the internal encoding is', function (string $encoding, Closure $scenario, string $ruleId, bool $violates) use ($underEncoding): void {
    $hasViolation = $underEncoding($encoding, static fn (): bool => xr()->validateModel($scenario())->hasViolation($ruleId));

    expect($hasViolation)->toBe($violates);
})->with([...$encodings, 'UTF-16' => ['UTF-16']])->with([
    'card number of ten non-ASCII digits (BR-51)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: str_repeat("\u{0660}", 10))]), 'BR-51', false],
    'card number of eleven non-ASCII digits (BR-51)' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: str_repeat("\u{0660}", 11))]), 'BR-51', true],
    'VATEX code with a dotless i that upper-cases to I (BR-CL-22)' => [static fn (): Invoice => makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00', exemptionReasonCode: "vatex-eu-\u{0131}")]), 'BR-CL-22', false],
    'lower-case VATEX code (BR-CL-22)' => [static fn (): Invoice => makeInvoice(taxSubtotals: [new TaxSubtotal(category: 'S', rate: '19.00', taxableAmount: '100.00', taxAmount: '19.00', exemptionReasonCode: 'vatex-eu-o')]), 'BR-CL-22', false],
    'seller VAT identifier with a non-ASCII country prefix (BR-CO-09)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: "\u{00C4}T123456789", contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'BR-CO-09', true],
    'seller VAT identifier with non-ASCII after the country prefix (BR-CO-09)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: "DE\u{00C4}23456789", contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'BR-CO-09', false],
    'seller VAT identifier with an ASCII country prefix (BR-CO-09)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: 'DE', vatId: 'DE123456789', contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'BR-CO-09', false],
]);

it('reports every model string that is not valid UTF-8 as a fatal violation', function (string $encoding, Closure $scenario, string $field) use ($underEncoding): void {
    $validationResult = $underEncoding($encoding, static fn (): ValidationResult => xr()->validateModel($scenario()));

    $encodingViolations = array_values(array_filter($validationResult->violations, static fn (Violation $violation): bool => $violation->ruleId === 'UTF-8'));

    expect(array_map(static fn (Violation $violation): ?string => $violation->flag, $encodingViolations))->toBe([$field])
        ->and($encodingViolations[0]->severity)->toBe(Severity::Fatal)
        ->and($validationResult->isValid())->toBeFalse();
})->with($encodings)->with([
    'text field of the invoice' => [static fn (): Invoice => makeInvoice(number: "\xA0"), 'number'],
    'amount of the totals' => [static fn (): Invoice => makeInvoice(totals: new Totals(lineTotal: '100.00', taxBasisTotal: '100.00', taxTotal: '19.00', grandTotal: '119.00', paidAmount: '0.00', payableAmount: "\xA0")), 'totals.payableAmount'],
    'field of a party' => [static fn (): Invoice => makeInvoice(seller: new Party(name: "\xA0", countryCode: 'DE', vatId: 'DE123456789', contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'seller.name'],
    'field of an optional party' => [static fn (): Invoice => makeInvoice(payee: new Party(name: "\xA0")), 'payee.name'],
    'entry of a list of strings' => [static fn (): Invoice => makeInvoice(notes: ['Hinweis', "\xA0"]), 'notes[1]'],
    'entry of a list of nullable strings' => [static fn (): Invoice => makeInvoice(precedingInvoiceReferences: [null, "\xA0"]), 'precedingInvoiceReferences[1]'],
    'field of a list item' => [static fn (): Invoice => makeInvoice(paymentMeans: [new PaymentMeans(typeCode: '30', accountId: "\xA0", hasCreditTransfer: true)]), 'paymentMeans[0].accountId'],
    'field of an item nested in a list item' => [static fn (): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', attributes: [new ItemAttribute(name: 'cva', value: "\xA0")])]), 'lines[0].attributes[0].value'],
]);

it('leaves the result of a model in valid UTF-8 unchanged whatever the internal encoding is', function (string $encoding) use ($underEncoding): void {
    $invoice = makeInvoice(
        seller: new Party(name: "M\u{00FC}ller GmbH", countryCode: 'DE', vatId: 'DE123456789', contactName: "J\u{00FC}rgen Stra\u{00DF}e", contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: "K\u{00F6}ln", postCode: '50667'),
        notes: ["Zahlbar ohne Abzug \u{2013} danke", "\u{00A0}"],
        paymentMeans: [new PaymentMeans(typeCode: '48', hasCardInformation: true, cardNumber: str_repeat("\u{0660}", 11))],
    );

    $validationResult = $underEncoding($encoding, static fn (): ValidationResult => xr()->validateModel($invoice));
    $ruleResult = $underEncoding($encoding, static fn (): ValidationResult => new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()])->validate($invoice));

    expect($validationResult->hasViolation('UTF-8'))->toBeFalse()
        ->and($validationResult->hasViolation('BR-51'))->toBeTrue()
        ->and($validationResult->violations)->toEqual($ruleResult->violations);
})->with($encodings);

it('flags a list entry that is not valid UTF-8 without its key when the key is no list index', function (string $key): void {
    $validationResult = xr()->validateModel(makeInvoice(notes: [$key => "\xA0"]));

    $encodingViolations = array_values(array_filter($validationResult->violations, static fn (Violation $violation): bool => $violation->ruleId === 'UTF-8'));

    expect(array_map(static fn (Violation $violation): array => [$violation->flag, $violation->message], $encodingViolations))->toBe([['notes[?]', 'The value of notes[?] is not valid UTF-8.']])
        ->and(json_encode($validationResult->toArray(), JSON_THROW_ON_ERROR))->toBeString();
})->with([
    'key that is not valid UTF-8' => ["\xFF"],
    'key that carries customer data' => ['Max Muster, Berlin'],
]);

it('returns a result for a model that carries values outside the model classes', function (Closure $thirdPartyPayments): void {
    $validationResult = xr()->validateModel(makeInvoice(thirdPartyPayments: $thirdPartyPayments()));

    expect($validationResult->hasViolation('UTF-8'))->toBeFalse()
        ->and(json_encode($validationResult->toArray(), JSON_THROW_ON_ERROR))->toBeString();
})->with([
    'list that contains itself' => [static function (): array {
        $thirdPartyPayments = [];
        $thirdPartyPayments[] = &$thirdPartyPayments;

        return $thirdPartyPayments;
    }],
    'list that contains itself twice' => [static function (): array {
        $thirdPartyPayments = [];
        $thirdPartyPayments[] = &$thirdPartyPayments;
        $thirdPartyPayments[] = &$thirdPartyPayments;

        return $thirdPartyPayments;
    }],
    'object that refers to itself' => [static function (): array {
        $node = new stdClass;
        $node->self = $node;

        return [$node];
    }],
    'object with a property name that is not valid UTF-8' => [static fn (): array => [(object) ["\xFF" => 'Hinweis']]],
]);

it('returns a result for lines that each hold a list that contains itself', function (): void {
    $lines = array_map(static function (int $index): InvoiceLine {
        $attributes = [];
        $attributes[] = &$attributes;

        return new InvoiceLine(id: (string) $index, name: 'Beratung', attributes: $attributes);
    }, range(1, 2));

    expect(ModelEncoding::violations(makeInvoice(lines: $lines)))->toBe([]);
});

it('reports a string that is not valid UTF-8 in a value outside the model classes without its keys', function (Closure $scenario, string $field): void {
    $validationResult = xr()->validateModel($scenario());

    expect(array_map(static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->flag, $violation->message], $validationResult->violations))->toBe([['UTF-8', Severity::Fatal, $field, "The value of {$field} is not valid UTF-8."]])
        ->and(json_encode($validationResult->toArray(), JSON_THROW_ON_ERROR))->toBeString();
})->with([
    'string in an object that is a list item' => [static fn (): Invoice => makeInvoice(thirdPartyPayments: [(object) ['description' => "\xA0"]]), 'thirdPartyPayments[0][?]'],
    'string in a nested list' => [static fn (): Invoice => makeInvoice(notes: [['Hinweis', "\xA0"]]), 'notes[0][?]'],
    'string in an object with a property name that is not valid UTF-8' => [static fn (): Invoice => makeInvoice(thirdPartyPayments: [(object) ["\xFF" => "\xA0"]]), 'thirdPartyPayments[0][?]'],
    'string in a list inside an object inside a nested list' => [static fn (): Invoice => makeInvoice(lines: [new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', attributes: [[(object) ['values' => ['ok', "\xA0"]]]])]), 'lines[0].attributes[0][?]'],
    'string in an object that refers to itself' => [static function (): Invoice {
        $node = new stdClass;
        $node->self = $node;
        $node->description = "\xA0";

        return makeInvoice(thirdPartyPayments: [$node]);
    }, 'thirdPartyPayments[0][?]'],
    'string in a list that contains itself' => [static function (): Invoice {
        $notes = ["\xA0"];
        $notes[] = &$notes;

        return makeInvoice(notes: [$notes]);
    }, 'notes[0][?]'],
    'string in a private property of an object' => [static fn (): Invoice => makeInvoice(thirdPartyPayments: [new readonly class("\xA0")
    {
        public function __construct(private string $description) {}

        public function hasDescription(): bool
        {
            return $this->description !== '';
        }
    }]), 'thirdPartyPayments[0][?]'],
    'string in a protected property of an object' => [static fn (): Invoice => makeInvoice(thirdPartyPayments: [new readonly class("\xA0")
    {
        public function __construct(protected string $description) {}
    }]), 'thirdPartyPayments[0][?]'],
    'string of a Stringable list entry' => [static fn (): Invoice => makeInvoice(notes: [new class implements Stringable
    {
        public function __toString(): string
        {
            return "\xA0";
        }
    }]), 'notes[0][?]'],
]);

it('reports a value that exceeds the depth or size limit of the check without calling it invalid UTF-8', function (Closure $scenario, string $field): void {
    $startedAt = hrtime(true);
    $validationResult = xr()->validateModel($scenario());
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect(array_map(static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->flag, $violation->message], $validationResult->violations))->toBe([['UTF-8', Severity::Fatal, $field, "The value of {$field} is nested too deep or too large to be checked for valid UTF-8."]])
        ->and($elapsedSeconds)->toBeLessThan(1.0);
})->with([
    'string not valid UTF-8 nested deeper than the depth limit' => [static fn (): Invoice => makeInvoice(notes: [array_reduce(range(1, 100), static fn (array $list): array => [$list], ["\xA0"])]), 'notes[0][?]'],
    'valid string nested deeper than the depth limit' => [static fn (): Invoice => makeInvoice(notes: [array_reduce(range(1, 100), static fn (array $list): array => [$list], ['Hinweis'])]), 'notes[0][?]'],
    'list shared by value on every one of 40 levels' => [static fn (): Invoice => makeInvoice(thirdPartyPayments: [(object) ['data' => array_reduce(range(1, 40), static fn (array $list): array => [$list, $list], ['x'])]]), 'thirdPartyPayments[0][?]'],
    'wide list shared by value' => [static function (): Invoice {
        $row = array_fill(0, 2000, 'Hinweis');

        return makeInvoice(thirdPartyPayments: [(object) ['rows' => array_fill(0, 2000, $row)]]);
    }, 'thirdPartyPayments[0][?]'],
    'wide list of references shared by value on every one of 40 levels' => [static function (): Invoice {
        $target = 'Hinweis';
        $references = [];

        while (count($references) < 2000) {
            $references[] = &$target;
        }

        return makeInvoice(thirdPartyPayments: [(object) ['data' => array_reduce(range(1, 40), static fn (array $list): array => [$list, $list], $references)]]);
    }, 'thirdPartyPayments[0][?]'],
]);

it('checks a model of 50000 full lines with the result of the business rules and no limit violation', function (): void {
    $lines = array_map(static fn (int $index): InvoiceLine => new InvoiceLine(
        id: (string) $index, name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
        allowanceCharges: [new LineAllowanceCharge(isCharge: false, amount: '5.00', reason: 'Rabatt'), new LineAllowanceCharge(isCharge: true, amount: '5.00', reason: 'Fracht')],
        hasPeriod: true, periodStart: '2026-01-01', periodEnd: '2026-01-31',
        itemClassifications: [new ItemClassification(code: '8471', scheme: 'HS'), new ItemClassification(code: '7311', scheme: 'STI')],
        attributes: [new ItemAttribute(name: 'cva', value: 'clean'), new ItemAttribute(name: 'Farbe', value: 'Blau'), new ItemAttribute(name: 'Größe', value: 'M')],
    ), range(1, 50000));
    $invoice = makeInvoice(lines: $lines);

    $startedAt = hrtime(true);
    $encodingViolations = ModelEncoding::violations($invoice);
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($encodingViolations)->toBe([])
        ->and($elapsedSeconds)->toBeLessThan(2.0)
        ->and(xr()->validateModel($invoice)->violations)->toEqual(new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()])->validate($invoice)->violations);
});

it('reports every one of 100000 list entries that are not valid UTF-8 within a second', function (): void {
    $startedAt = hrtime(true);
    $validationResult = xr()->validateModel(makeInvoice(notes: array_fill(0, 100000, "\xA0")));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($validationResult->violations)->toHaveCount(100000)
        ->and($validationResult->violations[0]->flag)->toBe('notes[0]')
        ->and($validationResult->violations[99999]->flag)->toBe('notes[99999]')
        ->and($validationResult->violations[99999]->message)->toBe('The value of notes[99999] is not valid UTF-8.')
        ->and($elapsedSeconds)->toBeLessThan(1.0);
});

it('checks a model of 25000 lines in valid UTF-8 within the limits', function (): void {
    $line = new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', itemClassifications: [new ItemClassification(code: 'M1', scheme: 'CVD')], attributes: [new ItemAttribute(name: 'cva', value: 'clean')]);

    expect(ModelEncoding::violations(makeInvoice(lines: array_fill(0, 25000, $line))))->toBe([]);
});

it('checks model objects that share a subtree on every level in linear time', function (): void {
    $line = static fn (array $attributes): InvoiceLine => new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', attributes: $attributes);
    $invoiceLine = array_reduce(range(1, 30), static fn (InvoiceLine $invoiceLine): InvoiceLine => $line([$invoiceLine, $invoiceLine]), $line([]));

    $startedAt = hrtime(true);
    $encodingViolations = ModelEncoding::violations(makeInvoice(lines: [$invoiceLine]));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($encodingViolations)->toBe([])
        ->and($elapsedSeconds)->toBeLessThan(1.0);
});

it('reports model objects that share a subtree with a violation on every level with a limit violation within a second', function (): void {
    $line = static fn (array $attributes, string $name): InvoiceLine => new InvoiceLine(id: '1', name: $name, netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', attributes: $attributes);
    $invoiceLine = array_reduce(range(1, 30), static fn (InvoiceLine $invoiceLine): InvoiceLine => $line([$invoiceLine, $invoiceLine], 'Beratung'), $line([], "\xA0"));

    $startedAt = hrtime(true);
    $validationResult = xr()->validateModel(makeInvoice(lines: [$invoiceLine]));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    $lastViolation = $validationResult->violations[array_key_last($validationResult->violations)];
    $invalidViolations = array_filter($validationResult->violations, static fn (Violation $violation): bool => $violation->message === "The value of {$violation->flag} is not valid UTF-8.");

    expect(array_unique(array_map(static fn (Violation $violation): string => $violation->ruleId.'/'.$violation->severity->value, $validationResult->violations)))->toBe(['UTF-8/'.Severity::Fatal->value])
        ->and($lastViolation->message)->toBe("The value of {$lastViolation->flag} is nested too deep or too large to be checked for valid UTF-8.")
        ->and(count($invalidViolations))->toBe(count($validationResult->violations) - 1)
        ->and(reset($invalidViolations)->flag)->toBe('lines[0]'.str_repeat('.attributes[0]', 30).'.name')
        ->and($elapsedSeconds)->toBeLessThan(1.0);
});

it('checks lines that share their model objects with the result of the business rules and no limit violation', function (int $lineCount, Closure $lines): void {
    $invoice = makeInvoice(lines: $lines($lineCount));

    $startedAt = hrtime(true);
    $encodingViolations = ModelEncoding::violations($invoice);
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($encodingViolations)->toBe([])
        ->and($elapsedSeconds)->toBeLessThan($lineCount / 25000)
        ->and(xr()->validateModel($invoice)->violations)->toEqual(new Validator([...RuleSets::en16931(), ...RuleSets::xrechnung()])->validate($invoice)->violations);
})->with([
    '50000 lines' => [50000],
    '200000 lines' => [200000],
])->with([
    'that hold the same lists' => [static function (int $lineCount): array {
        $allowanceCharges = [new LineAllowanceCharge(isCharge: false, amount: '5.00', reason: 'Rabatt'), new LineAllowanceCharge(isCharge: true, amount: '5.00', reason: 'Fracht')];
        $itemClassifications = [new ItemClassification(code: '8471', scheme: 'HS'), new ItemClassification(code: '7311', scheme: 'STI')];
        $attributes = [new ItemAttribute(name: 'cva', value: 'clean'), new ItemAttribute(name: 'Farbe', value: 'Blau'), new ItemAttribute(name: 'Größe', value: 'M')];

        return array_map(static fn (int $index): InvoiceLine => new InvoiceLine(
            id: (string) $index, name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
            allowanceCharges: $allowanceCharges, itemClassifications: $itemClassifications, attributes: $attributes,
        ), range(1, $lineCount));
    }],
    'that hold lists of their own with the same model objects' => [static function (int $lineCount): array {
        [$discount, $freight] = [new LineAllowanceCharge(isCharge: false, amount: '5.00', reason: 'Rabatt'), new LineAllowanceCharge(isCharge: true, amount: '5.00', reason: 'Fracht')];
        [$customsCode, $productCode] = [new ItemClassification(code: '8471', scheme: 'HS'), new ItemClassification(code: '7311', scheme: 'STI')];
        [$cva, $colour, $size] = [new ItemAttribute(name: 'cva', value: 'clean'), new ItemAttribute(name: 'Farbe', value: 'Blau'), new ItemAttribute(name: 'Größe', value: 'M')];

        return array_map(static fn (int $index): InvoiceLine => new InvoiceLine(
            id: (string) $index, name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00',
            allowanceCharges: [$discount, $freight], itemClassifications: [$customsCode, $productCode], attributes: [$cva, $colour, $size],
        ), range(1, $lineCount));
    }],
]);

it('checks many model objects that share a long list in linear time', function (Closure $entries): void {
    $sharedList = $entries();
    $lines = array_map(static fn (int $index): InvoiceLine => new InvoiceLine(id: (string) $index, name: 'Beratung', attributes: $sharedList), range(1, 10000));

    $startedAt = hrtime(true);
    $encodingViolations = ModelEncoding::violations(makeInvoice(lines: $lines));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($encodingViolations)->toBe([])
        ->and($elapsedSeconds)->toBeLessThan(1.0);
})->with([
    'list of strings' => [static fn (): array => array_fill(0, 10000, 'Hinweis')],
    'list of one model object' => [static fn (): array => array_fill(0, 10000, new ItemAttribute(name: 'cva', value: 'clean'))],
    'list of distinct model objects' => [static fn (): array => array_map(static fn (int $index): ItemAttribute => new ItemAttribute(name: 'cva', value: (string) $index), range(1, 10000))],
]);

it('reports many model objects that hold one of two long lists in turn with a limit violation within a second', function (Closure $entries): void {
    $sharedLists = [$entries('clean'), $entries('dirty')];
    $lines = array_map(static fn (int $index): InvoiceLine => new InvoiceLine(id: (string) $index, name: 'Beratung', attributes: $sharedLists[$index % 2]), range(1, 10000));

    $startedAt = hrtime(true);
    $validationResult = xr()->validateModel(makeInvoice(lines: $lines));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($validationResult->violations)->toHaveCount(1)
        ->and($validationResult->violations[0]->message)->toBe("The value of {$validationResult->violations[0]->flag} is nested too deep or too large to be checked for valid UTF-8.")
        ->and($elapsedSeconds)->toBeLessThan(1.0);
})->with([
    'lists of strings' => [static fn (string $value): array => array_fill(0, 10000, $value)],
    'lists of model objects' => [static function (string $value): array {
        $itemAttribute = new ItemAttribute(name: 'cva', value: $value);

        return array_fill(0, 10000, $itemAttribute);
    }],
]);

it('reports a long string that is not valid UTF-8 in many places within a second', function (Closure $scenario, string $firstField, string $lastField): void {
    $invalidString = str_repeat('a', 2_000_000)."\xA0";

    $startedAt = hrtime(true);
    $validationResult = xr()->validateModel($scenario($invalidString));
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($validationResult->violations)->toHaveCount(50000)
        ->and($validationResult->violations[0]->flag)->toBe($firstField)
        ->and($validationResult->violations[49999]->message)->toBe("The value of {$lastField} is not valid UTF-8.")
        ->and($elapsedSeconds)->toBeLessThan(1.0);
})->with([
    'list entries' => [static fn (string $invalidString): Invoice => makeInvoice(notes: array_fill(0, 50000, $invalidString)), 'notes[0]', 'notes[49999]'],
    'fields of distinct lines' => [static fn (string $invalidString): Invoice => makeInvoice(lines: array_map(static fn (int $index): InvoiceLine => new InvoiceLine(id: (string) $index, name: $invalidString), range(0, 49999))), 'lines[0].name', 'lines[49999].name'],
]);

it('returns a result with a violation for a model object that contains itself or a chain of model objects deeper than the depth limit', function (Closure $scenario, string $field, string $message): void {
    $validationResult = xr()->validateModel($scenario());

    expect(array_map(static fn (Violation $violation): array => [$violation->ruleId, $violation->severity, $violation->flag, $violation->message], $validationResult->violations))->toBe([['UTF-8', Severity::Fatal, $field, sprintf($message, $field)]]);
})->with([
    'model object that contains itself through a reference in its list' => [static function (): Invoice {
        $attributes = [];
        $attributes[0] = &$slot;
        $line = new InvoiceLine(id: '1', name: 'Beratung', netAmount: '100.00', netPrice: '100.00', quantity: '1', unitCode: 'C62', taxCategory: 'S', taxRate: '19.00', attributes: $attributes);
        $slot = $line;

        return makeInvoice(lines: [$line]);
    }, 'lines[0].attributes[0]', 'The value of %s contains itself and cannot be checked for valid UTF-8.'],
    'chain of model objects deeper than the depth limit' => [static fn (): Invoice => makeInvoice(lines: [array_reduce(range(1, 40), static fn (InvoiceLine $invoiceLine): InvoiceLine => new InvoiceLine(id: '1', name: 'Beratung', attributes: [$invoiceLine]), new InvoiceLine(id: '1', name: 'Beratung'))]), 'lines[0]'.str_repeat('.attributes[0]', 31), 'The value of %s is nested too deep or too large to be checked for valid UTF-8.'],
]);

it('reports a string that is not valid UTF-8 in an object of the model namespace that is no model class without its property names', function (): void {
    require_once __DIR__.'/../Support/CustomerRecord.php';

    $validationResult = xr()->validateModel(makeInvoice(thirdPartyPayments: [new CustomerRecord("\xA0")]));

    expect(array_map(static fn (Violation $violation): array => [$violation->flag, $violation->message], $validationResult->violations))->toBe([['thirdPartyPayments[0][?]', 'The value of thirdPartyPayments[0][?] is not valid UTF-8.']]);
});

it('returns only the UTF-8 violations, without the raw bytes, when a code-list field is not valid UTF-8', function (Closure $scenario, string $field): void {
    $validationResult = xr()->validateModel($scenario());

    $messages = array_map(static fn (Violation $violation): string => $violation->message, $validationResult->violations);

    expect(array_map(static fn (Violation $violation): array => [$violation->ruleId, $violation->flag], $validationResult->violations))->toBe([['UTF-8', $field]])
        ->and(json_encode($validationResult->toArray(), JSON_THROW_ON_ERROR))->toBeString()
        ->and(array_filter($messages, static fn (string $message): bool => str_contains($message, "\xFF")))->toBe([]);
})->with([
    'invoice currency code (BR-CL-04)' => [static fn (): Invoice => makeInvoice(currency: "EU\xFF"), 'currency'],
    'seller country code (BR-CL-14)' => [static fn (): Invoice => makeInvoice(seller: new Party(name: 'Seller GmbH', countryCode: "D\xFF", vatId: 'DE123456789', contactName: 'Max Muster', contactPhone: '+49 30 000000', contactEmail: 'billing@example.com', city: 'Berlin', postCode: '10115')), 'seller.countryCode'],
]);
