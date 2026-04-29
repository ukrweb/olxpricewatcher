#!/bin/sh
set -eu

echo "Starting OLX price watcher worker"
FROM="${OLX_CHECK_INTERVAL_FROM_SECONDS:-300}"
TO="${OLX_CHECK_INTERVAL_TO_SECONDS:-600}"

if [ "$FROM" -gt "$TO" ]; then
    echo "Invalid worker interval: FROM must be <= TO"
    exit 1
fi

echo "Check interval range: $FROM-$TO seconds"

while true; do
    echo "$(date -Iseconds) Running price check"
    php bin/console app:check-prices || true
    SLEEP_SECONDS=$(awk -v min="$FROM" -v max="$TO" 'BEGIN{srand(); print int(min + rand() * (max - min + 1))}')
    echo "$(date -Iseconds) Sleeping for $SLEEP_SECONDS seconds"
    sleep "$SLEEP_SECONDS"
done
