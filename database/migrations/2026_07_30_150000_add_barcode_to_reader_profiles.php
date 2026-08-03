<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ДИР §9.4 — a scannable code on the reader's card itself, so the circulation
 * desk and the visit counter can identify a reader with one scan instead of
 * typing a name. Mirrors book_copies.barcode: nullable and unique.
 *
 * Existing tickets are backfilled here — a card without a code would be
 * unscannable, which defeats the point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->string('barcode', 64)->nullable()->unique()->after('ticket_number');
        });

        // Backfill in id order so the sequence is stable and reproducible.
        $sequence = 1;
        foreach (DB::table('reader_profiles')->orderBy('id')->pluck('id') as $id) {
            do {
                $candidate = sprintf('RDR%08d', $sequence);
                $sequence++;
            } while (DB::table('reader_profiles')->where('barcode', $candidate)->exists());

            DB::table('reader_profiles')->where('id', $id)->update(['barcode' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
