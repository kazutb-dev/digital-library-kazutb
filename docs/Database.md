# Database Reference

## Connection

- **Engine**: PostgreSQL 16 (Docker) / PostgreSQL compatible host
- **Dev DB**: `digital_library_dev`
- **Prod DB**: `digital_library_prod`
- **Testing**: SQLite `:memory:` (PHPUnit / CI)

## Migrations (18 files)

| File | Creates / Modifies | Domain |
|------|--------------------|--------|
| `0001_01_01_000000_create_users_table` | `users`, `password_reset_tokens`, `sessions` | Auth |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` | Framework |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` | Queue |
| `2026_03_26_105401_create_personal_access_tokens_table` | `personal_access_tokens` | Sanctum |
| `2026_03_26_110500_add_ad_login_and_role_to_users_table` | `users` (ad_login, role) | Auth |
| `2026_03_31_080810_create_identity_match_logs_table` | `identity_match_logs` | Auth audit |
| `2026_04_01_120000_create_circulation_loans_table` | `circulation_loans` | Circulation |
| `2026_04_01_120100_create_circulation_audit_events_table` | `circulation_audit_events` | Audit |
| `2026_04_01_210000_create_integration_idempotency_keys_table` | `integration_idempotency_keys` | Integration |
| `2026_04_02_100000_add_retirement_fields_to_book_copies` | `book_copies` (retirement fields) | Stewardship |
| `2026_04_03_222605_create_integration_api_log_table` | `integration_api_log` | Integration |
| `2026_04_06_160000_create_literature_drafts_tables` | `literature_drafts`, `literature_draft_items` | Drafts |
| `2026_04_06_180000_create_digital_materials_table` | `digital_materials` | Digital access |
| `2026_04_23_120000_create_member_contact_submissions_table` | `member_contact_submissions` | Reader CRM |
| `2026_04_23_130000_create_scientific_works_table` | `scientific_works` | Repository |
| `2026_04_23_150000_create_admin_external_resources_table` | `admin_external_resources` | Admin |
| `2026_04_24_120000_create_notifications_table` | `notifications` | Laravel notifications |
| `2026_04_24_140000_create_library_news_table` | `library_news` | News CMS |

> **Note**: `books`, `authors`, `categories`, `book_copies` (base tables) are expected to exist  
> from a legacy migration or manual schema. Check the database when live.

## Checking migration status

```bash
# Local (requires PHP 8.4 + DB connection)
php artisan migrate:status

# Via Docker DEV
make dev-migrate
docker compose -f docker-compose.dev.yml exec app php artisan migrate:status
```

## Catalog audit queries

Run these against the live DB to count records:

```sql
-- Core catalog
SELECT 'books'       AS tbl, COUNT(*) FROM books
UNION ALL
SELECT 'authors',           COUNT(*) FROM authors
UNION ALL
SELECT 'book_copies',       COUNT(*) FROM book_copies
UNION ALL
SELECT 'categories',        COUNT(*) FROM categories
UNION ALL
SELECT 'scientific_works',  COUNT(*) FROM scientific_works
UNION ALL
SELECT 'digital_materials', COUNT(*) FROM digital_materials
UNION ALL
SELECT 'library_news',      COUNT(*) FROM library_news;

-- Data quality: books without ISBN
SELECT COUNT(*) AS books_without_isbn FROM books WHERE isbn IS NULL OR isbn = '';

-- Data quality: duplicate ISBNs
SELECT isbn, COUNT(*) c FROM books GROUP BY isbn HAVING COUNT(*) > 1;

-- Data quality: copies without book
SELECT COUNT(*) FROM book_copies bc
LEFT JOIN books b ON b.id = bc.book_id
WHERE b.id IS NULL;
```
