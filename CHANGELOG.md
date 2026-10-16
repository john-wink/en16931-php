# Changelog

All notable changes to `john-wink/en16931-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Pre-1.0: the public API may still change between minor versions.

## [Unreleased]

Candidate for 0.4.0, not 0.3.1: `Decimal` is public API and
`Decimal::isNumeric()` changes in a backwards-incompatible way, and
`validateModel()` now fails a model with a string that is not valid UTF-8
(both see below).

### Added

- `Decimal::isFilled()`: the one definition of a filled value, for amounts and
  text fields alike: not null and not only whitespace, U+00A0 and U+3000
  included. It trims as UTF-8 whatever `mb_internal_encoding()` is set to.
- `Decimal::isFilledButNotXsDecimal()`: a filled value that
  `Decimal::fromXsDecimal()` cannot read.
- `Decimal::fromXsDecimal()`: reads an `xs:decimal`, ignoring only XML
  whitespace (space, tab, CR, LF), and returns a value `Decimal::isNumeric()`
  accepts, or `null`.

### Changed

- **BR-CO-17 now applies the official CEN tolerance instead of exact equality.**
  The VAT category tax amount (BT-117) is compared with
  round(|BT-116| × BT-119 / 100) per syntax, exactly as the official artefacts
  assert: for CII the absolute value of BT-117 may differ by at most 1
  (inclusive), for UBL by less than 1 (strict). A model validated without a
  source syntax (`validateModel` on an `Invoice` without `sourceSyntax`) uses
  the strict UBL check, so it passes only what both artefacts accept. BR-CO-14
  and BR-CO-15 stay exact.
- **BR-CO-17 with a missing rate, or one that rounds to zero, now requires
  BT-117 to round to zero.** A missing BT-119 used to skip the rule; a BT-117
  of 0.50 or more (or below -0.50) is now fatal, as in the official artefacts.
- **`Decimal::isNumeric()` no longer trims (breaking).** It accepts a subset
  of what BCMath accepts: an optional `-`, digits and an optional fraction.
  BCMath also takes `+19.00`, `.50`, `19.` and `-.5`; `isNumeric()` rejects
  them. Every value it accepts can go into `Decimal::add()`, `sub()`, `mul()`
  and `compare()`. A value framed by whitespace, including U+00A0 and U+3000,
  used to pass and then crashed BCMath with a `ValueError`; it now returns
  `false`. Callers that read raw XML text use `Decimal::fromXsDecimal()`, which
  ignores only XML whitespace, reads the forms `+19.00`, `.50` and `19.`, and
  returns a value `isNumeric()` accepts.
- **`Party::hasVatId()`, `hasAnyIdentifier()` and `hasPostalAddress()` use
  `Decimal::isFilled()`.** They used to count only `''` as empty. A VAT
  identifier (BT-31, BT-48, BT-63) or a Seller identifier (BT-29, BT-30) of
  only U+00A0 or U+3000 no longer counts as present, so the rules that require
  one now fail on it: BR-S/Z/E/IG/IP/AE-02 to -04, BR-IC-02 to -04,
  BR-G-02 to -04, BR-CO-26 and BR-DE-16. A Seller, Buyer or tax representative
  postal address of only such spaces no longer counts as present: BR-08, BR-10
  and BR-19 now fail on it. BR-DE-3, BR-DE-4, BR-DE-8 and BR-DE-9 check city
  and post code only when an address is present, so they no longer fire on an
  address of only whitespace; BR-08 or BR-10 fails it as fatal instead.
- **BR-O-02 to BR-O-04 keep failing on a blank VAT identifier in
  `validateModel`.** They fail on any supplied BT-31, BT-48 or BT-63 other than
  `''`, a value of only whitespace included. Over XML this does not arise: the
  readers trim Unicode whitespace and read such a VAT identifier as absent, so
  these rules pass on it.
- **Model inputs are UTF-8.** `Decimal::isFilled()` and the rules trim every
  string as UTF-8 whatever `mb_internal_encoding()` is set to, so
  `validateModel()` expects every string in the `Invoice` model to be UTF-8.
  The readers deliver UTF-8. The README states the contract.
- **`validateModel()` fails closed on strings that are not valid UTF-8
  (breaking).** A caller that passes ISO-8859-1 strings, such as a name with
  `ü` as the byte `0xFC`, used to get a result and now gets a fatal violation.
  Every string in the `Invoice` model that fails
  `mb_check_encoding($value, 'UTF-8')` is a fatal violation with the rule ID
  `UTF-8`, flagged with the path of the field, such as `seller.name`,
  `notes[1]` or `lines[0].attributes[0].value`. The path holds only property
  names of the 13 model classes and list indexes; any other key shows as `[?]`.
  Strings in values outside the model classes, such as other objects or
  nested lists, are checked as well and flagged with `[?]` after the field,
  such as `thirdPartyPayments[0][?]`: every property of such an object,
  private and protected ones included, and the string of a `Stringable`. The
  check stops at 64 levels and after 1,000,000 counted values. It skips a
  model object it has checked before without a violation and a list with the
  same entries as the last list without a violation in the same property of
  the same model class, unless that list holds lists. Comparing with that
  list weighs one unit per entry plus the bytes of its string entries and
  string keys; up to 64 units the comparison is free, above that its weight
  counts on every comparison, whether the list is the same array or a
  content-equal one built apart. The same rule holds for a string that is not
  valid UTF-8: every further place with the same content, whether the same
  string or a content-equal one stored apart, weighs its bytes; up to 64 bytes
  it is free, above that its weight counts. In the lists of
  every model object other than the invoice, an entry counts unless it is a
  model object the check meets for the first time, and a model object with a
  violation that the check meets again there counts with all its values;
  every value outside the model classes counts, including list entries
  skipped as a reference already checked. The result for a model whose lists
  hold up to 64 units, whose model objects each appear once or whose lines
  share model objects, and whose invalid strings of more than 64 bytes each
  appear once, therefore does not depend on its size. A list of more
  than 64 units counts its weight even when the comparison fails, so lines
  with different lists count as well: lines that each hold 65 distinct model
  objects end with the limit violation after about 15,400 lines. Many model
  objects that share a longer list end with the limit violation. Every
  violation is reported, without a cap on their number, so memory grows with
  the violations: in the worst case a small model with a shared subtree and a
  list of invalid strings yields close to 1,000,000 violations with paths of
  several hundred characters, measured at about 1.1 GB (969,099 violations,
  paths up to 444 characters) and estimated at up to about 2 GB with longer
  property names in the path. A model beyond these limits,
  or a model object that contains itself, gets a fatal `UTF-8` violation with
  a message of its own (`… is nested too deep or too large to be checked for
  valid UTF-8.`, `… contains itself and cannot be checked for valid UTF-8.`),
  even when every string is valid UTF-8. A model with such a violation gets only the
  `UTF-8` violations, without the results of the business rules, so no message
  repeats the raw bytes and `toArray()` stays JSON-encodable. A field of only the
  ISO-8859-1 byte `0xA0` used to count as filled. Results for models in valid
  UTF-8 within these limits do not change; `validate()` reads XML into UTF-8
  and is not affected.

### Fixed

- **The readers and the rules trim as UTF-8 whatever `mb_internal_encoding()`
  is set to.** With an internal encoding such as ISO-8859-1, `mb_trim()` cut
  only the last byte of a U+00A0. The readers then read an element of only
  U+00A0 (a BT-31, for example) as a one-byte value instead of as missing, and
  kept a byte of a framing U+00A0 in names, codes and `@currencyID`. The rules
  that trim a code, a VAT identifier, a card number or a URL (BR-50, BR-51,
  BR-61, BR-CL-22, BR-CO-09, BR-DE-19, BR-DE-20, BR-DE-23 to BR-DE-25,
  BR-DE-CVD-04/05, BR-TMP-2) judged a value framed by U+00A0 differently than
  under UTF-8.
- **BR-51, BR-CL-22 and BR-CO-09 count, upper-case and cut as UTF-8 whatever
  `mb_internal_encoding()` is set to.** Under ISO-8859-1, BR-51 counted the
  bytes of a card number instead of its characters and warned on ten non-ASCII
  digits, and BR-CL-22 rejected a VATEX code that upper-cases to a valid one
  only under UTF-8, such as `vatex-eu-ı`.
- **BR-CO-17 fails closed on unreadable values.** A filled BT-116, BT-119 or
  BT-117 that is not an `xs:decimal` used to skip the rule and now fails it. The
  `xs:decimal` forms the engine did not read before (`+19.00`, `.50`, `19.`) are
  normalised and validated instead of skipped. Only XML whitespace (space, tab,
  CR, LF) around a value is ignored; a value framed by other Unicode spaces
  such as U+00A0 is not an `xs:decimal` and fails.
- **`validateModel` no longer throws a `ValueError` on amounts framed by XML
  whitespace.** A BT-117 such as `' 19.00 '` crashed BCMath in BR-CO-14 and in
  BR-S-09, BR-IG-09 and BR-IP-09; the same happened in BR-CO-10 to BR-CO-16,
  BR-S/Z/E/AE/IC/G/O/IG/IP-08, the VAT-rate rules BR-S/Z/E/AE/IC/G/IG/IP-05,
  -06 and -07, the zero-tax rules BR-Z/E/AE/IC/G/O-09, BR-27, BR-28,
  BR-CO-25, BR-DEX-02 and BR-DEX-09. Every amount and rate that reaches a
  calculation is now read as an `xs:decimal` through the new
  `Decimal::fromXsDecimal()`, the reader BR-CO-17 already used: space, tab, CR
  and LF around a value are ignored, and the forms `+19.00`, `.50` and `19.`
  are read instead of being treated as unreadable. BR-CO-14 and BR-CO-15 stay
  exact.
- **A filled amount that is not an `xs:decimal` now fails closed.** A value
  such as U+00A0 + `999.00` + U+00A0 used to count as missing, so the rule
  passed silently. It is now a fatal violation of BR-CO-10 to BR-CO-16 (as the
  declared total or as one of its operands, including BR-CO-16, which is
  otherwise a warning), of BR-CO-25 (BT-115), of BR-DEX-09 (BT-112 to BT-115
  and the third party payment amounts) and of the zero-tax rules
  BR-Z/E/AE/IC/G/O-09 (BT-117), in addition to BR-CO-17. The same holds for
  BR-27 (BT-146) and BR-28 (BT-148), and for the amounts no calculation reads:
  BT-111, BT-93, BT-100, BT-136, BT-137, BT-141 and BT-142 now fail BR-DEC-15,
  -02, -06, -24, -25, -27 and -28, with or without a decimal point. A value of
  only whitespace, U+00A0 or U+3000 still counts as missing. Failing a filled
  value without a `.` is stricter than the official BR-DEC asserts, which let
  it pass; over XML the XSD rejects such a value first.
- **BR-DEX-02 fails closed on unreadable amounts.** A filled line or sub line
  net amount that is not an `xs:decimal` used to skip the warning; it now
  raises it, as BR-DEX-09 does.
- **BR-DEC-* and BR-DEX-13 count the decimals of the `xs:decimal` reading.**
  An amount written as `+0.505` or `.505` used to pass them; it now fails them
  like `0.505`. A filled value that is not an `xs:decimal` fails them. Known
  deviation, not proven against KoSIT: space, tab, CR and LF after the
  decimals are ignored, while the official assert presumably counts them as
  characters after the `.` (`19.00` followed by a space). The rules are not
  tightened without that proof; COVERAGE.md notes it per rule.

## [0.3.0] - 2026-07-15

### Added

- **Full three-stage validation pipeline — every layer the KoSIT validator
  enforces is now covered natively.** Validating XML runs XSD schema → syntax
  rules → business rules, matching the official validator's own order.
- **XSD schema validation (stage 1).** The official UBL 2.1 and CII D16B schemas
  are bundled under `resources/xsd`; `SchemaValidator` rejects structurally
  malformed documents before any rule runs — no download, no Java.
- **All 1276 syntax rules (stage 2): UBL-CR (679), UBL-SR (50), CII-SR (450) and
  CII-DT (97).** `SchematronEngine` evaluates them directly against the DOM from
  `resources/syntax-rules.json`, generated by `tools/build-syntax-rules.php` from
  the KoSIT configuration. The few XPath 2.0 constructs the rules use
  (`upper-case`, `matches`, `distinct-values`, boolean-literal comparisons) are
  rewritten to XPath 1.0 / bridged PHP functions so PHP's `DOMXPath` can run them.
- The parity test now compares against KoSIT across **all three layers** over the
  full official instance suite — 0 false positives and 0 verdict divergences on
  the XSD, syntax and business-rule codes alike.
- `COVERAGE.md` and the README gain a validation-pipeline overview summarising all
  three stages; the per-rule matrix continues to detail the business rules.

### Changed

- `En16931Validator::validate()` / `validateCii()` / `validateUbl()` now return
  violations from all three stages (a fatal XSD or syntax violation makes
  `isValid()` false). `validateModel()` is unchanged — it runs the business rules
  only, since there is no XML to schema- or syntax-check.

## [0.2.0] - 2026-07-15

### Added

- **Full KoSIT rule coverage (274 / 274).** The coverage reference is now built
  from the KoSIT validator configuration 3.0.2 — the compiled Schematron the
  official validator runs, i.e. the legal conformance target — instead of the
  schematron source repositories, which drift from the shipped configuration.
- Newly enforced rules that the configuration adds on top of the source:
  BR-66 / BR-67 (at most one payment card / mandate), BR-CO-27 (registered;
  vacuous in the config), BR-DEX-15 (CII: sub invoice lines unsupported) and
  BR-CO-25 (kept by the config).
- The parity test now runs the **entire official KoSIT test suite**
  bidirectionally — every UBL and CII instance across standard, extension and
  technical-cases — asserting verdict parity and zero false positives on all of
  them (previously only a two-file CII corpus ran bidirectionally).

### Changed

- **IGIC / IPSI rules renamed BR-AF → BR-IG and BR-AG → BR-IP** to match the
  KoSIT configuration (the logic is unchanged; the ids were outdated). This is a
  breaking change for anyone matching on the old ids.
- `Invoice` carries the source syntax (`ubl` / `cii`) so the few genuinely
  syntax-specific rules (BR-DEX-15) resolve correctly.

### Removed

- **BR-DE-TMP-32 is no longer enforced.** It exists in the XRechnung schematron
  source but is not shipped in the KoSIT validator configuration 3.0.2, so
  enforcing it rejected invoices the official validator accepts (32 false
  positives on the official suite). Documented in COVERAGE.md.

- Full rule coverage: the XRechnung **extension** (BR-DEX-01..14 — sub invoice
  lines, third party payments, DiGA schemes, `application/xml` attachments)
  and the **Clean Vehicles Directive** profile (BR-DE-CVD-01..06, BR-TMP-CVD-01),
  plus BR-DE-14 (breakdown rate) and BR-TMP-2 (external document URL). These
  are gated on the specification identifier (BT-24), so they stay silent unless
  the extension / CVD profile is declared. New `ThirdPartyPayment` and
  `SubInvoiceLine` models; contract/tender references (BT-12/BT-17) and item
  origin country (BT-159) are now parsed. **All 278 official EN 16931 + KoSIT
  XRechnung 2.5.0 business rules are now implemented.**

- The remaining code lists: BR-CL-03 (amount currency attributes), BR-CL-05
  (tax currency), BR-CL-06 (tax point date code), BR-CL-07 (UNTDID 1153),
  BR-CL-08 (UNTDID 4451 note subjects), BR-CL-10/11/21/26 (ISO 6523 ICD, with
  the SEPA extra on BR-CL-10), BR-CL-13 (UNTDID 7143), BR-CL-15 (origin
  country), BR-CL-19/20 (UNTDID 5189/7161 reason codes), BR-CL-22 (VATEX,
  case-insensitive) and BR-CL-23 (UN/ECE Rec 20+21, ~2100 unit codes). The
  code lists are generated from the official Schematron; BR-CL-10/11/21/23/
  24/25/26 carry warning severity, mirroring the KoSIT scenario downgrades.

- Documents & line details: attachments (BG-24 — BR-52, BR-CL-24 MIME list,
  BR-DE-22 unique filenames), preceding invoices (BG-3 — BR-55, BR-DE-26),
  payee (BG-10 — BR-17), tax representative address (BR-19), BT-111 (BR-53,
  BR-DEC-15), gross price (BR-28), item identifiers and attributes (BR-54,
  BR-64, BR-65), tax point date exclusivity (BR-CO-03), the BR-CO-21..24
  reason restatements and the base-amount decimal rules (BR-DEC-02/06/25/28)
  plus BR-DEC-05/24/27. Model and both readers gained attachments, preceding
  invoice references, payee, BT-7, BT-111, BT-148, item details and
  allowance/charge base amounts.

- Delivery & periods (BG-13/14/15, BG-26): BR-29/30 (period ordering),
  BR-CO-19/20 (period content), BR-57 (deliver-to country), BR-IC-11/12
  (intra-community delivery info), BR-DE-10/11 (deliver-to city + post code)
  and BR-DE-TMP-32 (delivery date/period recommendation). BR-CL-14 now covers
  all four country fields (BT-40/55/69/80). The model gains the invoicing and
  line periods, the actual delivery date (BT-72), BT-8 and the deliver-to
  address (BG-15), parsed from both CII and UBL.

- Payment instructions (BG-16..19): BR-49/50/51/61 and BR-CL-16 (UNTDID 4461),
  plus the XRechnung payment rules — BR-DE-1 (payment instructions mandatory),
  BR-DE-23/24/25 a+b (payment-code group consistency), BR-DE-30/31 (direct
  debit) and BR-DE-19/20 (IBAN checks incl. the mod-97 checksum). New
  `PaymentMeans` model (BT-81/84/87/91 + group flags) and BT-90, parsed from
  both CII and UBL.

- Postal addresses and electronic addresses: BR-08/BR-10 (address groups),
  BR-62/BR-63 (electronic address scheme required), BR-CL-25 (EAS code list)
  and the XRechnung address rules BR-DE-3/4/8/9 (city + post code). The model
  gains street/city/post code and the electronic address incl. scheme id per
  party, parsed from both CII and UBL.

- ~70 additional rules: document allowance/charge mandatory fields (BR-31..38),
  the breakdown rate (BR-48), VAT-id country prefixes (BR-CO-09), per-category
  VAT identification (BR-*-02/-03/-04 for S/Z/E/AE/K/G/O/L/M), the
  Not-subject-to-VAT restrictions (BR-O-02..07, BR-O-11..14), IGIC / IPSI /
  split payment (BR-AF-*, BR-AG-*, BR-B-01/02), the S/L/M tax calculation with
  the official ±1 tolerance (BR-S/AF/AG-09), tax representative rules
  (BR-18/20/56) and the XRechnung rules BR-DE-2/16/17/18/21/27/28 (incl. the
  Skonto format check).
- Model & readers: seller tax registration identifier (BT-32), the tax
  representative party (BG-11: BT-62/63/69) and document allowance/charge
  reason codes (BT-98/BT-105) are now parsed from both CII and UBL; payment
  terms (BT-20) are read untrimmed so the Skonto line-break rule matches the
  official validator.

- Rule-coverage matrix: [COVERAGE.md](COVERAGE.md) + README summary, generated
  by `tools/generate-coverage.php` against the official EN 16931 **1.3.16** /
  XRechnung **2.5.0** artefacts (`resources/rules-reference.json`, built by
  `tools/build-rules-reference.php`) and drift-guarded by `CoverageDocTest`.
- BR-S/Z/E/AE-07: document-level **charge** VAT rate rules — previously these
  violations were reported under the `-06` (allowance) ids.
- BR-CL-17 now also validates BT-95 / BT-102 / BT-118 (was: BT-151 only), and
  VAT category `B` (split payment) is accepted as a valid UNCL5305 code.

### Changed

- BR-Z/E/AE-05 and BR-Z/E/AE-06/-07 require the VAT rate to be **exactly 0**;
  an absent or negative rate now fails, matching the official asserts.
- BR-{S,Z,E,AE,IC,G,O}-01 follow the official semantics: document-level
  allowances/charges trigger them too, non-S categories require **exactly one**
  matching VAT breakdown group, and an unused Standard-rated group violates
  BR-S-01.
- BR-CL-01 uses the complete official UNTDID 1001 list (62 codes): adds
  261/262/296/308/471–473/500–503, removes the non-official 936.

### Fixed

- The Leitweg-ID rule (BT-10) reports the official id **BR-DE-15** — it was
  mislabelled as BR-DE-1 (which officially requires PAYMENT INSTRUCTIONS,
  BG-16, and remains open).
- The document-level charge decimal rule reports the official id **BR-DEC-05**
  (BT-99) — it was mislabelled as BR-DEC-02, which officially covers the
  allowance base amount (BT-93) and is now implemented as such.
- The invoice currency rule (BT-5) reports the official id **BR-CL-04** (the
  real BR-CL-03 covers every amount's currency attribute and is now
  implemented as such), and the line VAT category rule (BT-151) reports
  **BR-CL-18** (BR-CL-17 covers BT-95/102/118 only).

## [0.1.0] - 2026-07-11

Initial pre-release.

### Added

- A dependency-free, Java-free PHP validator for EN 16931 electronic invoices —
  no KoSIT jar, no JRE, no subprocess.
- `En16931Validator` entry point with `en16931()` and `xrechnung()` rule sets;
  `validate()` auto-detects CII vs UBL by root namespace, plus explicit
  `validateCii()` / `validateUbl()` / `validateModel()`.
- ~100 EN 16931 business rules in pure PHP: presence (BR-*), calculation and
  content (BR-CO-*), category rules (BR-S/Z/E/AE/IC/G/O-*), decimal (BR-DEC-*),
  code-list (BR-CL-*) and the German XRechnung CIUS (BR-DE-*) on top of the
  EN 16931 core.
- CII and UBL readers that read exact decimal strings from the DOM; money as
  integer minor units with BCMath (never a float).
- Full ISO 4217 / ISO 3166 code lists; `ValidationResult` with `isValid()`,
  `fatals()`, `warnings()` and per-rule `Violation`s.
- Proven parity against the official KoSIT validator in CI (Java toolchain used
  only as a dev/CI oracle): zero false positives on the 72-instance XRechnung
  test suite, plus matching verdicts on a negative corpus.

[0.2.0]: https://github.com/john-wink/en16931-php/releases/tag/v0.2.0
[0.1.0]: https://github.com/john-wink/en16931-php/releases/tag/v0.1.0
