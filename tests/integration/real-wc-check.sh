#!/usr/bin/env bash
# Real WooCommerce check (not run by the unit suite).
# Usage: real-wc-check.sh BASE_URL UNPRICED_ID PRICED_ID [GEO_HEADER]
# Needs a site where GEO_HEADER (default X-RWQA-CC) forces the Geo Core country (loopback only),
# store currency GBP, map ZA->ZAR, PRICED_ID with a ZAR price book, UNPRICED_ID without.
set -euo pipefail
B=$1; U=$2; P=$3; H=${4:-X-RWQA-CC}; fail=0
cur() { curl -s -H "$H: $1" "$B/wp-json/wc/store/v1/products/$2" | python3 -c 'import json,sys;p=json.load(sys.stdin)["prices"];print(p["currency_code"],p["price"])'; }
chk() { if [ "$2" = "$3" ]; then echo "PASS $1 ($2)"; else echo "FAIL $1: got '$2' want '$3'"; fail=1; fi; }
chk "ZA unpriced reports store currency" "$(cur ZA "$U" | cut -d' ' -f1)" GBP
chk "ZA priced reports ZAR" "$(cur ZA "$P" | cut -d' ' -f1)" ZAR
chk "GB unpriced reports GBP" "$(cur GB "$U" | cut -d' ' -f1)" GBP
L=$(curl -s -H "$H: ZA" "$B/wp-json/wc/store/v1/products?include=$U,$P" | python3 -c 'import json,sys;print(" ".join(sorted(str(x["id"])+":"+x["prices"]["currency_code"] for x in json.load(sys.stdin))))')
chk "ZA list" "$L" "$(printf '%s\n' "$U:GBP" "$P:ZAR" | sort | tr '\n' ' ' | sed 's/ $//')"
J=$(curl -s -H "$H: ZA" "$B/?p=$U" -L | grep -o '"priceCurrency":"[A-Z]*"' | sort -u | tr '\n' ' ')
chk "ZA unpriced JSON-LD" "$J" '"priceCurrency":"GBP" '
exit $fail
