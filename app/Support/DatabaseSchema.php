<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class DatabaseSchema
{
    /**
     * Schema-qualified PostgreSQL tables are optional integration sources.
     * Treat them as unavailable on other drivers or connection failures.
     */
    public static function hasTable(string $table, ?string $connection = null): bool
    {
        try {
            $database = DB::connection($connection);

            if (str_contains($table, '.') && $database->getDriverName() !== 'pgsql') {
                return false;
            }

            return $database->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
