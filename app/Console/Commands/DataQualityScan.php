<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Console\Command;

class DataQualityScan extends Command
{
    protected $signature = 'library:data-quality:scan
        {scope=all : all, bibliographic_records, book_copies, reader_profiles, loans, fines or reservations}
        {--started-by= : Local user id}
        {--queue-only : Create a tracked run without executing it}';

    protected $description = 'Run the chunked, idempotent persistent data-quality scanner';

    public function handle(DataQualityScanner $scanner): int
    {
        $actor = $this->option('started-by') ? User::query()->find($this->option('started-by')) : null;
        $run = $scanner->start((string) $this->argument('scope'), $actor);
        $this->info("Queued {$run->run_number}");
        if (! $this->option('queue-only')) {
            $run = $scanner->execute($run);
            $this->table(
                ['run', 'status', 'records', 'found', 'created', 'reopened', 'resolved', 'ms'],
                [[
                    $run->run_number, $run->status, $run->records_scanned, $run->issues_found,
                    $run->issues_created, $run->issues_reopened, $run->issues_resolved_automatically, $run->duration_ms,
                ]],
            );
        }

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
