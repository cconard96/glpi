#!/bin/bash
set -e -u -x -o pipefail

# Type checks
npx tsc -p tsconfig.json --noEmit

# Playwright
npx playwright test --trace on --workers=1
