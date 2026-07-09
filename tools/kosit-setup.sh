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

mkdir -p "$DIR"
echo "Downloading KoSIT validator ..."
curl -sSL -o "$DIR/validator.zip" "$VALIDATOR_URL"
curl -sSL -o "$DIR/config.zip" "$CONFIG_URL"

echo "Extracting ..."
unzip -o -q "$DIR/validator.zip" -d "$DIR/validator"
unzip -o -q "$DIR/config.zip" -d "$DIR/config"

echo "KoSIT validator + XRechnung 3.0.2 configuration ready in build/kosit/."
