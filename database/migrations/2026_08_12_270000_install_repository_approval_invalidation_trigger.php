<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        $columns = $this->protectedColumns();
        $updateColumns = implode(', ', array_map(static fn (string $column): string => '"'.$column.'"', $columns));

        if ($driver === 'pgsql') {
            $jsonColumns = ['authors', 'keywords', 'title_translations', 'abstract_translations', 'keyword_translations'];
            $changed = implode("\n                OR ", array_map(
                static fn (string $column): string => in_array($column, $jsonColumns, true)
                    ? '(OLD."'.$column.'")::jsonb IS DISTINCT FROM (NEW."'.$column.'")::jsonb'
                    : 'OLD."'.$column.'" IS DISTINCT FROM NEW."'.$column.'"',
                $columns,
            ));
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION repository_invalidate_changed_approval() RETURNS trigger AS \$\$
                BEGIN
                    IF OLD.active_approval_id IS NOT NULL AND (
                        {$changed}
                    ) THEN
                        NEW.active_approval_id := NULL;
                        NEW.approved_by := NULL;
                        NEW.reviewed_by := NULL;
                        NEW.scheduled_for := NULL;
                        NEW.published_at := NULL;
                        IF NEW.status IN ('approved', 'scheduled', 'published', 'embargoed') THEN
                            NEW.status := 'metadata_review';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS repository_items_invalidate_changed_approval ON repository_items;
                CREATE TRIGGER repository_items_invalidate_changed_approval
                BEFORE UPDATE OF {$updateColumns} ON repository_items
                FOR EACH ROW EXECUTE FUNCTION repository_invalidate_changed_approval();
                SQL);

            return;
        }

        if ($driver === 'sqlite') {
            $changed = implode("\n                    OR ", array_map(
                static fn (string $column): string => 'OLD."'.$column.'" IS NOT NEW."'.$column.'"',
                $columns,
            ));
            DB::unprepared('DROP TRIGGER IF EXISTS repository_items_invalidate_changed_approval;');
            DB::unprepared(<<<SQL
                CREATE TRIGGER repository_items_invalidate_changed_approval
                AFTER UPDATE OF {$updateColumns} ON repository_items
                FOR EACH ROW
                WHEN OLD.active_approval_id IS NOT NULL AND (
                    {$changed}
                )
                BEGIN
                    UPDATE repository_items
                    SET active_approval_id = NULL,
                        approved_by = NULL,
                        reviewed_by = NULL,
                        scheduled_for = NULL,
                        published_at = NULL,
                        status = CASE
                            WHEN NEW.status IN ('approved', 'scheduled', 'published', 'embargoed') THEN 'metadata_review'
                            ELSE NEW.status
                        END
                    WHERE id = NEW.id;
                END;
                SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS repository_items_invalidate_changed_approval ON repository_items;');
            DB::unprepared('DROP FUNCTION IF EXISTS repository_invalidate_changed_approval();');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS repository_items_invalidate_changed_approval;');
        }
    }

    /** @return list<string> */
    private function protectedColumns(): array
    {
        return [
            'title', 'authors', 'work_type', 'year', 'department', 'udc_code',
            'abstract', 'keywords', 'language', 'title_translations', 'original_title',
            'abstract_translations', 'keyword_translations', 'supervisor', 'reviewer',
            'university', 'faculty', 'educational_programme', 'degree_level',
            'defence_date', 'publication_date', 'page_count', 'bibliography', 'doi',
            'isbn_issn', 'source', 'rights_holder', 'copyright_status', 'licence_type',
            'licence_text', 'permission_document_path', 'permission_date', 'access_policy',
            'embargo_until', 'post_embargo_access_policy', 'file_path', 'file_name',
            'file_size', 'checksum_sha256', 'version_number',
        ];
    }
};
