#!/bin/bash
# Fires N booking requests for one slot at the same moment and prints the
# status-code histogram. Expected: exactly one 201, the rest 409.
#
# `php artisan serve` handles requests one at a time, so run the API with
# real worker processes first (from backend/):
#   PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8001 -t public public/index.php
#
# Usage: scripts/concurrency-check.sh SLOT_ID [N] [BASE_URL]
set -euo pipefail

SLOT_ID="$1"
N="${2:-20}"
BASE_URL="${3:-http://127.0.0.1:8001}"

seq "$N" | xargs -P "$N" -I{} curl -s -o /dev/null -w "%{http_code}\n" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"slot_id\":$SLOT_ID,\"customer_name\":\"Racer {}\",\"customer_email\":\"racer{}@example.com\"}" \
  "$BASE_URL/api/bookings" | sort | uniq -c
