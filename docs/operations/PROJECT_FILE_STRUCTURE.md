# Project File Structure & Operational Data Policy

Status: active · Last updated: 2026-08-28 · Owner: platform/operations

This document defines what belongs inside the Git repository and what must live
outside it. It was written during the repository cleanup that preceded the
MARC-SQL recovery migration.

---

## 1. The rule

**The repository holds source. It does not hold data.**

If a file is produced by running the system — rather than by writing the system —
it belongs outside the repository, under `/home/admtutor/library-data/`.

---

## 2. Repository contents

`/home/admtutor/projects/library` contains:

| Path | Purpose |
|---|---|
| `app/` | Laravel application code |
| `bootstrap/` | Framework bootstrap (`bootstrap/cache/` is generated, ignored) |
| `config/` | Configuration |
| `database/` | Migrations, factories, seeders |
| `design-system/` | Design tokens and system assets |
| `docker/` | Container build assets (nginx, php-fpm, supervisor) |
| `docs/` | Project documentation |
| `lang/` | Translation catalogues (en / kk / ru) |
| `public/` | Web root; `public/build/` and `public/storage` are generated |
| `resources/` | Blade views, JS/CSS sources |
| `routes/` | Route definitions |
| `scripts/` | Operational and development scripts |
| `ssl/` | TLS material (ignored; provisioned per environment) |
| `storage/` | **Laravel runtime directory — never relocate** |
| `tests/` | Test suites |
| `mutation-tests/`, `perf-tests/` | QA harnesses (their `*.log` output is ignored) |

Plus standard project files: `artisan`, `composer.json`, `composer.lock`,
`package.json`, `package-lock.json`, `Dockerfile`, `docker-compose*.yml`,
`phpunit*.xml`, `playwright*.config.ts`, `vite.config.js`, `Makefile`,
`README.md`, `CHANGELOG.md`, `PROJECT_CONTEXT.md`, `design.md`,
`.gitignore`, `.gitattributes`, `.editorconfig`, and the `.env*.example`
templates.

### `storage/` is runtime, not source

`storage/` is a Laravel runtime directory and must never be moved or bulk-cleaned.
It holds production user content:

- `storage/app/private/digital-materials/` — **published electronic materials**
  served to readers. Files here are referenced by `electronic_materials.file_path`.
  Deleting one breaks a live catalogue record.
- `storage/app/private/repository/`, `.../official-reports/`,
  `.../external-resource-contracts/` — production documents.
- `storage/app/backups/`, `storage/app/recovery/`, `storage/backups/` — operational
  output; ignored by Git, safe to prune deliberately, never as a side effect.
- `storage/framework/` — regenerable caches, sessions, compiled views.
- `storage/logs/` — application logs.

---

## 3. The repository must NOT contain

- database dumps or backups (`*.bak`, `*.dump`, `*.sql.gz`)
- MARC migration packages (`KazUTB_MARC_FULL_*.zip`)
- generated audit or compliance reports
- temporary screenshots and scratch images
- Playwright/PHPUnit results (`test-results/`, `playwright-report/`, `.phpunit.result.cache`)
- production user documents or source PDFs awaiting ingestion
- any large operational artifact

These are enforced by `.gitignore`. If a new class of artifact appears, add a rule
rather than committing it.

---

## 4. External operational path

All operational data lives under:

```
/home/admtutor/library-data/
```

| Directory | Contents |
|---|---|
| `imports/` | Inbound migration packages awaiting import, by source and date |
| `imports/marcsql/<YYYY-MM-DD>/` | A dated MARC-SQL delivery (zip + `.sha256`) |
| `backups/postgres/` | PostgreSQL dumps |
| `backups/marcsql/` | SQL Server backups taken by us |
| `audit/` | Audit and forensic output |
| `staging/` | Scratch space for in-progress transformations |
| `quarantine/` | Files held back pending review (suspect uploads, failed scans) |
| `reports/` | Generated operational and compliance reports |
| `legacy-files/` | Original legacy artifacts retained for provenance |

`/home/admtutor/library-data/README.md` documents this in place. That path is
deliberately outside Git.

---

## 5. MARC recovery import location

MARC-SQL recovery packages are staged at:

```
/home/admtutor/library-data/imports/marcsql/<YYYY-MM-DD>/
```

Each delivery must contain the archive and its checksum sidecar, and the checksum
must be verified with `sha256sum -c` **before** the package is unpacked.

---

## 6. The deprecated MARC importer

`scripts/deprecated/import-marcsql-stream.php` is retained for forensic reference
only. It is destructive — it deletes the entire catalogue before importing, and it
drops most MARC and copy attributes. It caused the 2026-08-12 data loss.

It now refuses to run unless `MARC_LEGACY_IMPORTER_ACKNOWLEDGE_DATA_LOSS` is set to
an explicit acknowledgement string, on top of its pre-existing confirmation and
non-production guards.

**The supported importer is:**

```bash
php artisan marc:import-catalog
```

It is non-destructive (upserts through `marc_import_records`) and maps
substantially more of the source.

---

## 7. Cleaning generated artifacts safely

```bash
# Test output — regenerated on the next run.
rm -rf test-results playwright-report .phpunit.result.cache

# Framework caches — regenerated on demand.
php artisan optimize:clear
```

Never use `git clean -fd`, `git reset --hard`, or `git restore .` on this
repository: the working tree routinely carries uncommitted work, and ignored
paths include live runtime data.

---

## 8. Environment files

| File | Git | Notes |
|---|---|---|
| `.env` | ignored | Live secrets. Never commit. |
| `.env.example` | tracked | Template |
| `.env.dev.example` | tracked | Template |
| `.env.prod.example` | tracked | Template |
| `.env.bak-*` | **must not live in the repo** | Credential-bearing backups belong in `library-data/legacy-files/env-backups/`, mode `600` |

Note: `.gitignore` uses `.env.*` as a blanket rule, so every new
`.env.<name>.example` template needs an explicit `!` negation added alongside the
existing ones, or it will be silently ignored.
