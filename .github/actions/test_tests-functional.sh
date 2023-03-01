#!/bin/bash
set -e -u -x -o pipefail

ATOUM_ADDITIONNAL_OPTIONS=""
if [[ "$CODE_COVERAGE" = true ]]; then
  export COVERAGE_DIR="coverage-functional"
else
  ATOUM_ADDITIONNAL_OPTIONS="--no-code-coverage";
fi

vendor/bin/phpunit -c tests/phpunit.xml --testsuite "GLPI Functional Tests"

unset COVERAGE_DIR
