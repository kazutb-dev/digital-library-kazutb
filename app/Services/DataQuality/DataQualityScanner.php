<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\DataQualityIssue;
use App\Models\DataQualityScanRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DataQualityScanner
{
    /** @var array<string,class-string<Model>> */
    public const SCOPES = [
        'bibliographic_records' => BibliographicRecord::class,
        'book_copies' => BookCopy::class,
        'reader_profiles' => ReaderProfile::class,
        'loans' => Loan::class,
        'fines' => Fine::class,
        'reservations' => Reservation::class,
    ];

    public const ENTITY_TYPES = [
        'bibliographic_records' => 'bibliographic_record',
        'book_copies' => 'book_copy',
        'reader_profiles' => 'reader_profile',
        'loans' => 'loan',
        'fines' => 'fine',
        'reservations' => 'reservation',
    ];

    public function __construct(
        private readonly DataQualityRuleRegistry $rules,
        private readonly AuditLogger $audit,
        private readonly DataQualityNotificationService $notifications,
    ) {}

    public function start(string $scope = 'all', ?User $actor = null): DataQualityScanRun
    {
        if ($scope !== 'all' && ! array_key_exists($scope, self::SCOPES)) {
            throw new RuntimeException("Unsupported data quality scope: {$scope}");
        }

        return DB::transaction(function () use ($scope, $actor): DataQualityScanRun {
            $duplicate = DataQualityScanRun::query()
                ->where('scope', $scope)
                ->whereIn('status', ['queued', 'running'])
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw new RuntimeException("A {$scope} scan is already running.");
            }

            $run = DataQualityScanRun::query()->create([
                'run_number' => 'DQS-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'scope' => $scope,
                'status' => 'queued',
                'started_by' => $actor?->getKey(),
                'rules_version' => DataQualityRuleRegistry::VERSION,
            ]);
            $this->audit->logRequired(
                'data_quality.scan_started',
                'data_quality_scan_run',
                $run->getKey(),
                newValues: $run->toArray(),
                scope: 'operational',
                actor: $actor,
            );

            return $run;
        });
    }

    public function execute(DataQualityScanRun $run): DataQualityScanRun
    {
        $started = hrtime(true);
        $run = DB::transaction(function () use ($run): DataQualityScanRun {
            $locked = DataQualityScanRun::query()->lockForUpdate()->findOrFail($run->getKey());
            if (! in_array($locked->status, ['queued', 'failed'], true)) {
                throw new RuntimeException("Scan {$locked->run_number} cannot start from {$locked->status}.");
            }
            $locked->update(['status' => 'running', 'started_at' => now(), 'error_message' => null]);

            return $locked;
        });

        $counters = ['records_scanned' => 0, 'issues_found' => 0, 'issues_created' => 0, 'issues_reopened' => 0, 'issues_resolved_automatically' => 0];
        $errors = [];

        foreach ($this->scopeModels($run->scope) as $scope => $modelClass) {
            $entityType = self::ENTITY_TYPES[$scope];
            /** @var Builder $query */
            $query = $modelClass::query()->orderBy((new $modelClass)->getQualifiedKeyName());
            $query->chunkById($this->chunkSize(), function ($models) use ($run, $entityType, &$counters, &$errors): bool {
                foreach ($models as $model) {
                    if ($run->fresh()->status === 'cancelled') {
                        return false;
                    }
                    try {
                        $result = $this->scanModel($model, $entityType, $run);
                        foreach ($result as $key => $value) {
                            $counters[$key] += $value;
                        }
                    } catch (Throwable $exception) {
                        $errors[] = "{$entityType}:{$model->getKey()}: ".$exception->getMessage();
                    }
                }

                DataQualityScanRun::query()->whereKey($run->getKey())->update($counters);

                return true;
            });

            if ($run->fresh()->status === 'cancelled') {
                break;
            }
        }

        $run->refresh();
        if ($run->status !== 'cancelled') {
            $run->update($counters + [
                'status' => $errors === [] ? 'completed' : 'completed_with_errors',
                'finished_at' => now(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'error_message' => $errors === [] ? null : mb_substr(implode("\n", $errors), 0, 65000),
            ]);
        }
        $this->audit->logRequired(
            'data_quality.scan_completed',
            'data_quality_scan_run',
            $run->getKey(),
            newValues: $run->fresh()->toArray(),
            scope: 'operational',
            actor: $run->starter,
            metadata: ['error_count' => count($errors)],
        );
        $this->notifications->scanDigest($run->fresh());

        return $run->fresh();
    }

    /**
     * @return array{records_scanned:int,issues_found:int,issues_created:int,issues_reopened:int,issues_resolved_automatically:int}
     */
    public function scanModel(Model $model, string $entityType, ?DataQualityScanRun $run = null): array
    {
        return DB::transaction(function () use ($model, $entityType, $run): array {
            $violations = $this->rules->inspect($model);
            $seen = [];
            $created = 0;
            $reopened = 0;

            foreach ($violations as $violation) {
                $fingerprint = $this->fingerprint($entityType, (string) $model->getKey(), $violation['code'], $violation['field']);
                $seen[] = $fingerprint;
                $issue = DataQualityIssue::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();

                if ($issue === null) {
                    $issue = DataQualityIssue::query()->create([
                        'issue_number' => 'DQI-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                        'entity_type' => $entityType,
                        'entity_id' => (string) $model->getKey(),
                        'rule_code' => $violation['code'],
                        'category' => $violation['category'],
                        'severity' => $this->severityFor($violation['code'], $violation['severity']),
                        'status' => 'open',
                        'field_name' => $violation['field'],
                        'current_value' => $this->stringValue($violation['value']),
                        'expected_format' => $violation['expected'],
                        'description' => $violation['description'],
                        'suggested_action' => $violation['suggested_action'],
                        'fingerprint' => $fingerprint,
                        'scan_run_id' => $run?->getKey(),
                        'first_detected_at' => now(),
                        'last_detected_at' => now(),
                        'due_at' => now()->addHours($this->slaHours($violation['severity'])),
                        'context' => $violation['context'] ?: null,
                    ]);
                    $created++;
                    $this->audit->logRequired(
                        'data_quality.issue_created',
                        'data_quality_issue',
                        $issue->getKey(),
                        newValues: $issue->toArray(),
                        scope: 'operational',
                        actor: $run?->starter,
                    );
                } else {
                    $wasClosed = in_array($issue->status, ['resolved', 'ignored', 'false_positive'], true)
                        && ! ($issue->status === 'ignored' && $issue->ignored_until?->isFuture());
                    $issue->update([
                        'status' => $wasClosed ? 'reopened' : $issue->status,
                        'current_value' => $this->stringValue($violation['value']),
                        'last_detected_at' => now(),
                        'occurrence_count' => $issue->occurrence_count + 1,
                        'scan_run_id' => $run?->getKey(),
                        'resolved_at' => $wasClosed ? null : $issue->resolved_at,
                        'resolved_by' => $wasClosed ? null : $issue->resolved_by,
                        'context' => $violation['context'] ?: null,
                    ]);
                    if ($wasClosed) {
                        $reopened++;
                        $this->audit->logRequired(
                            'data_quality.issue_reopened',
                            'data_quality_issue',
                            $issue->getKey(),
                            newValues: $issue->fresh()->toArray(),
                            scope: 'operational',
                            actor: $run?->starter,
                        );
                        $this->notifications->reopened($issue->fresh('assignee'), $run?->starter);
                    }
                }
            }

            $resolved = DataQualityIssue::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', (string) $model->getKey())
                ->actionable()
                ->when($seen !== [], fn (Builder $query) => $query->whereNotIn('fingerprint', $seen))
                ->get();
            foreach ($resolved as $issue) {
                $old = $issue->toArray();
                $issue->update([
                    'status' => 'resolved',
                    'resolution_type' => 'rule_no_longer_violated',
                    'resolution_notes' => __('data_quality.messages.verified_by_rescan'),
                    'resolved_at' => now(),
                ]);
                $this->audit->logRequired(
                    'data_quality.issue_resolved',
                    'data_quality_issue',
                    $issue->getKey(),
                    oldValues: $old,
                    newValues: $issue->fresh()->toArray(),
                    scope: 'operational',
                    actor: $run?->starter,
                );
            }

            return [
                'records_scanned' => 1,
                'issues_found' => count($violations),
                'issues_created' => $created,
                'issues_reopened' => $reopened,
                'issues_resolved_automatically' => $resolved->count(),
            ];
        });
    }

    public function cancel(DataQualityScanRun $run, User $actor): DataQualityScanRun
    {
        return DB::transaction(function () use ($run, $actor): DataQualityScanRun {
            $run = DataQualityScanRun::query()->lockForUpdate()->findOrFail($run->getKey());
            if (! in_array($run->status, ['queued', 'running'], true)) {
                throw new RuntimeException('Only a queued or running scan can be cancelled.');
            }
            $run->update(['status' => 'cancelled', 'cancelled_at' => now(), 'finished_at' => now()]);
            $this->audit->logRequired('data_quality.scan_cancelled', 'data_quality_scan_run', $run->getKey(), newValues: $run->toArray(), scope: 'operational', actor: $actor);

            return $run;
        });
    }

    public function fingerprint(string $entityType, string $entityId, string $ruleCode, ?string $field): string
    {
        return hash('sha256', implode('|', [$entityType, $entityId, $ruleCode, $field ?? '']));
    }

    /** @return array<string,class-string<Model>> */
    private function scopeModels(string $scope): array
    {
        return $scope === 'all' ? self::SCOPES : [$scope => self::SCOPES[$scope]];
    }

    private function chunkSize(): int
    {
        return min(5000, max(50, (int) Setting::valueFor('data_quality_scan_chunk_size', 500)));
    }

    private function slaHours(string $severity): int
    {
        return (int) Setting::valueFor("data_quality_sla_{$severity}_hours", match ($severity) {
            'critical' => 24,
            'high' => 72,
            'medium' => 168,
            default => 720,
        });
    }

    private function severityFor(string $ruleCode, string $default): string
    {
        return (string) Setting::valueFor("data_quality_severity_{$ruleCode}", $default);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE), 0, 65535);
    }
}
