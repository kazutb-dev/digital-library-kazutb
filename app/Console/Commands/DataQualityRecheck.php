<?php

namespace App\Console\Commands;

use App\Models\DataQualityIssue;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Console\Command;

class DataQualityRecheck extends Command
{
    protected $signature = 'library:data-quality:recheck {issue : Issue number or id}';

    protected $description = 'Re-evaluate one persistent data-quality issue';

    public function handle(DataQualityScanner $scanner): int
    {
        $needle = (string) $this->argument('issue');
        $issue = DataQualityIssue::query()
            ->where('issue_number', $needle)
            ->when(is_numeric($needle), fn ($query) => $query->orWhereKey((int) $needle))
            ->firstOrFail();
        $scope = match ($issue->entity_type) {
            'bibliographic_record' => 'bibliographic_records',
            'book_copy' => 'book_copies',
            'reader_profile' => 'reader_profiles',
            default => $issue->entity_type.'s',
        };
        $model = DataQualityScanner::SCOPES[$scope] ?? null;
        $entity = $model === null ? null : $model::query()->find($issue->entity_id);
        if ($entity === null) {
            $this->error('The affected entity no longer exists.');

            return self::FAILURE;
        }
        $scanner->scanModel($entity, $issue->entity_type);
        $this->info($issue->fresh()->issue_number.': '.$issue->fresh()->status);

        return self::SUCCESS;
    }
}
