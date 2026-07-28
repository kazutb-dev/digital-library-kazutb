#!/bin/bash
# ──────────────────────────────────────────────────────────────
#  Install PHP 8.4 on Ubuntu 24.04 (Noble)
#  Requires sudo. Run: sudo bash scripts/dev/install-php84.sh
# ──────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== Installing PHP 8.4 on Ubuntu 24.04 (Noble) ==="

# Add Ondrej PPA (standard source for PHP on Ubuntu)
add-apt-repository -y ppa:ondrej/php
apt-get update -q

apt-get install -y \
  php8.4 \
  php8.4-cli \
  php8.4-fpm \
  php8.4-pgsql \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-bcmath \
  php8.4-curl \
  php8.4-zip \
  php8.4-intl \
  php8.4-opcache \
  php8.4-tokenizer \
  php8.4-sqlite3 \
  php8.4-pcov

# Make php8.4 the default CLI
update-alternatives --set php /usr/bin/php8.4 || true

php -v
echo "=== PHP 8.4 installed successfully ==="
echo ""
echo "Next step: composer install"
