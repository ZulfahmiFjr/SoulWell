#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
compiler="${RUSTC:-rustc}"
mkdir -p tmp/native
"$compiler" native/roll.rs --edition 2021 --test -C opt-level=2 -o tmp/native/roll-tests
tmp/native/roll-tests
"$compiler" native/roll.rs --crate-type cdylib --edition 2021 -C opt-level=3 -C panic=abort -C strip=symbols -o tmp/native/libsoulwell_roll.so
cp tmp/native/libsoulwell_roll.so resources/native/libsoulwell_roll.so
sha256sum resources/native/libsoulwell_roll.so | cut -d ' ' -f 1 > resources/native/roll.sha256
