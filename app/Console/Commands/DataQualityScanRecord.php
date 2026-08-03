<?php

namespace App\Console\Commands;

use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Console\Command;
use RuntimeException;

class DataQualityScanRecord extends Command
{
    protected $signature = 'library:data-quality:scan-record {scope} {id}';

    protected $description = 'Scan one record immediately after save or on demand';

    public function handle(DataQualityScanner $scanner): int
    {
        $scope = (string) $this->argument('scope');
        $model = DataQualityScanner::SCOPES[$scope] ?? throw new RuntimeException('Unknown scan scope.');
        $entity = $model::query()->findOrFail($this->argument('id'));
        $result = $scanner->scanModel($entity, DataQualityScanner::ENTITY_TYPES[$scope]);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
