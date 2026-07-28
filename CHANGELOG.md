# Changelog

All notable changes to this project are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- `docker-compose.dev.yml` — dedicated dev stack (port 8080, Vite HMR on 5173)
- `docker-compose.prod.yml` — dedicated prod stack (no bind-mount, no host Postgres port)
- `.env.dev.example` — dev environment template
- `.env.prod.example` — production environment template
- `Makefile` — unified `make dev-up`, `make prod-deploy`, `make test` etc.
- `docs/Architecture.md` — stack and domain boundary documentation
- `docs/Database.md` — migration table, catalog audit queries
- `docs/Development.md` — prerequisites, setup, branch workflow
- `docs/Deployment.md` — DEV and PROD deploy runbooks
- `docs/Release.md` — release checklist and versioning
- `.github/workflows/deploy.yml` — production deploy on `v*` tag
- `scripts/dev/install-php84.sh` — PHP 8.4 install script (sudo, Ubuntu 24.04)
- `scripts/dev/install-docker.sh` — Docker + postgresql-client install script
- `develop` branch as integration branch (branched from `origin/main`)

### Changed
- `.github/workflows/ci.yml` — CI now runs on both `main` and `develop` branches
- `.gitignore` — added `graphify-out/`, `app/graphify-out/`, mutation/chaos/perf run artifacts, `.env.dev`, `.env.prod`

---

## [Wave 2 — pre-openclaw snapshot]

Wave 2 work: librarian panels, admin modules, scientific works, notifications, audit.  
Preserved in branch `wave2-wip-before-openclaw`.

---

## [Wave 1 — 2026-03-xx]

Shell IA, /account retirement, trilingual switcher, footer expansion.
