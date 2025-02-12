#!/bin/bash
set -e -u -x -o pipefail

# Install browsers
echo "Installing browsers..."
npx playwright install chromium --with-deps
echo "Browsers installed"
