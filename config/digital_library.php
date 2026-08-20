<?php

$defaultExpiryNoticeDays = [0, 1, 7, 14, 30, 60, 90];
$configuredExpiryNoticeDays = [];
foreach (explode(',', (string) env('EXTERNAL_RESOURCE_EXPIRY_NOTICE_DAYS', implode(',', $defaultExpiryNoticeDays))) as $value) {
    $value = trim($value);
    if (preg_match('/^\d{1,4}$/', $value) === 1) {
        $days = (int) $value;
        if ($days <= 3650) {
            $configuredExpiryNoticeDays[] = $days;
        }
    }
}
$configuredExpiryNoticeDays = array_values(array_unique($configuredExpiryNoticeDays));
sort($configuredExpiryNoticeDays, SORT_NUMERIC);

return [
    'author_self_submission' => false,
    'external_resource_expiry_notice_days' => $configuredExpiryNoticeDays ?: $defaultExpiryNoticeDays,
    'external_resource_health_timeout' => max(2, min(30, (int) env('EXTERNAL_RESOURCE_HEALTH_TIMEOUT', 8))),
    'external_resource_health_connect_timeout' => max(1, min(15, (int) env('EXTERNAL_RESOURCE_HEALTH_CONNECT_TIMEOUT', 4))),
    'external_resource_analytics_retention_days' => max(1, min(3650, (int) env('EXTERNAL_RESOURCE_ANALYTICS_RETENTION_DAYS', 395))),
    // Anonymous daily repository counters are useful for longitudinal official
    // reporting, but are still bounded so the analytics table cannot grow
    // forever. No reader identifier or precise timestamp is retained.
    'repository_usage_retention_days' => max(30, min(3650, (int) env('REPOSITORY_USAGE_RETENTION_DAYS', 1095))),
    'ocr' => ['enabled' => false, 'engine' => env('DIGITAL_OCR_ENGINE')],
    'presentation_preview' => ['enabled' => false, 'converter' => env('DIGITAL_PRESENTATION_CONVERTER')],
];
