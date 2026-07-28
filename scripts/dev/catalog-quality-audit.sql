-- Non-destructive catalog quality audit.
-- Run only after connecting to the target PostgreSQL database.

-- 1) Object existence snapshot
SELECT table_schema, table_name
FROM information_schema.tables
WHERE table_schema IN ('app', 'public', 'review')
ORDER BY table_schema, table_name;

-- 2) Core counts (only for objects that usually exist in legacy schema)
-- documents
SELECT 'app.documents' AS object_name, COUNT(*)::bigint AS row_count
FROM app.documents;

SELECT 'app.book_copies' AS object_name, COUNT(*)::bigint AS row_count
FROM app.book_copies;

SELECT 'app.readers' AS object_name, COUNT(*)::bigint AS row_count
FROM app.readers;

SELECT 'app.reader_contacts' AS object_name, COUNT(*)::bigint AS row_count
FROM app.reader_contacts;

SELECT 'app.authors' AS object_name, COUNT(*)::bigint AS row_count
FROM app.authors;

SELECT 'app.publishers' AS object_name, COUNT(*)::bigint AS row_count
FROM app.publishers;

SELECT 'app.subjects' AS object_name, COUNT(*)::bigint AS row_count
FROM app.subjects;

SELECT 'app.circulation_loans' AS object_name, COUNT(*)::bigint AS row_count
FROM app.circulation_loans;

SELECT 'app.digital_materials' AS object_name, COUNT(*)::bigint AS row_count
FROM app.digital_materials;

-- 3) Data quality checks (best-effort, skip manually if column names differ)
-- Empty/blank ISBN in detail view
SELECT 'empty_isbn_in_detail_view' AS check_name, COUNT(*)::bigint AS bad_rows
FROM app.document_detail_v
WHERE COALESCE(TRIM(isbn), '') = '';

-- Duplicate ISBN in detail view
SELECT 'duplicate_isbn_in_detail_view' AS check_name, COUNT(*)::bigint AS duplicate_groups
FROM (
  SELECT isbn
  FROM app.document_detail_v
  WHERE COALESCE(TRIM(isbn), '') <> ''
  GROUP BY isbn
  HAVING COUNT(*) > 1
) d;

-- Copies without document parent
SELECT 'copies_without_document' AS check_name, COUNT(*)::bigint AS bad_rows
FROM app.book_copies bc
LEFT JOIN app.documents d ON d.id = bc.document_id
WHERE d.id IS NULL;

-- Reader contacts orphaned from readers
SELECT 'orphan_reader_contacts' AS check_name, COUNT(*)::bigint AS bad_rows
FROM app.reader_contacts rc
LEFT JOIN app.readers r ON r.id = rc.reader_id
WHERE r.id IS NULL;

-- 4) Database size + largest tables
SELECT pg_size_pretty(pg_database_size(current_database())) AS database_size;

SELECT
  n.nspname AS schema_name,
  c.relname AS object_name,
  pg_size_pretty(pg_total_relation_size(c.oid)) AS total_size
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE c.relkind = 'r'
  AND n.nspname IN ('app', 'public', 'review')
ORDER BY pg_total_relation_size(c.oid) DESC
LIMIT 30;
