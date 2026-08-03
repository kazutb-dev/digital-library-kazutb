<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integer validation receives form values as numeric strings. Normalize
     * already-saved rows so the key-value type contract matches `type`.
     */
    public function up(): void
    {
        $this->rewrite(static fn (mixed $value): mixed => is_numeric($value) ? (int) $value : $value);
    }

    public function down(): void
    {
        $this->rewrite(static fn (mixed $value): mixed => is_int($value) ? (string) $value : $value);
    }

    private function rewrite(callable $normalizer): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('type', 'integer')
            ->orderBy('id')
            ->get(['id', 'value'])
            ->each(function (object $row) use ($normalizer): void {
                $decoded = json_decode((string) $row->value, true);
                $normalized = $normalizer($decoded);

                if ($normalized !== $decoded) {
                    DB::table('settings')
                        ->where('id', $row->id)
                        ->update(['value' => json_encode($normalized, JSON_THROW_ON_ERROR)]);
                }
            });
    }
};
