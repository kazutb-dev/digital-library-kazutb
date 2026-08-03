# ──────────────────────────────────────────────────────────────
#  Digital Library — Makefile
#  Provides unified commands for dev and prod environments
#
#  Dev:   make dev-up | make dev-down | make dev-logs
#  Prod:  make prod-up | make prod-deploy
#  Local: make install | make test | make build
# ──────────────────────────────────────────────────────────────

.PHONY: help install dev-up dev-down dev-logs dev-shell dev-migrate dev-seed \
        prod-up prod-down prod-build prod-deploy \
        test test-unit test-e2e build lint \
	db-status fresh migrate vendor-pdfjs \
	audit-infra audit-schema audit-db audit-all \
	audit-catalog-quality recover-known-volume \
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
	$(MAKE) vendor-pdfjs

build: ## Build frontend assets for production
	npm run build

vendor-pdfjs: ## Refresh the vendored pdf.js reader assets from node_modules
	./scripts/vendor-pdfjs.sh

lint: ## Run pint (PHP) linter
	./vendor/bin/pint --test

test: ## Run PHPUnit tests
	php artisan config:clear --ansi
	php artisan test

test-unit: ## Run only unit tests
	php artisan test --testsuite=Unit

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

dev-fresh: ## Drop and re-migrate + seed in DEV
	docker compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed

# ── PROD environment (Docker) ──────────────────────────────────
prod-build: ## Build production Docker image
	docker compose -f docker-compose.prod.yml build

prod-up: ## Start PROD stack (background)
	docker compose -f docker-compose.prod.yml up -d
	@echo "$(GREEN)PROD stack started.$(RESET)"

prod-down: ## Stop PROD stack
	docker compose -f docker-compose.prod.yml down

prod-deploy: prod-build prod-up ## Build and deploy PROD
	docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
	docker compose -f docker-compose.prod.yml exec app php artisan optimize
	@echo "$(GREEN)PROD deployed and optimized.$(RESET)"

# ── DB helpers ─────────────────────────────────────────────────
db-status: ## Show migration status (local artisan)
	php artisan migrate:status

migrate: ## Run migrations locally
	php artisan migrate

fresh: ## Fresh migrate + seed locally (DEV only)
	php artisan migrate:fresh --seed

# ── Audit helpers (non-destructive) ───────────────────────────
audit-infra: ## Run host/runtime/backup discovery audit
	bash scripts/dev/infra-audit.sh

audit-schema: ## Compare DB objects used by code vs migration-managed tables
	bash scripts/dev/schema-drift-audit.sh

audit-db: ## Probe configured PostgreSQL and collect entity counts when reachable
	bash scripts/dev/db-recovery-audit.sh

audit-catalog-quality: ## Run SQL quality audit script against current DB connection
	bash scripts/dev/run-catalog-quality-audit.sh

recover-known-volume: ## Probe old known Docker volume for legacy postgres data
	bash scripts/dev/recover-known-postgres-volume.sh

audit-all: audit-infra audit-schema audit-db ## Run all recovery audits

# ── Git helpers ────────────────────────────────────────────────
branches-status: ## Show all branches with tracking info
	git branch -vv
	@echo ""
	@echo "$(CYAN)Remotes:$(RESET)"
	git remote -v

# ── System setup (requires sudo) ───────────────────────────────
setup-php84: ## Install PHP 8.4 (Ubuntu 24.04, requires sudo)
	sudo bash scripts/dev/install-php84.sh

setup-docker: ## Install Docker (Ubuntu 24.04, requires sudo)
	sudo bash scripts/dev/install-docker.sh

setup-all: ## Full system setup (PHP 8.4 + Docker, requires sudo)
	$(MAKE) setup-php84
	$(MAKE) setup-docker
	$(MAKE) install
	$(MAKE) build
