#!/usr/bin/env bash
# 可重跑的容器內靜態檢查：PHP 語法、PHPStan、PHP_CodeSniffer。
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOOLS_DIR="$APP_DIR/tools"
PHPSTAN_VERSION="2.2.17"
PHPSTAN_FILE="$TOOLS_DIR/phpstan-${PHPSTAN_VERSION}.phar"
PHPSTAN_URL="https://github.com/phpstan/phpstan/releases/download/${PHPSTAN_VERSION}/phpstan.phar"
PHPSTAN_SHA256="46e0eb600188e5f6945b846e427614ad531ca575dac2659d307c3f2b7e1d6c3e"
PHPCS_VERSION="4.0.4"
PHPCS_FILE="$TOOLS_DIR/phpcs-${PHPCS_VERSION}.phar"
PHPCS_URL="https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/download/${PHPCS_VERSION}/phpcs.phar"
PHPCS_SHA256="4b010cd21d8bc8a17e2504792e3c77ef8259126f24caf7eafe7eddef1fa871e9"

download_verified_phar() {
    local name="$1" file="$2" url="$3" expected_sha256="$4" actual_sha256 temporary
    mkdir -p "$TOOLS_DIR"

    if [[ -e "$file" ]]; then
        actual_sha256="$(sha256sum "$file" | awk '{print $1}')"
        if [[ "$actual_sha256" != "$expected_sha256" ]]; then
            printf 'ERROR: %s checksum mismatch: expected %s, got %s\n' "$name" "$expected_sha256" "$actual_sha256" >&2
            return 1
        fi
        printf '%s %s: verified cached PHAR\n' "$name" "${file##*/}"
        return 0
    fi

    temporary="${file}.download"
    printf '%s: downloading %s\n' "$name" "$url"
    # A large official PHAR can outlast an interrupted Docker CLI session.  Keep only
    # an untrusted partial download and resume it; it is never executed or renamed
    # until the full SHA-256 matches the pinned value below.
    curl --continue-at - --fail --location --retry 3 --silent --show-error --output "$temporary" "$url"
    actual_sha256="$(sha256sum "$temporary" | awk '{print $1}')"
    if [[ "$actual_sha256" != "$expected_sha256" ]]; then
        printf 'ERROR: %s checksum mismatch: expected %s, got %s; refusing to execute\n' "$name" "$expected_sha256" "$actual_sha256" >&2
        return 1
    fi
    mv "$temporary" "$file"
    printf '%s %s: downloaded and checksum verified\n' "$name" "${file##*/}"
}

cd "$APP_DIR"
download_verified_phar "PHPStan ${PHPSTAN_VERSION}" "$PHPSTAN_FILE" "$PHPSTAN_URL" "$PHPSTAN_SHA256"
download_verified_phar "PHP_CodeSniffer ${PHPCS_VERSION}" "$PHPCS_FILE" "$PHPCS_URL" "$PHPCS_SHA256"

# Docker 的 PHP image 不保證含 git。可用時以 Git 的追蹤清單為準；否則 find 是保守
# superset，仍涵蓋每個追蹤 PHP 檔，並排除執行時才產生的 config.inc.php。
if command -v git >/dev/null 2>&1; then
    mapfile -d '' -t PHP_FILES < <(git ls-files -z -- '*.php' ':!src/config.inc.php')
else
    mapfile -d '' -t PHP_FILES < <(find public scripts src -type f -name '*.php' ! -path 'src/config.inc.php' -print0 | sort -z)
fi

printf 'STEP 1/3 php -l: %d PHP files\n' "${#PHP_FILES[@]}"
for file in "${PHP_FILES[@]}"; do
    if ! php -l "$file"; then
        printf 'FAIL: php -l failed for %s\n' "$file" >&2
        exit 1
    fi
done
printf 'PASS: php -l: %d PHP files\n' "${#PHP_FILES[@]}"

printf 'STEP 2/3 PHPStan %s (level from phpstan.neon.dist)\n' "$PHPSTAN_VERSION"
if ! php "$PHPSTAN_FILE" analyse --configuration=phpstan.neon.dist --no-progress --error-format=table; then
    printf 'FAIL: PHPStan\n' >&2
    exit 1
fi
printf 'PASS: PHPStan\n'

printf 'STEP 3/3 PHP_CodeSniffer %s\n' "$PHPCS_VERSION"
if ! php "$PHPCS_FILE" --standard=phpcs.xml.dist --report=full public scripts src; then
    printf 'FAIL: PHP_CodeSniffer\n' >&2
    exit 1
fi
printf 'PASS: PHP_CodeSniffer\n'
printf 'SUMMARY PASS: php -l, PHPStan, PHP_CodeSniffer\n'
