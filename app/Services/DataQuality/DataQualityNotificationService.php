<?php

namespace App\Services\DataQuality;

use App\Models\DataQualityIssue;
use App\Models\DataQualityScanRun;
use App\Models\User;
use App\Services\Catalog\LibraryNotificationService;

class DataQualityNotificationService
{
    public function __construct(private readonly LibraryNotificationService $notifications) {}

    public function assigned(User $assignee, string $issueNumber): void
    {
        $this->notifications->sendLocalized(
            $assignee,
            'data_quality_issue_assigned',
            'data_quality.notifications.assigned_title',
            'data_quality.notifications.assigned_body',
            ['number' => $issueNumber],
            ['issue_number' => $issueNumber],
        );
    }

    public function scanDigest(DataQualityScanRun $run): void
    {
        if (! $run->starter || ($run->issues_created === 0 && $run->issues_reopened === 0)) {
            return;
        }
        $this->notifications->sendLocalized(
            $run->starter,
            'data_quality_critical_digest',
            'data_quality.notifications.scan_title',
            'data_quality.notifications.scan_body',
            [
                'number' => $run->run_number,
                'created' => $run->issues_created,
                'reopened' => $run->issues_reopened,
            ],
            ['scan_run_id' => $run->getKey()],
        );
    }

    public function reopened(DataQualityIssue $issue, ?User $fallback = null): void
    {
        $recipient = $issue->assignee ?? $fallback;
        if (! $recipient) {
            return;
        }

        $this->notifications->sendLocalized(
            $recipient,
            'data_quality_issue_reopened',
            'data_quality.notifications.reopened_title',
            'data_quality.notifications.reopened_body',
            ['number' => $issue->issue_number],
            ['issue_id' => $issue->getKey()],
        );
    }

    public function approvalDigest(string $permission, string $event, string $titleKey, string $bodyKey, array $parameters, array $payload): void
    {
        User::permission($permission)->where('is_active', true)->orderBy('id')->each(
            fn (User $user) => $this->notifications->sendLocalized($user, $event, $titleKey, $bodyKey, $parameters, $payload)
        );
    }
}
