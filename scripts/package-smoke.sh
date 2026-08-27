#!/usr/bin/env bash

set -euo pipefail

PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WAFFO_PHP_SMOKE_TMP_ROOT="${TMPDIR:-/tmp}"
SMOKE_DIR="$(mktemp -d "${WAFFO_PHP_SMOKE_TMP_ROOT%/}/waffo-php-package-smoke.XXXXXX")"
trap 'rm -rf "$SMOKE_DIR"' EXIT
ARCHIVE_DIR="$SMOKE_DIR/archive"
PACKAGE_EXTRACT_DIR="$SMOKE_DIR/package"
CONSUMER_DIR="$SMOKE_DIR/consumer"
EXPECTED_SDK_VERSION="$(sed -n "s/.*public const VERSION = '\([^']*\)'.*/\1/p" "$PACKAGE_DIR/src/Version.php")"
if [ -z "$EXPECTED_SDK_VERSION" ]; then
  echo "错误: 无法从 Version.php 读取 SDK 版本。" >&2
  exit 1
fi
mkdir -p "$ARCHIVE_DIR" "$PACKAGE_EXTRACT_DIR" "$CONSUMER_DIR"

cd "$PACKAGE_DIR"
composer validate --strict
composer archive --format=zip --dir="$ARCHIVE_DIR" --file=waffo-php
ARCHIVE_PATH="$ARCHIVE_DIR/waffo-php.zip"
if unzip -Z1 "$ARCHIVE_PATH" | grep -Eq '(^|/)(vendor|tests|docs|scripts|sdk-spec|\.phpunit\.cache)(/|$)|(^|/)(ADR\.md|composer\.lock|\.php-cs-fixer\.cache|openapi\.json)$'; then
  echo "错误: Composer archive 包含开发期文件。" >&2
  exit 1
fi
unzip -q "$ARCHIVE_PATH" -d "$PACKAGE_EXTRACT_DIR"

cd "$CONSUMER_DIR"
composer init --no-interaction --name=waffo/package-smoke
composer config --json repositories.waffo "{\"type\":\"path\",\"url\":\"$PACKAGE_EXTRACT_DIR\",\"options\":{\"symlink\":false}}"
composer require --no-interaction waffo/waffo-php:@dev
php -r 'require "vendor/autoload.php"; if (Waffo\Version::VERSION !== $argv[1]) { fwrite(STDERR, "SDK version mismatch\n"); exit(1); } echo Waffo\Version::SDK_HEADER, PHP_EOL;' "$EXPECTED_SDK_VERSION"
