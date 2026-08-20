# Final application security gate — 2026-08-20

## Outcome

Application security controls passed the tested backend and public-browser
boundaries. ASG-009 is fixed in production: web and workers now use the
least-privilege `library_app` role, migrations use the separate
`library_migrator` role, and the PostgreSQL administrative credential is absent
from the application container. Unresolved Critical and High findings are zero.

Authenticated browser automation is conditional because no owner credential was
available in memory. Existing session files were deliberately not reused.

## Findings

| ID | Severity | Area | Finding | Status |
|---|---|---|---|---|
| ASG-001 | High | Integration API | Empty bearer-token allowlist previously failed open | FIXED — empty allowlist now returns 401; production has one configured token |
| ASG-002 | Medium | CSRF | 25 internal session API writes lacked explicit CSRF middleware | FIXED/LIVE — route cache refreshed; 236 browser writes audited, gaps 0 |
| ASG-003 | Medium | LDAP | Client code permitted plaintext or unverified configuration fallback | FIXED — LDAPS and certificate verification now fail closed |
| ASG-004 | Medium | CORS | Private session API returned wildcard ACAO | FIXED/LIVE — canonical application origin only; credentials enabled only for that origin |
| ASG-005 | Medium | Files | Repository/digital uploads lacked common executable-extension and active-PDF guard | FIXED — executable, MIME-spoof and active-PDF regression tests pass |
| ASG-006 | Medium | Path traversal | Private file keys had no explicit cross-platform traversal contract | FIXED — plain/encoded/Windows/absolute cases denied |
| ASG-007 | Medium | Audit/secrets | Nested redaction omitted `passwd`, cookie and OAuth client/access key variants | FIXED — nested regression matrix passes |
| ASG-008 | High | Node dependencies | Initial audit: 2 Critical and 5 High, primarily build tooling plus Axios | FIXED — safe non-major patches; final Critical 0, High 0; build passes |
| ASG-009 | High | Database privileges | Runtime previously used the `library_user` cluster superuser | FIXED/LIVE — runtime `library_app`; migrator `library_migrator`; both have all dangerous role flags false |
| ASG-010 | Medium | Backup permissions | Production dump was HTTP-inaccessible but host mode was `664` | FIXED — owner/group preserved as `www-data:www-data`; mode is `640` |
| ASG-011 | Medium | Node dependencies | 4 Moderate and 4 Low advisories remain in AI/syntax-highlighting dependency chain | OPEN — no Critical/High; upstream/no non-breaking complete fix |
| ASG-012 | Operational | Browser | Authenticated Playwright smoke unavailable without owner credential | CONDITIONAL — public desktop/mobile browser gate passed |
| ASG-013 | Operational | Docker health | PostgreSQL query service passes while Docker healthcheck is unhealthy | ACCEPTED INFRASTRUCTURE / OBSERVABILITY DEBT |
| ASG-014 | Operational | Containers | App supervisors/master processes run as root; workers run as `www-data`; rootfs writable | DOCUMENTED — no container refactor in this gate |
| ASG-015 | High | Image/backup exposure | A local app image build admitted the production dump despite generic ignore rules | FIXED/LIVE — explicit backup exclusions; rebuilt/running image verified backup-free; prior dangling images predate the dump |

## Evidence summary

- Public Playwright, desktop 1440×900 and mobile 390×844: `/`, `/catalog`,
  `/login` all 200; console errors 0; page errors 0; failed requests 0; mixed
  content 0; HTTP 500 responses 0.
- Browser session cookie: Secure, HttpOnly, SameSite=Lax.
- Owner admin state: AD-linked, active, manual admin, direct permissions 0,
  effective permissions 153. Role was not changed.
- RBAC exact-role matrix, admin negative paths, reader object isolation, message
  ownership and digital/repository file policies passed isolated tests.
- SQL: representative report/filter injection rejected; raw expressions reviewed
  as static or allowlisted. LDAP metacharacters escaped in LDAP-enabled runtime.
- XSS: rich news HTML sanitized and Kazakh Unicode preserved; reader private
  collection XSS escaping passed.
- Source paths: `/.env` 403, `/.git/config` 403, Compose files 404, backup path 403.
- Composer advisories: 0. npm final: Critical 0, High 0, Moderate 4, Low 4.
- PostgreSQL is loopback-published only (`127.0.0.1:5432`); no Redis container or
  Docker socket mount was found.
- All 72 migrations passed from an empty isolated PostgreSQL database as a
  non-superuser migrator; targeted rollback/reapply passed. Production migrator
  identity preflight returned `library_migrator` and `Nothing to migrate`.
- Production runtime identity is `library_app`; SUPERUSER, CREATEDB, CREATEROLE,
  REPLICATION and BYPASSRLS are false. All 109 application tables, 95 sequences,
  2 functions and the `app` schema are owned by `library_migrator`.

## ASG-009 production remediation

### Before

```text
library_user:
  SUPERUSER: YES
  CREATEDB: YES
  CREATEROLE: YES
  REPLICATION: YES
  runtime + migrations + admin: combined
```

### Target and production state

```text
library_app:
  runtime login: YES
  SUPERUSER/CREATEDB/CREATEROLE/REPLICATION/BYPASSRLS: NO
  privileges: CONNECT, schema USAGE, table SELECT/INSERT/UPDATE/DELETE,
              sequence USAGE/SELECT

library_migrator:
  deployment-only login: YES
  SUPERUSER/CREATEDB/CREATEROLE/REPLICATION/BYPASSRLS: NO
  application schema/object ownership and DDL: YES

library_user:
  PostgreSQL bootstrap/admin and database owner
  exposed to web/worker container: NO
```

### Isolated proof

- Empty PostgreSQL test DB migration: 72/72 PASS under non-super migrator.
- Targeted final migration rollback/reapply: PASS.
- Runtime reads/writes, RBAC, catalog, copies, circulation, Data Quality, admin
  reads, queue and scheduler: PASS without DB permission errors.
- Runtime CREATE/ALTER/TRUNCATE, CREATE/DROP DATABASE and CREATE/ALTER ROLE:
  denied with SQLSTATE 42501.
- Isolated databases and NOLOGIN test roles were removed after evidence capture.

### Production maintenance

- Pre-change custom-format backup: 21,636,543 bytes; TOC and restore test PASS;
  SHA-256 `2f38bd712fc080e56b7f60d8c908fce317b3a6f0b46a80879f172a53f3a755d4`.
- Ownership/grant update: transactional; business rows unchanged.
- Runtime credential switch: app-only replacement, two seconds.
- PostgreSQL restart: NO. Docker daemon restart: NO. Network change: NO.
- Migration profile preflight: `library_migrator`, all dangerous flags false;
  production result `Nothing to migrate`.
- Backup owner/group retained `www-data:www-data`; dump and verification artifact
  modes set to `640`, with an entrypoint safeguard against later widening.
- App replacement exposed a stale embedded nginx config; the canonical config
  was syntax-checked, restored and nginx alone reloaded. The corrected app image
  was rebuilt for future controlled replacements. Redirect and HSTS re-passed.
- Image inspection then found that generic `.dockerignore` rules had admitted a
  production dump. Explicit `storage/backups` and recursive dump exclusions were
  added, the image was rebuilt, and only the app was replaced again. The running
  image contains neither backup artifacts nor `.env`; final browser smoke passed.

## Final gate matrix

### Production

- Availability: PASS (`/`, `/catalog`, `/login` return 200).
- HTTPS: PASS; HTTP redirect preserves `/catalog?lang=ru`; HSTS is
  `max-age=31536000`.
- Database service: PASS. Docker health: UNHEALTHY, classified separately as
  infrastructure/observability debt.
- Database least privilege: PASS. Runtime and migration identities are distinct;
  the admin secret is not present in the app environment.
- Owner admin: the approved account remains AD-linked, active, manual admin;
  the previously verified HTTP admin gate and logout remain the accepted live
  checkpoint. No role mutation was performed.

### Browser

- Runner: PASS using the already-local Playwright 1.59.1 Noble image.
- Public desktop/mobile: console 0, page errors 0, mixed content 0, unexpected
  request failures 0, HTTP 500 responses 0.
- Authenticated Playwright: CONDITIONAL/BLOCKED BY CREDENTIAL AVAILABILITY. No
  authentication bypass, persisted session file or credential logging was used.

### Authentication and sessions

- AD-only staff boundary: PASS in route/config/controller review and isolated
  tests; production demo/test authentication routes are absent.
- LDAP TLS and filter escaping: PASS; LDAPS and peer/name verification fail
  closed, special filter characters are escaped.
- Enumeration, rate limiting, session fixation and logout: PASS in isolated
  regression tests. Anonymous-to-authenticated session rotation was verified
  without printing identifiers.
- Browser cookie attributes: Secure YES, HttpOnly YES, SameSite Lax.

### CSRF and authorization

- Browser state-changing routes audited: 236; session-write CSRF gaps: 0.
- Unsafe state-changing GET: 0. `/password/change` is a form view only.
- API routes inventoried: 93. Protected endpoints reject unauthenticated access.
- RBAC, reader/staff isolation, IDOR and protected file access: PASS across the
  targeted backend matrices. The machine-readable route evidence is
  `docs/runtime/SECURITY-ROUTE-INVENTORY.json`.

### Input and files

- SQL/LDAP injection, XSS, redirect, mass-assignment and traversal boundaries:
  PASS for reviewed representative surfaces and isolated tests. No destructive
  production payload was sent.
- Upload executable/MIME/PDF guards and private storage policies: PASS. The
  flow inventory is `docs/runtime/SECURITY-UPLOAD-INVENTORY.md`.
- Source/config HTTP exposure: PASS (403/404). Backup HTTP exposure: PASS (403);
  host mode is now `640` with the existing owner/group preserved.

### Platform, audit and dependencies

- CORS: PASS after canonical-origin restriction; private credentialed APIs do
  not return wildcard ACAO.
- Debug exposure: no production debug/source response observed. Secret-marker
  scan of representative HTML returned 0 findings.
- Audit access/immutability and nested redaction: PASS in policy/static review
  and regression tests. No secret values were inserted into tests.
- Composer: 0 advisories, 0 abandoned packages.
- npm: Critical 0, High 0, Moderate 4, Low 4. Remaining advisories are in the AI
  SDK/provider and syntax-highlighting chain and have no complete non-breaking
  resolution in the current dependency constraints.
- Reachable Critical: 0. Unresolved High: 0.

## Gate decision

The application-layer controls and PostgreSQL least-privilege boundary pass,
with authenticated browser automation conditional on owner credentials. The
overall result is:

```text
PRODUCTION AVAILABILITY: PASS
HTTPS TRANSPORT: PASS
APPLICATION SECURITY CONTROLS: PASS
DATABASE LEAST PRIVILEGE: PASS
SECURITY HARDENING GATE: PASS
```

## Data and runtime safety

- Database: `digital_library_recovered`; recovery false.
- Records 9,562; copies 50,907; inventory numbers 50,907; barcodes 27; loans 0;
  reservations 0; users 18.
- Migrations: 72 Ran, 0 Pending. PostgreSQL restart count: 0.
- DB reset: NO. PostgreSQL restart: NO. Migrations applied: NO. MARC changes: NO.
- App-only replacement: YES, twice (two and three seconds). One expected startup
  502 occurred during the second replacement; steady-state HTTP 500/SQL errors
  are zero. Docker daemon restart: NO. Nginx reload: YES after a stale-image
  redirect/HSTS regression was detected and corrected.
- Business-data security-test writes: 0. Commit/push: NO.
