#!/usr/bin/env bash
#
# Downloads the official KoSIT validator (Java) and the XRechnung validator
# configuration into build/kosit/ — the dev-only conformance oracle used by
# tests/Feature/ParityTest.php. Not required to use the library; only to prove
# parity against the official validator.
#
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/build/kosit"
VALIDATOR_URL="https://github.com/itplr-kosit/validator/releases/download/v1.5.0/validator-1.5.0-distribution.zip"
CONFIG_URL="https://github.com/itplr-kosit/validator-configuration-xrechnung/releases/download/release-2025-03-21/validator-configuration-xrechnung_3.0.2_2025-03-21.zip"
TESTSUITE_URL="https://github.com/itplr-kosit/xrechnung-testsuite/releases/download/v2026-01-31/xrechnung-3.0.2-testsuite-2026-01-31.zip"

mkdir -p "$DIR"
echo "Downloading KoSIT validator, configuration and test suite ..."
curl -sSL -o "$DIR/validator.zip" "$VALIDATOR_URL"
curl -sSL -o "$DIR/config.zip" "$CONFIG_URL"
curl -sSL -o "$DIR/testsuite.zip" "$TESTSUITE_URL"

echo "Extracting ..."
unzip -o -q "$DIR/validator.zip" -d "$DIR/validator"
unzip -o -q "$DIR/config.zip" -d "$DIR/config"
unzip -o -q "$DIR/testsuite.zip" -d "$DIR/testsuite"

echo "KoSIT validator + XRechnung 3.0.2 configuration + test suite ready in build/kosit/."
