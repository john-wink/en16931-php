# Changelog

All notable changes to `john-wink/en16931-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Pre-1.0: the public API may still change between minor versions.

## [Unreleased]

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
