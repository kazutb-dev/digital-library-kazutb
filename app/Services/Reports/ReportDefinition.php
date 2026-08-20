<?php

namespace App\Services\Reports;

final readonly class ReportDefinition
{
    /**
     * @param  list<string>  $filters
     * @param  list<string>  $columns
     * @param  array{key:string,direction:'asc'|'desc'}  $defaultSort
     * @param  list<string>  $totals
     * @param  list<string>  $charts
     * @param  list<string>  $exports
     */
    public function __construct(
        public string $code,
        public string $titleKey,
        public string $descriptionKey,
        public string $dataset,
        public array $filters,
        public array $columns,
        public array $defaultSort,
        public array $totals,
        public array $charts,
        public array $exports,
        public string $permission,
        public bool $official,
        public bool $snapshotSupport,
        public bool $scheduleSupport,
        public string $sensitivityClass,
    ) {}
}
