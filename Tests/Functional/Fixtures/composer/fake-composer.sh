#!/bin/sh
# Test double for the composer binary: answers the three read-only commands of
# ComposerCliCollector with canned JSON. Exits 1 for `audit` like composer does
# when advisories exist. Anything else is an error (must never be called).
DIR="$(cd "$(dirname "$0")" && pwd)"
case "$*" in
  *" audit "*|"audit "*)
    cat "$DIR/audit.json"; exit 1 ;;
  *"--minor-only"*)
    cat "$DIR/outdated-minor.json"; exit 0 ;;
  *" outdated "*|"outdated "*)
    cat "$DIR/outdated.json"; exit 0 ;;
  *)
    echo "fake-composer: unexpected arguments: $*" >&2; exit 64 ;;
esac
