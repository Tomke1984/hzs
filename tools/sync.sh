#!/bin/bash
# Houdt je lokale kopie automatisch up-to-date met de testbranch op GitHub (macOS/Linux/Git Bash).
# Gebruik: ./tools/sync.sh [seconden] [branch]   (standaard elke 60 seconden, branch "dev")
# Stoppen: Ctrl+C
INTERVAL="${1:-60}"
BRANCH="${2:-dev}"
cd "$(dirname "$0")/.." || exit 1

git fetch origin "$BRANCH" -q || { echo "Kan branch '$BRANCH' niet ophalen."; exit 1; }
git checkout "$BRANCH" -q 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH" -q || exit 1

echo "Sync gestart op branch '$BRANCH' in $(pwd), elke ${INTERVAL}s. Stoppen met Ctrl+C."
while true; do
  OUT=$(git pull --ff-only origin "$BRANCH" 2>&1)
  if [ $? -ne 0 ]; then
    echo "$(date '+%H:%M:%S') Pull mislukt (lokale wijzigingen?): $OUT"
  elif ! echo "$OUT" | grep -q "Already up to date"; then
    echo "$(date '+%H:%M:%S') Bijgewerkt:"; echo "$OUT"
  fi
  sleep "$INTERVAL"
done
