# en16931-php

**A dependency-free, Java-free PHP validator for EN 16931 e-invoices** (ZUGFeRD /
Factur-X / XRechnung), in **both CII and UBL syntax**.

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

// validate() auto-detects CII vs UBL; validateCii()/validateUbl() force one.
$result = En16931Validator::xrechnung()->validate($xml); // or ::en16931()

$result->isValid();     // bool — no fatal violation
foreach ($result->violations as $violation) {
    echo "{$violation->ruleId} [{$violation->flag}]: {$violation->message}\n";
}
```

You can also validate a pre-built model (`En16931Validator::…->validateModel($invoice)`)
without XML.

## Covered rules

- **Document presence** BR-01 (BT-24), BR-02/03/04/05/06/07/09/11/16, BR-CO-18
- **Line presence** BR-21/22/23/24/25/26 (BT-126/129/130/131/153/146), BR-CO-04
- **VAT breakdown presence** BR-45/46/47 (BT-116/117/118)
- **Calculation (tolerance-free, exact BCMath)** BR-CO-10/11/12/13/14/15/16/17,
  and the per-category taxable-sum rules BR-{S,Z,E,AE,IC,G,O}-08 (line net −
  document allowances + charges)
- **Decimals** BR-DEC-09..20/23 (amounts ≤ 2 decimal places)
- **VAT category** BR-27 (net price ≥ 0), BR-S-05, BR-AE-02/03, per-category
  BR-\*-01 (matching breakdown), BR-\*-09 (zero tax), BR-\*-10 (exemption reason)
- **Code lists** BR-CL-01 (BT-3), BR-CL-03 (ISO 4217 currency), BR-CL-14
  (ISO 3166 country, seller + buyer), BR-CL-17 (BT-151)
- **XRechnung CIUS** BR-DE-1 (Leitweg-ID), BR-DE-5/6/7 (seller contact)

Full ISO 4217 and ISO 3166-1 code lists are bundled. Document-level allowances
and charges (BG-20/BG-21) are modelled and reconciled.

## Roadmap

- The remaining EN 16931 rules (line-level allowances/charges, more BR-CO-*)
- Conformance harness diffing against the KoSIT validator on the official corpus

## Quality gates

```bash
composer qa   # pint + rector + phpstan (max) + pest
```

## License

MIT. See [LICENSE.md](LICENSE.md).
