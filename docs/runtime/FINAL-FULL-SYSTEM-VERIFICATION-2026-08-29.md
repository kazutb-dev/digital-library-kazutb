# KazUTB Digital Library — Final Full-System Verification

Дата аудита: 2026-08-29 UTC  
Проект: `/home/admtutor/projects/library`  
Live: `https://elibrary.kaztbu.edu.kz`  
Ветка / HEAD: `main` / `01f9041ff7256e9b607058e5923ba3566844226a`  
База: `digital_library_recovered`

> **СТАТУС ДОКУМЕНТА: DRAFT / FINAL GATES PENDING.** Это фактический отчёт по уже полученным доказательствам, а не декларация готовности. Плейсхолдеры `PENDING` должны быть заменены только результатами свежих проверок после заморозки всех изменений.

## 1. Итог на текущем этапе

Application-level P0/P1, найденные в ходе аудита, исправлены и закрыты targeted-тестами, включая реальные PostgreSQL acceptance и concurrency-пробы в disposable БД. Однако закрывающий verdict сейчас запрещён по двум причинам:

1. Открыт `QG-CRED-01` уровня **P1**: пользователь раскрыл в чате пароль действующей AD-linked учётной записи, имеющей canonical роль `admin`. Секрет в этом документе намеренно не воспроизводится. Ротация выполняется во внешнем Active Directory и пока не подтверждена.
2. Финальная frontend-сборка завершена, но второй полный PHPUnit с нуля, production recreate/deploy и post-deploy smoke ещё не завершены.

Текущий обязательный статус до закрытия этих гейтов: **BLOCKED**.

`FINAL_VERDICT: [PENDING — после финальных гейтов вставить ровно один допустимый verdict; при открытом QG-CRED-01 он обязан остаться BLOCKED]`

## 2. Метод и границы доказательств

- Повторный MARC import, `migrate:fresh`, `db:wipe`, второй stack, branch и worktree не создавались.
- Live PostgreSQL использовался только для read-only проверок, безопасных служебных операций (`ANALYZE`, cache warm-up) и health-проверок.
- Мутирующая единая acceptance-цепочка и настоящие параллельные транзакции выполнялись в disposable PostgreSQL БД; контрольные префиксы после тестов отсутствовали, БД удалена.
- PHPUnit isolation отдельно доказал `APP_ENV=testing`, SQLite `:memory:` и недоступный production PostgreSQL port.
- AD-пароль пользователя не применялся. Проверялись health, состояние провайдера, mapping/error history и существующие события успешной аутентификации.
- Browser screenshot/E2E не выдан за PASS: локальный Chromium не стартовал из-за отсутствующих системных библиотек. Статические geometry-тесты, server-side crawls и read-only HTTP-проверки приведены отдельно.
- Результаты разных наборов тестов пересекаются; количества ниже нельзя суммировать в «общее число тестов».

## 3. Fresh baseline и сохранность recovery

### Runtime baseline

| Проверка | Факт |
|---|---|
| Git | `main`, HEAD не менялся; исходный dirty worktree сохранён |
| Docker | `library-app-1` и `library-postgres-1` были healthy; bind mount проекта в `/app`; restart count 0 на baseline |
| Laravel / PHP | Laravel 13.25; PHP 8.4.24 в app container |
| PostgreSQL | PostgreSQL 18.4; current DB `digital_library_recovered`; app user `library_app` |
| Migrations | 76/76, все `Ran`, дублей нет; четыре recovery migration применены по одному разу; две операционные migration batch 11 добавили пустые acquisition/inventory структуры |
| Routes | 490 source routes; 371 named, 119 unnamed; 0 duplicate name; 0 duplicate method+URI |
| RBAC | 8 actual DB roles; 213 permissions; 0 users без роли, 0 multi-role users, 0 direct grants, 0 orphan grants |
| Queue | workers `integrations,reports,default`; `jobs=0`, `failed_jobs=0` |
| Scheduler | 11 зарегистрированных schedules; reservation/circulation/messages/integrations/reports/DQ/prune jobs присутствуют |
| Disk | 194 GB total, 71 GB used, 114 GB free (39%); inode use 10% |

### Данные

| Сущность | Baseline |
|---|---:|
| `bibliographic_records` | 9 628 |
| `book_copies` | 51 074 |
| `legacy_marc_records` | 9 612 |
| `legacy_marc_copies` | 51 219 |
| `legacy_marc_fields` | 223 263 |
| `contributors` | 6 860 |
| `subjects` | 106 |
| KSU books / entries / exact items / conflicts | 1 / 20 / 2 408 / 4 809 |
| Quarantine | 173 open: 172 orphan + 1 duplicate inventory case |
| Import conflicts | 3 open |
| Users / reader profiles | 18 / 8 |
| Loans / reservations / visits | 0 / 0 / 0 |
| Inventory sessions / acquisition batches | 0 / 0 |
| DQ issues | 133 608 total; 81 227 open; 52 381 resolved |
| Electronic materials | 5 |

Recovery reconciliation is exact: `51 219 = 2 408 exact KSU + 4 809 KSU conflicts + 44 002 without KSU`; overlap is zero. Historical conflicts were not artificially converted to exact matches.

The required repeat DQ scan completed as `DQS-20260829-205620-CB3N`: 51 074 copies scanned, 42 343 issues found, 0 created, 0 reopened, 0 auto-resolved, 140 555 ms. Rule `copy.location.missing` remains `open=0`, `resolved=43 829`; the former false-positive population was not recreated.

Control records remained unchanged at the checked checkpoint:

- Manual record `9565`: title, author, publisher, year, ISBN, UDC, draft flag and `updated_at` matched baseline.
- Legacy record `6710` / `DOC 12514`: author Лунгу К.Н., Айрис-пресс, М., 2007, ISBN, UDC and control number matched source; copy `legacy_inv_id=50526`, inventory `4404`, sigla `KSTLIB`, registration `2025-02-24` matched source.
- `fund_raw` retains the 137 legacy `T090w` values; no automatic normalization was performed.

Финальный независимый read-only snapshot снят `2026-08-29 21:14:28 UTC` внутри `REPEATABLE READ READ ONLY`. Все raw/recovery/business totals выше совпали; `source_doc_id` и `source_inv_id` уникальны, пропущенных source hashes и orphan FK/link нет. Manual `9565`, legacy `6710`/`DOC 12514` и copy `41938` сохранили исходные значения и timestamps. От раннего checkpoint изменились только ожидаемые служебные факты: migrations `74 → 76` и activity logs `187 555 → 187 558`; бизнес-таблицы workflows остались пустыми.

## 4. Actual roles and grants

| Role | Users | Permissions | Назначение по фактической matrix |
|---|---:|---:|---|
| `member` | 4 | 35 | Публичный поиск и собственный кабинет/бронь/выдачи/материалы |
| `librarian` | 3 | 103 | Circulation desk, copies, reservations, visits, incidents, operations reports |
| `senior_librarian` | 2 | 149 | Librarian + KSU, inventory approval, write-off, recovery, raw MARC, settings |
| `cataloguer` | 2 | 15 | Canonical bibliography, classification, raw MARC, catalog DQ |
| `acquisitions` | 1 | 19 | Intake/confirmation, KSU management, copy creation, acquisition reports |
| `bibliographer` | 1 | 18 | Bibliographic discovery, repository/external resources, requests/EDD/tasks |
| `director` | 2 | 69 | Management analytics/approval/read-only operational oversight |
| `admin` | 3 | 167 | Enumerated control-plane capabilities; no wildcard grant |

Один canonical admin имеет устаревшее legacy-поле `users.role=reader`; authorization использует canonical Spatie-role, поэтому фактического расширения/сужения доступа из этого поля не обнаружено. Это P2 data-normalization observation, а не используемый RBAC источник.

## 5. Final defect list

### P0/P1 defects found during this gate

| ID | Module | Severity | Root cause | Fix | Test / evidence | Status |
|---|---|---|---|---|---|---|
| QG-WO-06 | Incident / write-off / KSU-2 | P0 | Incident resolution directly set `written_off`, bypassing act/date, central lifecycle, KSU-2, reservation/history/report/audit effects | Preliminary damage stays `under_repair`; authorized final incident delegates to `CopyWriteOffService`, with act/date required | Circulation/incident 21 tests, 311 assertions; unified PostgreSQL chain | **FIXED / PASS** |
| QG-CONC-01 | Circulation | P1 | Global loan limit counted rows without locking a stable reader row; two zero-row transactions could both pass | Lock user before count/issue; transaction retry=3 | Real PostgreSQL forked concurrency: same reader/max=1, one success and one controlled limit rejection | **FIXED / PASS** |
| QG-CONC-02 | Reservations | P1 | Same zero-row race in global reservation limit | Lock user before count/create; retry=3 | Real PostgreSQL forked concurrency; last-copy allocation yielded one ready and one queued | **FIXED / PASS** |
| QG-DATA-04 | Fund movement | P1 | Branch-only movement could retain a fund belonging to another branch | Validate the resulting branch+fund pair atomically and roll back invalid moves | Service tests + unified PostgreSQL chain | **FIXED for new writes / PASS** |
| QG-HARNESS-07 | Test safety | P1 | Disposable PostgreSQL test runner could create DB with the wrong owner | Runner validates owner and isolation before use | PostgreSQL acceptance harness pass; disposable DB removed | **FIXED / PASS** |
| QG-REP-01 | Reports | P1 | Inventory-book hydrated 51 074 rows before applying export size limit, causing PHP OOM at 256 MB | Bounded SQL probe before hydration; narrow select; SQL aggregation for facets/totals; controlled `ReportLimitExceeded` | 33 tests, 1 259 assertions; live oversized report exits normally with HTTP-domain 422 and ~23 MB peak | **FIXED / PASS** |
| QG-RBAC-01 | Director KPI | P1 | Staff KPI compared morph value with `User::class`; stored morph alias is `user` | Use `(new User)->getMorphClass()` | Live KPI now 14 active staff, equal to canonical aggregate; RBAC/dashboard tests | **FIXED / PASS** |
| QG-SEC-02 | Catalog / raw MARC | P1 | Edit controller and Blade exposed raw MARC to any ordinary librarian | Guard controller payload and UI with `catalog.view_raw_marc` | Cataloguer positive / librarian negative: 2 tests, 36 assertions | **FIXED / PASS** |
| QG-RBAC-04 | Member cabinet / own scope | P1 | Fifteen own-scope capabilities were guarded by broad `member` role, so a revoked grant still left action/payload/link access | Exact permission middleware, controller payload gates, dashboard/sidebar gates | Member/RBAC negative tests included in focused suite | **FIXED / PASS** |
| QG-SEC-05 | Executive reports | P1 | Export endpoint and UI did not enforce `reports.export` | Exact route/controller/UI gate | 2 tests, 33 assertions | **FIXED / PASS** |
| QG-RBAC-08 | Reservations | P1 | Ready/extend routes checked only `reservation.confirm`; assigned-copy path bypassed `reservation.assign_copy` | AND gates: ready=`confirm+fulfill`, extend=`confirm+extend`; controller/UI require assign-copy | 4 tests, 19 assertions; active route cache rechecked | **FIXED / PASS** |
| QG-API-01 | Public external-catalog proxy | P1 | Upstream exception/transport status/internal endpoint could be reflected to public JSON | Catch/report `Throwable`; return localized generic 503 with `success=false`; normalize upstream failures | `ReaderConvergenceTest` and public error privacy tests | **FIXED / PASS** |
| QG-PUB-02 | Public book payload | P1 | Embedded electronic material exposed direct external locator; top-level technical `source` leaked an internal table identifier | Remove direct locator from embedded payload; remove internal source metadata | Book detail visibility/API tests | **FIXED / PASS** |
| QG-OPS-01 | Production runtime | P1 | Runtime `.env` said local/debug-style logging and named the obsolete DB; stale caches allowed local semantics | Set production URL/env, recovered DB, daily/warning logging; rebuild config/route caches | `artisan about` shows production, debug OFF, correct URL, cached config/routes/views; final container recreate still pending | **FIXED IN CONFIG / DEPLOY CHECK PENDING** |
| QG-CRED-01 | AD / credential security | P1 | A password for a live AD-linked canonical-admin account was disclosed in this conversation | Required external action: rotate in AD, invalidate active sessions/tokens as policy requires, review auth/security logs, confirm old secret rejected and new login succeeds | Account is active and has recent successful AD authentication; rotation evidence is absent; secret was not used by audit | **OPEN / RELEASE BLOCKER** |
| QG-FE-02 | Frontend dependency | P1 | Seven core Blade templates depended on Tailwind CDN at baseline, creating production availability/CSP/supply-chain risk | Replace all seven with local Vite CSS; centralize palette/fonts/class-dark/radius compatibility and local forms plugin | CDN references in `resources/views`=0; Node asset contract 3/3; focused render 18 tests/161 assertions; final build PASS | **FIXED / PASS (visual browser run skipped)** |

### P2/P3 defects, limitations and data debt

| ID | Module | Severity | Root cause | Fix | Test / evidence | Status |
|---|---|---|---|---|---|---|
| QG-CONC-03 | Visits | P2 | Ten-minute dedupe and audit write occurred outside one locked transaction | Lock `ReaderProfile`; perform decision + write + audit transactionally | Service suite | **FIXED / PASS** |
| QG-CONC-05 | Inventory | P2 | Two empty-scope inventory sessions could both start because no row existed to lock | PostgreSQL advisory xact lock `(1263752521,1)` and transaction retry | Real PostgreSQL concurrency allocated inventory/barcode sequence values 1/2 without duplicates | **FIXED / PASS** |
| QG-UI/RBAC-03 | Repository | P2 | UI used dead `repository.update` permission and outer route/operational allow-list omitted the implemented capability | Align on `repository.edit`; expose only to exact permission | Navigation/repository 2 tests, 22 assertions | **FIXED / PASS** |
| QG-UI/RBAC-06 | Admin shell | P2 | Delegated control-plane users saw dead admin links or lost reachable links | Make shell navigation capability-aware | Navigation/control-plane tests | **FIXED / PASS** |
| QG-RBAC-07 | Operational staff entry | P2 | Implemented staff capabilities were absent from `EnsureOperationalStaffPermission`; custom least-privilege roles received false 403 | Add only implemented staff entry capabilities; remove dead sidebar links | 2 tests, 13 assertions; 102/102 operational permissions unique and seeded | **FIXED / PASS** |
| QG-PUB-01 | Public catalog | P2 | UDC was hidden from guest despite explicit catalog requirement | Publish canonical UDC/display value without exposing raw MARC | Catalog filters/book-detail tests | **FIXED / PASS** |
| QG-I18N-01 | KK/EN DQ copy | P2 | Twenty legacy literal-dot aliases were interpreted as nested translation keys | Correct KK/EN DQ key structure | 15 tests, 134 assertions; 5 827 keys/locale, 0 critical, 29 documented legitimate warnings | **FIXED / PASS** |
| QG-PERF-01 | PostgreSQL planner | P2 | Recovery tables had no current planner statistics (`n_live_tup=0`, `last_analyze=NULL`) | Run `ANALYZE` only on measured core tables | Availability plan improved 39.658 ms → 20.792 ms; title 11.184 ms; UDC 5.445 ms | **FIXED / PASS** |
| QG-BUILD-01 | Frontend bundle | P2 | One JS chunk was ~1.5 MB | Vite manual chunk split | Final build produced 19 JS chunks, largest 295.21 kB, without the original chunk warning | **FIXED / PASS** |
| QG-TEST-LOG-01 | Test isolation | P2 | Tests could append expected errors to production-style log files | Force stderr test logging in `tests/bootstrap.php` | Focused test runs no longer depend on production log file | **FIXED / PASS** |
| QG-TEST-02 | Member API test fixtures | P2 | Five legacy controller-contract classes created only a session array; after exact RBAC gates they correctly received 403 from Spatie because no canonical Auth principal existed | Keep production middleware fail-closed; bypass only Spatie permission middleware in those controller-contract tests; add canonical allow/revoke coverage for all eight account routes and a session-only fail-closed regression | 62 targeted tests, 305 assertions, 14 isolated-PG skips; production fallback explicitly rejected | **FIXED / PASS targeted; final rerun pending** |
| QG-DOC-01 | Tooling/docs | P3 | Composer/Make/docs referenced 13 deleted wrapper scripts | Replace with real inline commands/read-only targets and factual docs | Missing-reference scan=0; `composer validate` PASS; PDF.js 6.2.108 verification PASS | **FIXED / PASS** |
| QG-DATA-09 | Funds | P2 | Nine recovered copies already contain a branch/fund mismatch | New invalid writes are blocked; legacy rows retained for governed correction | Read-only DB reconciliation | **OPEN DATA-QUALITY BACKLOG** |
| QG-REC-01 | Historical write-off | P2 | Three canonical historical `written_off` copies predate central KSU-2 path and have no KSU-2 item | Do not fabricate historical KSU; all new write-offs use central service | Read-only IDs/status/act/date reconciliation | **ACCEPTED RECOVERY CONSTRAINT** |
| QG-PERM-01 | Permission catalogue | P2 | Six contracts exist without a complete implemented consumer: `reservation.override_queue`, `digital.view_analytics`, `repository.view_public`, `external_resources.view_contracts`, `messages.manage_sla`, `integrations.manage_secrets_reference` | No fake UI was added; capability gap documented | Seeder/route/navigation audit | **OPEN / NON-CORE CONTRACT DEBT** |
| QG-BACKUP-01 | Backup | P2 | Verified local backup exists, but offsite replication is not configured | Configure independent offsite target, retention and restore drill | Current configuration explicitly says not configured | **OPEN** |
| QG-UI-IMG-01 | Public UI assets | P2 | `campus-library.jpg` is ~13.1 MB (6000×4000) and `author-visit.jpg` ~16.8 MB; first is eager on home/login | Requires separately authorized image optimization/responsive variants | File dimensions/sizes measured; no bitmap was silently rewritten | **OPEN PERFORMANCE ADVISORY** |
| QG-E2E-01 | Visual acceptance environment | P2 | Host lacks Chromium shared libraries (`libatk`, `libXdamage`, `libasound`, `libatspi`) | Install approved browser runtime or execute suite on prepared runner | 28 browser cases could not start; one redirect-only case passed | **SKIPPED / NOT A UI PASS** |
| QG-REP-02 | Large synchronous export | P2 | Collection exports intentionally reject result sets above 10 000 rows; no queued collection export exists | Narrow filters work; future queued export is a product decision | Controlled 422 replaces OOM; report tests pass | **DOCUMENTED LIMIT** |

Legacy absence of 42 334 barcodes is tracked source data, not classified as migration failure. Open DQ/conflict/quarantine counts likewise remain visible governed work, not falsely reported as repaired data.

## 6. Final module matrix

Legend: `PASS` = backed by a concrete targeted/live/disposable-PostgreSQL check; `PARTIAL` = application check passed but required visual/final gate is still pending; `N/A` = not a function of the module.

| Module | Backend | DB | UI | RBAC | Audit | Search | Report | KK | RU | EN | Test | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Public catalog/book | PASS | PASS | PARTIAL | PASS | N/A | PASS | N/A | PASS | PASS | PASS | Feature + crawl | **PARTIAL: visual/final smoke** |
| Member cabinet | PASS | PASS | PARTIAL | PASS own-only | PASS | Catalog PASS | N/A | PASS | PASS | PASS | Own-scope/IDOR | **PARTIAL: role crawl** |
| Cataloguing | PASS | PASS | PARTIAL | PASS | PASS | PASS | N/A | PASS | PASS | PASS | Acceptance/features | **PARTIAL: visual** |
| Raw MARC | PASS read-only | PASS immutable | PARTIAL expert view | PASS | Read-only | Record lookup PASS | N/A | PASS | PASS | PASS | Positive/negative RBAC | **PASS targeted** |
| Acquisitions | PASS | PASS transactional | PARTIAL | PASS | PASS | Existing/draft record | PASS | PASS | PASS | PASS | Unified PG chain | **PASS targeted** |
| KSU/recovery | PASS | PASS | PARTIAL | PASS | PASS | PASS | PASS/reconciled | PASS | PASS | PASS | PG acceptance/concurrency | **PASS targeted** |
| Copies/lifecycle | PASS | PASS | PARTIAL | PASS | PASS | Filters tested | PASS | PASS | PASS | PASS | Features + PG chain | **PASS targeted** |
| Circulation/return/renew | PASS | PASS/locked | PARTIAL scanner UI | PASS | PASS | Reader/copy scan | PASS | PASS | PASS | PASS | 21/311 + concurrency | **PASS targeted** |
| Reservations | PASS | PASS/locked | PARTIAL | PASS exact gates | PASS | Queue lookup | PASS | PASS | PASS | PASS | Feature + concurrency | **PASS targeted** |
| Visits/fines/incidents | PASS | PASS transactional | PARTIAL | PASS | PASS | Reader/case lookup | PASS | PASS | PASS | PASS | Service/features | **PASS targeted** |
| Inventory/movement | PASS | PASS/locked | PARTIAL | PASS | PASS before/after | Scan/filter PASS | PASS | PASS | PASS | PASS | Unified PG + concurrency | **PASS targeted** |
| DQ/conflicts/quarantine | PASS | PASS | PARTIAL grouped | PASS | PASS | Filters/groups | PASS | PASS | PASS | PASS | Repeat live scan/features | **PASS engine; backlog open** |
| Digital/repository/files | PASS | PASS | PARTIAL | PASS | PASS | Metadata PASS | Analytics contract partial | PASS | PASS | PASS | 6/17 + 4/77 boundaries | **PARTIAL: P2 contracts/UI** |
| Reports/exports | PASS bounded | PASS aggregates | PARTIAL | PASS | Archive/audit PASS | Filters PASS | PASS reconciled | PASS | PASS | PASS | 33/1 259 | **PASS targeted; final suite pending** |
| Director | PASS | PASS aggregates | PARTIAL | PASS read-only | Approved view | N/A | PASS | PASS | PASS | PASS | KPI/RBAC tests | **PASS targeted** |
| Admin/settings/integrations | PASS | PASS | PARTIAL | PASS enumerated | PASS technical | PASS | Health/log views | PASS | PASS | PASS | Boundary/control-plane | **BLOCKED by credential incident** |
| AD auth | PASS health/failure handling | Mapping PASS | Login page PARTIAL | Mapping PASS | Auth history PASS | N/A | N/A | PASS | PASS | PASS | Health/history/features | **BLOCKED by QG-CRED-01** |
| Backup/recovery ops | PASS local | Restore PASS | N/A | Least privilege PASS | Artifact metadata | N/A | N/A | N/A | N/A | N/A | Disposable restore | **PARTIAL: no offsite** |
| Frontend/assets | PASS server render | N/A | PARTIAL | Nav PASS | N/A | N/A | N/A | PASS | PASS | PASS | Local asset contract/build/render | **PASS build; visual browser run skipped** |

## 7. Final role matrix

Legend: `OWN` = only own data/action; `RO` = read-only/aggregate; `OPS` = operational access; `—` = intentionally forbidden by actual grant; `PENDING` = final live role crawl not yet run. Matrix reflects actual DB role grants, not the legacy `users.role` field.

| Role | Login | Dashboard | Catalog | Copies | Circulation | Reservation | Acquisition | KSU | Inventory | Movement | Quality | Reports | MARC | Admin | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `member` | Auth tested | OWN | Search/read | Availability only | OWN history/renew | OWN create/cancel | — | — | — | — | — | — | — | — | **Targeted PASS; crawl PENDING** |
| `librarian` | Auth fixture | OPS | Create/edit/search | Create/edit | Issue/return/renew | Desk/assign/fulfil | View workspace only | — | View/scan | Create/view | Triage/correct | OPS/export | — | — | **Targeted PASS; crawl PENDING** |
| `senior_librarian` | Auth fixture | OPS | Full operational | +delete/write-off | +overrides | +extend/transfer | Intake/confirm | Manage/resolve | Create/review/approve | OPS | Scan/bulk/approve | OPS/export | Read-only | Settings only | **Targeted PASS; crawl PENDING** |
| `cataloguer` | Auth fixture | Focused | Create/edit/classify | Edit | — | — | — | — | — | — | Catalog DQ | — | Read-only | — | **Targeted PASS; crawl PENDING** |
| `acquisitions` | Auth fixture | Focused | Search/create draft | Create | — | — | Full intake/confirm | View/manage | — | — | — | Acquisition/export | — | — | **Targeted PASS; crawl PENDING** |
| `bibliographer` | Auth fixture | Tasks/EDD | Search/read | — | — | — | — | — | — | — | — | — | — | — | **Targeted PASS; business scope documented** |
| `director` | Auth fixture | Executive | Aggregate/approved only | — | Aggregate only | Contract `override_queue` not implemented | RO acquisitions | Via reports | — | — | View/reports | Full/export/approve | — | — | **Targeted PASS; P2 contract debt** |
| `admin` | AD health + fixture | Control plane | Enumerated full | Enumerated full | Enumerated full | Enumerated full | Enumerated full | Manage/resolve | Full | Full | Full/recovery | Full/export | Read-only | Users/roles/system | **RBAC PASS; QG-CRED-01 BLOCKER** |

Negative proofs include: member cannot access staff/foreign IDs; librarian cannot access admin or raw MARC; acquisitions cannot arbitrarily PATCH catalog; director cannot perform destructive catalog/write-off/inventory/acquisition/KSU/queue mutations; diagnostics, digital, repository, exports and attachments require their exact permissions. No wildcard admin grant was introduced.

## 8. Final workflow matrix

| Workflow | Result | Evidence / remaining limit |
|---|---|---|
| Cataloguing | PASS | Draft → metadata → catalog visibility in feature/unified PG acceptance; raw MARC separated |
| Acquisition | PASS | Batch → existing/new draft → quantity/price → confirmation/copies in unified PG chain |
| KSU | PASS | `N/YYYY`, annual sequence and unique values; real PG parallel allocation returned 10/2026 and 11/2026 |
| Issue | PASS | Eligibility/state/audit/member loan; same-copy parallel issue produced one loan + one rejection |
| Return | PASS | Close loan, availability/queue handling and audit in circulation/unified chain |
| Renew | PASS | Active loan, limit, reservation and reader-state conditions covered by circulation features |
| Reservation | PASS | Create/queue/ready/fulfil/cancel/extend permissions; two-reader last-copy outcome controlled |
| Visit | PASS | Ten-minute dedupe is locked/transactional; visit does not create loan |
| Inventory | PASS | Scoped session/scan/results; parallel numbering 1/2; empty-scope start serialized |
| Movement | PASS | Atomic before/after movement and branch/fund validation; existing nine mismatches remain backlog |
| Incident | PASS | Loss/damage/repair/replacement paths tested; lifecycle eligibility enforced |
| Write-off | PASS for new operations | Central service requires act/date, updates KSU-2/report/audit; three historical rows intentionally unchanged |
| DQ | PASS engine | Live idempotent scan created/reopened 0 false location issues; data backlog remains visible |
| Reports | PASS targeted | All 14 collection codes read; aggregates/exports tested; >10k sync export controlled, not OOM |
| Reader cabinet | PASS targeted | Own-only reservations/loans/fines/incidents/messages/materials and IDOR boundaries |
| Director | PASS targeted | Active staff KPI fixed to 14; management totals use canonical aggregates |
| Admin | PASS application / BLOCKED operationally | Control plane and negative boundaries tested; exposed active credential must be rotated |

The unified disposable-PostgreSQL acceptance exercised: acquisition → KSU-1 → copies → catalog → reservation → issue → member loan → return → visit → inventory → movement → central write-off/KSU-2 → reports/KPI/audit. It passed 1 test / 45 assertions; the temporary DB was dropped and no acceptance prefix remained in live data.

## 9. Final security matrix

| Scope | Result | Evidence / condition |
|---|---|---|
| PUBLIC | PASS targeted | No price/KSU/raw MARC/legacy IDs/internal notes; generic localized external-service 503; final live smoke PENDING |
| MEMBER | PASS targeted | Exact own-scope permissions and IDOR negatives for foreign reservation/loan/profile |
| STAFF | PASS targeted | Operational entry gate aligned to implemented exact permissions; raw MARC denied to ordinary librarian |
| DIRECTOR | PASS targeted | Read-only/destructive negatives; exports require `reports.export` |
| ADMIN | **FAIL operational gate** | Application RBAC/control plane passes, but active canonical-admin credential disclosure is unremediated |
| API | PASS targeted | Guest/admin diagnostics boundaries, normalized errors, unsafe internal metadata removed |
| EXPORTS | PASS targeted | Permission gates; UTF-8/export formats tested; oversized collection export returns controlled 422 |
| FILES | PASS targeted | Private storage authorization and traversal/direct-path defenses; invalid public storage path gives 404 under production config |
| RAW MARC | PASS targeted | Read-only for cataloguer/senior/admin; negative librarian/member/public test |
| PII | PASS targeted | Own-only/member boundaries, admin technical visibility and attachment IDOR checks |

Additional security facts:

- `.env` mode was 600; private storage was 770; framework writable directories were 775.
- Public responses had HSTS, `nosniff`, `SAMEORIGIN`, referrer and permissions policies; session cookie was Secure, HttpOnly, SameSite=Lax.
- `library_app` is not superuser and has no create DB/role, replication or bypass-RLS privileges. Objects are owned by `library_migrator`; the bootstrap superuser is not the application connection.
- No secret value is included in this report.

## 10. Reports and reconciliation

Read-only live report checks covered all 14 collection report codes. Verified canonical aggregates include:

| Report | Result |
|---|---|
| KSU-1/register | 20 entries; 832 titles; 2 408 copies; value 38 358 094; 4 809 unresolved conflicts visible |
| KSU-2 | 0 new live entries at checkpoint; three pre-central historical write-offs not fabricated |
| KSU-3 | 2 rows; net 2 408 copies; value 38 358 094 |
| Inventory-book oversized | Controlled `ReportLimitExceeded`, process exit 0, peak memory ~23 MB |
| Active collection facet | 51 071 copies; 9 471 titles; value 183 496 545.31 |
| New arrivals/source | 51 074 copies; 9 474 titles; value 183 496 545.31 |

Totals are derived from canonical tables rather than legacy `m7–m33` columns. The difference between active collection and all-source totals is attributable to three historical written-off copies.

## 11. Performance

Global “slowest query” ranking is unavailable because `pg_stat_statements` is not installed; this is stated rather than inferred. Measured probes:

| Measurement | Result |
|---|---:|
| Public catalog read-only HTTP sample | ~440 ms |
| Largest sampled public response | ~289 KB catalog HTML |
| Availability filter after `ANALYZE` | 20.792 ms (was 39.658 ms) |
| Title substring query | 11.184 ms |
| UDC prefix query | 5.445 ms |
| Oversized inventory-book report | ~23 MB PHP peak; controlled rejection before hydration |
| Final frontend output | 19 JS chunks; largest 295.21 kB (was ~1.5 MB); CSS 103.08 kB / 17.06 kB gzip |
| Largest image assets | 16 776 032 B and 13 146 638 B |

No broad claim about “zero N+1” is made: the material measured defect was unbounded report hydration/aggregation and it was fixed. Catalogue/copy/KSU/dashboard report queries were exercised, but a production-wide query profiler was unavailable.

Final `npm run build` completed with exit code 0: 2,568 modules transformed, no chunk-size warning, largest JS chunk 295.21 kB (106.04 kB gzip). `npm run test:tailwind` passed 3/3. `npm audit` reports 0 high/critical and 4 low + 4 moderate advisories in pre-existing 21st/AI SDK dependency chains; no unsafe automatic audit fix was applied.

## 12. Test report

| Gate | Result | Evidence |
|---|---|---|
| Test DB isolation | PASS | 1 test / 9 assertions; testing + SQLite memory + production PostgreSQL unreachable |
| Business services | PASS targeted | 70 tests / 436 assertions |
| Circulation + incidents | PASS targeted | 21 tests / 311 assertions |
| PostgreSQL unified acceptance | PASS | 1 test / 45 assertions; disposable DB dropped |
| PostgreSQL true concurrency | PASS | KSU, inventory, barcode uniqueness, issue, reader limit and reservation barriers all passed |
| RBAC ready/extend/assign | PASS | 4 tests / 19 assertions |
| Raw MARC boundary | PASS | 2 tests / 36 assertions |
| Repository/navigation | PASS | 2 tests / 22 assertions |
| Acquisitions/director negatives | PASS | 2 tests / 25 assertions |
| Diagnostic boundaries | PASS | 8 tests / 69 assertions |
| Digital/repository/export/attachment boundaries | PASS | 6/17, 4/77, 2/33, 1/6 |
| Report stack | PASS targeted | 33 tests / 1 259 assertions |
| i18n | PASS targeted | 15 tests / 134 assertions; critical=0 |
| Public error privacy | PASS | 1 test / 90 assertions across 403/404/419/422/500 and kk/ru/en |
| Broad focused regression | PASS | 319 tests / 2 343 assertions |
| Recovery regression | PASS | 41 tests / 414 assertions |
| Reservation integrations | PASS with skips | 26 tests / 154 assertions; 2 skipped reported separately |
| Local server-side public crawl | PASS | 39/39 endpoints (13 × 3 locales), no 500/leak |
| Initial live public crawl | PASS read-only | kk/ru/en, no 500; intentional redirects preserved |
| Browser visual/E2E | **SKIPPED/BLOCKED** | Chromium shared libraries absent; not counted as UI PASS |
| Composer validation/docs refs | PASS | `composer validate`; missing wrapper references=0; PDF.js 6.2.108 verified |
| PHP syntax, changed PHP set | PASS | 334 changed/untracked PHP files, 0 syntax errors; final RBAC fixture files rechecked |
| Targeted Pint, task-owned files | PASS | Combined production/service/route/test set plus final six RBAC fixture files |
| `git diff --check` | PASS at code freeze | Re-run once more after final report update |
| Full PHPUnit from zero | **`[PENDING FINAL RUN]`** | Must be 0 failures / 0 errors; list skipped separately |
| Final production frontend build | PASS | Node asset contract 3/3; Vite build exit 0; largest chunk 295.21 kB |
| Final production role/public smoke | **`[PENDING FINAL RUN]`** | Must run after deploy/recreate |

An earlier partial full-suite run is intentionally excluded because concurrent edits invalidated it before completion. It cannot satisfy section 83 of the acceptance request.

## 13. Production health

| Component | Current verified checkpoint | Final gate |
|---|---|---|
| App container | Healthy on baseline; php-fpm/nginx/scheduler/queue under supervisor | `[PENDING recreate + uptime/restart/health]` |
| PostgreSQL | Healthy, 18.4, recovered DB, 934 MB, 76/76 migrations | Final read-only snapshot PASS; repeat a compact post-recreate identity/count check |
| Laravel config | Effective env production, debug OFF, correct URL; config/routes/views cached | Container OS env still needs final recreate confirmation |
| HTTP | Initial live kk/ru/en crawl had 0 accidental 500; security headers present | `[PENDING post-deploy crawl/timings]` |
| Queue | `jobs=0`, `failed_jobs=0`; workers for integrations/reports/default | `[PENDING post-deploy supervisor check]` |
| Scheduler | 11 jobs registered; scheduler process healthy | `[PENDING post-deploy next-run/health]` |
| AD | Enabled and connected; health ~61.5 ms; controlled historical provider failures followed by successes | **Credential rotation PENDING** |
| Disk/inodes | 114 GB free; inode use 10% | Recheck only if deployment changes materially |
| Storage | Private 770; env 600; invalid public storage path 404 under effective production config | `[PENDING smoke]` |
| Logs | Daily logging selected; old controlled audit/test errors retained, not deleted | `[PENDING marker + no new production ERROR during smoke]` |
| Backup | Verified local dump 45 194 639 B, checksum/TOC 1 708; disposable restore/count/FK check passed and DB dropped | **OFFSITE NOT CONFIGURED** |

Latest verified local backup artifact: `storage/app/backups/verified/digital_library_recovered/20260829T183850Z`. Local verification is PASS; offsite backup must not be reported as PASS.

`[PENDING FINAL DEPLOY: rebuild views/config/routes as appropriate; recreate only app service, never PostgreSQL; verify effective/OS APP_ENV=production, supervisor, queues, scheduler, AD health and live smokes.]`

## 14. Git ownership and dirty-worktree discipline

No reset, stash, clean, `git add -A`, branch, worktree, second repository or second Docker stack was used. HEAD remained unchanged.

Baseline supplied to the gate: 6 staged entries, 195 unstaged files, 128 untracked files. At this draft checkpoint, porcelain with all untracked files reported 6 staged, 237 unstaged, 132 untracked. These numbers are not a commit boundary and may change while final agents finish.

### PRE-EXISTING (known at baseline)

The six staged entries were already present and were not altered as part of this reporting task:

- `.gitignore` modified;
- `1.png`, `2.png`, `80400` deleted;
- `docs/operations/PROJECT_FILE_STRUCTURE.md` added;
- `scripts/dev/import-marcsql-stream.php` renamed to `scripts/deprecated/import-marcsql-stream.php`.

The broader 195/128 dirty baseline belongs to prior/user work unless a specific hunk is positively attributable below.

### TASK-OWNED / positively attributable audit changes

Ownership is at **hunk level** where files already had inherited modifications. Confirmed areas:

- security/public payload: `CatalogReadService`, `BookDetailReadService`, public API catalog/book controllers and their focused tests/translations;
- RBAC/navigation: `routes/web.php`, `routes/api.php`, `EnsureOperationalStaffPermission`, member/librarian/director controllers, affected layouts/views and focused RBAC tests;
- concurrency/lifecycle: circulation, reservation, visit, inventory, fund-movement, incident and central write-off services/controllers/tests;
- reports: `CollectionAccountingReportService`, `ReportLimitExceeded`, report feature tests;
- i18n: the 20 corrected KK/EN DQ aliases and interface localization tests;
- operations/tooling: `.env` (ignored), `.env.prod.example`, deployment docs, test bootstrap logging, Vite chunk configuration, 1280px E2E cases, public error privacy test, safe PostgreSQL test runner;
- this runtime report.

### UNKNOWN / overlapping

Every remaining dirty file or hunk not tied to an evidence-backed change above remains `UNKNOWN` or `PRE-EXISTING`; it must not be mass-formatted, staged, reverted or claimed as authored by this gate. A final `git status --porcelain=v1 --untracked-files=all` snapshot must be appended after edit freeze.

`[PENDING FINAL GIT SNAPSHOT: counts + exact task-owned lint set; preserve the six inherited staged entries.]`

## 15. Required closure checklist

- [ ] Rotate the disclosed credential in external AD; invalidate sessions/tokens as policy requires; audit access; prove the old credential no longer works without writing either secret to logs/report.
- [x] Record Tailwind hardening result and verify no unintended CDN dependency remains.
- [x] Run PHP syntax and targeted Pint on the final task-owned set.
- [ ] Run `git diff --check` once more after the final report edit.
- [ ] Run full PHPUnit from zero: 0 failures, 0 errors; list skips.
- [x] Run final production frontend build and record largest chunk/warnings.
- [ ] Rebuild caches/recreate only the app service; verify production environment and supervisor processes.
- [ ] Run final public kk/ru/en crawl, representative eight-role allowed/forbidden smoke, reports and storage/API privacy probes.
- [ ] Recheck logs from a fresh marker, queue/failed jobs, scheduler, AD health, raw/business counts and control records.
- [ ] Configure offsite backup or explicitly retain it as an open non-P0/P1 operational exception.
- [ ] Replace every `PENDING` placeholder with evidence; do not convert skipped browser visual coverage into PASS.

## 16. Verdict placeholder

Completion is forbidden while any P0/P1 is open. `QG-CRED-01` is still open and requires action outside this repository. Therefore this draft cannot honestly emit the verified verdict.

`[PENDING FINAL VERDICT — exact one-line value to be inserted by the root audit after all closure gates]`
