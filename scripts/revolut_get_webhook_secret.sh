#!/usr/bin/env bash
# revolut_get_webhook_secret.sh
#
# Fetches the signing_secret for a Revolut sandbox webhook.
#
# Usage:
#   ./revolut_get_webhook_secret.sh              # lists all webhooks with their secrets
#   ./revolut_get_webhook_secret.sh <webhook-id> # fetches secret for a specific webhook
#
# Reads REVOLUT_API_SECRET_KEY and REVOLUT_API_URL from ../.env (if present).

set -e

# --- Load .env ----------------------------------------------------------------
ENV_FILE="$(dirname "$0")/../.env"
if [ -f "$ENV_FILE" ]; then
    while IFS= read -r line; do
        line="${line#"${line%%[![:space:]]*}"}"   # ltrim
        [[ -z "$line" || "$line" == \#* ]] && continue #skips empty lines and comments (starting with #
        [[ "$line" != *=* ]] && continue # skips lines that don't have an "=" sign
        export "${line?}" # exports the variable
    done < "$ENV_FILE"
fi

# --- Resolve config -----------------------------------------------------------
API_URL="${REVOLUT_API_URL:-https://sandbox-merchant.revolut.com}"
SECRET_KEY="${REVOLUT_API_SECRET_KEY:-}"

if [ -z "$SECRET_KEY" ]; then
    echo "Error: REVOLUT_API_SECRET_KEY is not set (check .env)" >&2
    exit 1
fi

# --- Fetch --------------------------------------------------------------------
if [ -n "$1" ]; then
    # Specific webhook — response includes signing_secret
    curl -s -L -X GET "${API_URL}/api/1.0/webhooks/$1" \
        -H 'Accept: application/json' \
        -H "Authorization: Bearer ${SECRET_KEY}"
    echo
else
    # List all webhooks — iterate and fetch each to get signing_secret
    WEBHOOKS=$(curl -s -L -X GET "${API_URL}/api/1.0/webhooks" \
        -H 'Accept: application/json' \
        -H "Authorization: Bearer ${SECRET_KEY}")

    echo "$WEBHOOKS"
    echo

    # Extract IDs and fetch secrets (requires jq; skip gracefully if absent)
    if ! command -v jq &>/dev/null; then
        echo "Tip: install jq to auto-fetch signing_secret for each webhook."
        exit 0
    fi

    echo "$WEBHOOKS" | jq -r '.[].id' | while read -r id; do
        echo "--- Webhook $id ---"
        curl -s -L -X GET "${API_URL}/api/1.0/webhooks/${id}" \
            -H 'Accept: application/json' \
            -H "Authorization: Bearer ${SECRET_KEY}" \
        | jq '{id, url, signing_secret}'
        echo
    done
fi
