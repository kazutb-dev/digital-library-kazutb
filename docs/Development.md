# Development Guide

## Prerequisites

| Tool | Required | Install |
|------|----------|---------|
| PHP | **8.4** | `sudo bash scripts/dev/install-php84.sh` |
| Composer | 2.x | Already installed |
| Node.js | **22 LTS** | `nvm install 22 && nvm alias default 22` |
| npm | 10.x | Bundled with Node 22 |
| Docker | latest | `sudo bash scripts/dev/install-docker.sh` |
| Docker Compose | plugin v2 | Bundled with Docker above |

### NVM quick setup (if not done)

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
source ~/.bashrc
nvm install 22
nvm alias default 22
```

### Verify environment

```bash
php -v        # must be 8.4.x
node -v       # must be v22.x
composer -V
docker --version
docker compose version
```

## First-time local setup (with Docker)

```bash
# 1. Clone repo and enter
git clone git@github.com:almazmurat/library.git
cd library
git checkout develop          # always work on develop or feature/*

# 2. Configure environment
cp .env.dev.example .env      # edit POSTGRES_PASSWORD, APP_KEY

# 3. Generate APP_KEY
php artisan key:generate --show   # paste result into .env APP_KEY=

# 4. Start DEV stack
make dev-up                   # or: docker compose -f docker-compose.dev.yml up --build -d

# 5. Run migrations
make dev-migrate

# 6. Open
# App:   http://localhost:8080
# Vite:  http://localhost:5173
```

## Local setup without Docker

Useful for CI-like local runs with SQLite.

```bash
cp .env.example .env          # uses SQLite defaults
php artisan key:generate
composer install
npm ci
php artisan migrate
npm run dev &                  # vite dev server
php artisan serve              # laravel dev server on :8000
```

Or use the composer shortcut:

```bash
composer dev
```

## Branch workflow

```
origin/main   ─── production releases only (tagged vX.Y.Z)
      │
      └── develop ─── integration branch (all features merge here first)
               │
               ├── feature/my-feature    (new work)
               ├── fix/my-bugfix         (bugfixes)
               └── hotfix/critical-fix   (merges to main AND develop)
```

### Starting a feature

```bash
git checkout develop
git pull origin develop
git checkout -b feature/my-feature
# ... work ...
git push -u origin feature/my-feature
# Open PR: feature/my-feature → develop
```

### Releasing

```bash
git checkout develop
git pull
git checkout -b release/v1.2.0
# bump version, changelog...
git tag v1.2.0
git push origin v1.2.0        # triggers deploy.yml
# merge release/v1.2.0 → main AND → develop
```

## Running tests

```bash
make test              # PHPUnit via artisan
make test-unit         # unit suite only
make test-e2e          # Playwright smoke tests
make test-all          # lint + PHPUnit + Playwright
```

## Quality gates (same as CI)

```bash
composer qa:ci         # local CI gate script
composer lint          # pint formatting check
npm run build          # must succeed
```

## Useful commands

```bash
php artisan route:list --path=api       # list API routes
php artisan model:show Book             # model details
php artisan migrate:status
php artisan tinker                      # REPL

make dev-shell                          # bash in dev container
make dev-logs                           # follow logs
```
