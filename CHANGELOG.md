# Changelog

All notable changes to `john-wink/en16931-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Pre-1.0: the public API may still change between minor versions.

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

[0.1.0]: https://github.com/john-wink/en16931-php/releases/tag/v0.1.0
