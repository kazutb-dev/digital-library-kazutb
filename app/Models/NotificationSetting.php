<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-event-type notification channel configuration. Pure settings storage:
 * the circulation/reservation modules consult this matrix once they start
 * sending notifications for real.
 */
class NotificationSetting extends Model
{
    /**
     * Canonical notification event dictionary (Historical.md §25.2).
     *
     * @var list<string>
     */
    public const EVENT_TYPES = [
        'reservation_created',
        'reservation_queued',
        'reservation_copy_assigned',
        'reservation_in_transit',
        'reservation_expiry_reminder',
        'reservation_extended',
        'reservation_fulfilled',
        'reservation_restriction',
        'reservation_confirmed',
        'reservation_ready',
        'reservation_expired',
        'reservation_cancelled',
        'loan_due_soon',
        'loan_overdue',
        'loan_renewed',
        'digital_access_granted',
        'external_resource_licence',
        'external_resource_health',
        'repository_status_changed',
        'repository_published',
        'news_published',
        'news_pending_review',
        'news_changes_requested',
        'news_approved',
        'news_scheduled',
        'news_archived',
        'news_cancelled',
        'news_emergency',
        'message_received',
        'message_status_changed',
        'message_registered',
        'message_assigned',
        'message_critical',
        'message_priority_raised',
        'message_clarification_requested',
        'message_staff_replied',
        'message_internal_note',
        'message_response_prepared',
        'message_response_returned',
        'message_resolved',
        'message_rejected',
        'message_reopened',
        'message_user_replied',
        'message_sla_reminder',
        'message_sla_breached',
        'incident_opened',
        'incident_awaiting_replacement',
        'incident_candidate_submitted',
        'incident_replacement_approved',
        'incident_replacement_rejected',
        'incident_other_edition_required',
        'incident_fine_assigned',
        'incident_due_soon',
        'incident_resolved',
        'data_quality_issue_assigned',
        'data_quality_critical_digest',
        'data_quality_issue_overdue_digest',
        'data_quality_merge_approval_required',
        'data_quality_bulk_approval_required',
        'data_quality_import_review_required',
        'data_quality_import_completed_with_errors',
        'data_quality_issue_reopened',
        'data_quality_score_declined',
        'report_export_ready',
        'report_export_failed',
    ];

    protected $fillable = [
        'event_type',
        'in_app_enabled',
        'email_enabled',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    /**
     * Whether a channel is enabled for an event type; unknown events fall
     * back to enabled so a missing row never silently drops a notification.
     */
    public static function channelEnabled(string $eventType, string $channel): bool
    {
        $setting = static::query()->where('event_type', $eventType)->first();

        if ($setting === null) {
            return true;
        }

        return $channel === 'email' ? $setting->email_enabled : $setting->in_app_enabled;
    }
}
