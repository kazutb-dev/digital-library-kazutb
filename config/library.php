<?php

return [
    'reports' => [
        'official_retention_years' => max(1, (int) env('REPORTS_OFFICIAL_RETENTION_YEARS', 10)),
        'export_retention_days' => max(1, (int) env('REPORTS_EXPORT_RETENTION_DAYS', 365)),
        'max_custom_period_days' => max(1, (int) env('REPORTS_MAX_CUSTOM_PERIOD_DAYS', 366)),
        'max_live_rows' => max(100, (int) env('REPORTS_MAX_LIVE_ROWS', 10000)),
        'max_export_bytes' => max(1048576, (int) env('REPORTS_MAX_EXPORT_BYTES', 52428800)),
        'export_lease_seconds' => max(60, (int) env('REPORTS_EXPORT_LEASE_SECONDS', 300)),
        'export_dispatch_batch' => max(1, (int) env('REPORTS_EXPORT_DISPATCH_BATCH', 100)),
        'export_user_active_limit' => max(1, (int) env('REPORTS_EXPORT_USER_ACTIVE_LIMIT', 4)),
        'expired_snapshot_action' => env('REPORTS_EXPIRED_SNAPSHOT_ACTION', 'preserve'),
    ],
];
