#!/bin/bash
# Houdt je lokale kopie automatisch up-to-date met GitHub (macOS/Linux/Git Bash).
# Gebruik: ./tools/sync.sh [seconden]   (standaard elke 60 seconden)
# Stoppen: Ctrl+C
INTERVAL="${1:-60}"
cd "$(dirname "$0")/.." || exit 1

echo "Sync gestart in $(pwd), elke ${INTERVAL}s. Stoppen met Ctrl+C."
while true; do
  OUT=$(git pull --ff-only 2>&1)
  if [ $? -ne 0 ]; then
    echo "$(date '+%H:%M:%S') Pull mislukt (lokale wijzigingen?): $OUT"
  elif ! echo "$OUT" | grep -q "Already up to date"; then
    echo "$(date '+%H:%M:%S') Bijgewerkt:"; echo "$OUT"
  fi
  sleep "$INTERVAL"
done
