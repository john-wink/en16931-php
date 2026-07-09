# en16931-php

**A dependency-free, Java-free PHP validator for EN 16931 e-invoices** (ZUGFeRD /
Factur-X / XRechnung, CII syntax).

The official EN 16931 / KoSIT rule sets are Schematron compiled to XSLT 2.0 and
run by Saxon (Java). This library reimplements the business rules **natively in
PHP** — no JRE, no jar downloads, no subprocess — reading amounts as exact
decimal strings (BCMath) so the tolerance-free calculation rules are exact.

> [!IMPORTANT]
> This is a **growing, high-value subset** of the EN 16931 + XRechnung rules —
> presence, the calculation rules (BR-CO-*), key VAT-category rules, code lists
> and the German BR-DE essentials. It is **not yet full KoSIT parity**. The goal
> is to reach corpus parity with the official validator over time (which is kept
> as a dev-only golden oracle). Do not treat a green result as a legal guarantee.

## Requirements

- PHP **8.4+**, `ext-bcmath`, `ext-dom`

## Install

```bash
composer require john-wink/en16931-php
```

## Usage

```php
use JohnWink\En16931\En16931Validator;

$result = En16931Validator::xrechnung()->validateCii($xml); // or ::en16931()

$result->isValid();     // bool — no fatal violation
foreach ($result->violations as $violation) {
    echo "{$violation->ruleId} [{$violation->flag}]: {$violation->message}\n";
}
```

You can also validate a pre-built model (`En16931Validator::…->validateModel($invoice)`)
without XML.

## Covered rules (v0)

- **Presence** BR-02/03/04/05/06/07/09/11/16
- **Calculation** BR-CO-10/13/14/15/16 (tolerance-free, exact BCMath)
- **VAT category** BR-27 (net price ≥ 0), BR-S-05, BR-AE-02/03
- **Code lists** BR-CL-01 (BT-3), BR-CL-17 (BT-151)
- **XRechnung CIUS** BR-DE-1 (Leitweg-ID), BR-DE-5/6/7 (seller contact)

## Roadmap

- The remaining EN 16931 rules + full ISO 4217 / ISO 3166 code lists
- UBL syntax reader
- Conformance harness diffing against the KoSIT validator on the official corpus

## Quality gates

```bash
composer qa   # pint + rector + phpstan (max) + pest
```

## License

MIT. See [LICENSE.md](LICENSE.md).
