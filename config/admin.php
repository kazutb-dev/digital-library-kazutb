<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operational controls
    |--------------------------------------------------------------------------
    |
    | Backup and recovery are intentionally not executed from a web request.
    | Production operators can expose their orchestrator/runbook identifiers
    | here without placing database credentials in the admin UI.
    |
    */
    'backup' => [
        'provider' => env('BACKUP_PROVIDER'),
        'schedule' => env('BACKUP_SCHEDULE'),
        'last_success_at' => env('BACKUP_LAST_SUCCESS_AT'),
        'recovery_runbook' => env('RECOVERY_RUNBOOK_URL'),
    ],
];
