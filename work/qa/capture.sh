#!/usr/bin/env bash
# QA capture entrypoint. Wraps capture.js with sane defaults and friendly errors.
#
# Usage:
#   work/qa/capture.sh <label>            # captures from default sandbox URL with cb=<label>
#   work/qa/capture.sh <label> --url=URL  # override sandbox URL
#
# Examples:
#   work/qa/capture.sh v3
#   work/qa/capture.sh v3 --url=http://127.0.0.1:8765/
#
# Output: <repo>/work/screenshots/<label>/
#   _full.png         — full-page stitched screenshot (puppeteer fullPage)
#   _manifest.json    — capture metadata + per-anchor result log
#   page-NN.png       — viewport screenshots scrolled to per-page anchor (from anchors.json)
#   scroll-NN-yY.png  — overlapping 1200-px viewport bands stepping every 1100 px
#
# Idempotent: re-running with the same label wipes the output dir first.
# Anchor manifest: edit work/qa/anchors.json to add/change per-page anchor strategies.
#
# Dependencies: bash, node, puppeteer (resolved at /home/saad/phpstorm-projects/clm/node_modules/puppeteer)
# Sandbox must already be served at the URL (default http://127.0.0.1:8765/).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

if [[ $# -lt 1 ]]; then
  echo "usage: $0 <label> [--url=URL]" >&2
  exit 2
fi

LABEL="$1"
shift
if [[ ! "$LABEL" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo "error: label must match [A-Za-z0-9._-]+, got: $LABEL" >&2
  exit 2
fi

# Quick sanity: anchors.json must parse
if ! node -e "JSON.parse(require('fs').readFileSync('$SCRIPT_DIR/anchors.json','utf8'))" 2>/dev/null; then
  echo "error: $SCRIPT_DIR/anchors.json is not valid JSON" >&2
  exit 2
fi

# Quick sanity: puppeteer reachable
if ! node -e "require('/home/saad/phpstorm-projects/clm/node_modules/puppeteer')" 2>/dev/null; then
  echo "error: puppeteer not found at /home/saad/phpstorm-projects/clm/node_modules/puppeteer" >&2
  echo "fix:   cd /home/saad/phpstorm-projects/clm && npm i puppeteer" >&2
  exit 2
fi

exec node "$SCRIPT_DIR/capture.js" "$LABEL" "$@"
