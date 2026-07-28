#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

FILTER="Reservation(Read|Mutate)|InternalCirculation(CheckoutReturn|Readback)|InternalCopy(Read|Mutation|Retirement)"

run_php_tests() {
	if php artisan list --raw 2>/dev/null | grep -q '^test$'; then
		php artisan test --filter "$FILTER"
	elif [ -f vendor/bin/phpunit ]; then
		php vendor/bin/phpunit --filter "$FILTER"
	else
		echo "PHPUnit binary not found at vendor/bin/phpunit."
		echo "Run: composer install"
		exit 127
	fi
}

if command -v php >/dev/null 2>&1; then
	run_php_tests
elif command -v docker >/dev/null 2>&1 && docker compose ps app >/dev/null 2>&1; then
	docker compose exec -T app sh -lc '
		if php artisan list --raw 2>/dev/null | grep -q "^test$"; then
			php artisan test --filter "$1"
		elif [ -f vendor/bin/phpunit ]; then
			php vendor/bin/phpunit --filter "$1"
		else
			echo "PHPUnit binary not found at vendor/bin/phpunit inside container."
			echo "Run: docker compose exec -T app composer install"
			exit 127
		fi
	' sh "$FILTER"
else
	echo "PHP runtime not found."
	echo "Run with local PHP, or start containers and run:"
	echo "  docker compose up -d"
	echo "  docker compose exec -T app php artisan test --filter \"$FILTER\""
	exit 127
fi
