# ──────────────────────────────────────────────────────────────
#  Digital Library — Makefile
#  Provides unified commands for dev and prod environments
#
#  Dev:   make dev-up | make dev-down | make dev-logs
#  Prod:  make prod-up | make prod-deploy
#  Local: make install | make test | make build
# ──────────────────────────────────────────────────────────────

.PHONY: help install dev-up dev-down dev-logs dev-shell dev-migrate dev-seed dev-fresh \
        prod-up prod-down prod-build prod-deploy \
        test test-unit test-e2e test-all build lint \
	db-status fresh migrate verify-pdfjs \
        branches-status

# ── Colors ─────────────────────────────────────────────────────
CYAN  := \033[0;36m
GREEN := \033[0;32m
RESET := \033[0m

# ── Defaults ───────────────────────────────────────────────────
help: ## Show this help
	@echo ""
	@echo "$(CYAN)Digital Library — Makefile$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-22s$(RESET) %s\n", $$1, $$2}'
	@echo ""

# ── Local (no Docker) ──────────────────────────────────────────
install: ## Install all dependencies (composer + npm)
	composer install
	npm ci
	$(MAKE) verify-pdfjs

build: ## Build frontend assets for production
	npm run build

verify-pdfjs: ## Verify the committed pdf.js reader assets match node_modules
	@test -s public/vendor/pdfjs/build/pdf.min.mjs
	@test -s public/vendor/pdfjs/build/pdf.worker.min.mjs
	@test -d public/vendor/pdfjs/cmaps
	@test -d public/vendor/pdfjs/standard_fonts
	@node -e "const fs=require('fs'); const expected=require('./node_modules/pdfjs-dist/package.json').version; const actual=fs.readFileSync('public/vendor/pdfjs/VERSION','utf8').trim(); if(actual!==expected){console.error('Vendored pdf.js '+actual+' does not match dependency '+expected); process.exit(1)}; console.log('Vendored pdf.js '+actual+' verified')"

lint: ## Run pint (PHP) linter
	./vendor/bin/pint --test

test: ## Run PHPUnit tests
	php vendor/bin/phpunit --configuration phpunit.xml

test-unit: ## Run only unit tests
	php vendor/bin/phpunit --configuration phpunit.xml --testsuite=Unit

test-e2e: ## Run Playwright e2e tests
	npm run test:e2e

test-all: lint test test-e2e ## Run all quality gates

# ── DEV environment (Docker) ───────────────────────────────────
dev-up: ## Start DEV stack (docker-compose.dev.yml)
	docker compose -f docker-compose.dev.yml up --build -d
	@echo "$(GREEN)DEV stack started. App: http://localhost:8080 | Vite: http://localhost:5173$(RESET)"

dev-down: ## Stop DEV stack
	docker compose -f docker-compose.dev.yml down

dev-logs: ## Follow DEV logs
	docker compose -f docker-compose.dev.yml logs -f app frontend-dev

dev-shell: ## Open shell in DEV app container
	docker compose -f docker-compose.dev.yml exec app bash

dev-migrate: ## Run migrations in DEV container
	docker compose -f docker-compose.dev.yml exec app php artisan migrate

dev-seed: ## Seed database in DEV container
	docker compose -f docker-compose.dev.yml exec app php artisan db:seed

dev-fresh: ## Disabled: use the guarded isolated PostgreSQL test runner
	@echo "Direct migrate:fresh targets are disabled. Use TEST_DB_DATABASE=<name>_test ./scripts/dev/test-postgres.sh."
	@exit 64

# ── PROD environment (Docker) ──────────────────────────────────
prod-build: ## Build production Docker image
	docker compose -f docker-compose.prod.yml build

prod-up: ## Start PROD stack (background)
	docker compose -f docker-compose.prod.yml up -d
	@echo "$(GREEN)PROD stack started.$(RESET)"

prod-down: ## Stop PROD stack
	docker compose -f docker-compose.prod.yml down

prod-deploy: prod-build ## Build only; production promotion follows the approved recovery/deployment runbook
	@echo "Production image built. No migrations were applied and no service was started."
	@echo "Validate a backup restore, schema compatibility, effective runtime DB and rollback plan before make prod-up."

# ── DB helpers ─────────────────────────────────────────────────
db-status: ## Show migration status (local artisan)
	php artisan migrate:status

migrate: ## Run migrations locally
	php artisan migrate

fresh: ## Disabled: use the guarded isolated PostgreSQL test runner
	@echo "Direct migrate:fresh targets are disabled. Use TEST_DB_DATABASE=<name>_test ./scripts/dev/test-postgres.sh."
	@exit 64

# ── Git helpers ────────────────────────────────────────────────
branches-status: ## Show all branches with tracking info
	git branch -vv
	@echo ""
	@echo "$(CYAN)Remotes:$(RESET)"
	git remote -v
