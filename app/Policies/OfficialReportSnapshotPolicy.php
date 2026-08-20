<?php

namespace App\Policies;

use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Models\User;

class OfficialReportSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.official.archive');
    }

    public function view(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return $user->can('reports.official.archive')
            || (int) $snapshot->created_by === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('reports.official.create');
    }

    public function submit(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return $snapshot->status === 'generated'
            && $user->can('reports.official.submit')
            && ((int) $snapshot->created_by === (int) $user->getKey() || $user->hasRole('senior_librarian'));
    }

    public function revise(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return in_array($snapshot->status, ['approved', 'rejected', 'superseded'], true)
            && $user->can('reports.official.create');
    }

    public function approve(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return $snapshot->status === 'pending_review'
            && $user->hasRole('director')
            && $user->can('reports.official.approve')
            && (int) $snapshot->created_by !== (int) $user->getKey()
            && (int) $snapshot->submitted_by !== (int) $user->getKey();
    }

    public function reject(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return $this->approve($user, $snapshot);
    }

    public function delete(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return in_array($snapshot->status, ['draft', 'generated'], true)
            && $user->can('reports.official.delete_draft')
            && (int) $snapshot->created_by === (int) $user->getKey();
    }

    public function downloadSource(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return $this->view($user, $snapshot) && $user->can('reports.official.archive');
    }

    public function export(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return in_array($snapshot->status, ['approved', 'superseded', 'archived'], true)
            && $user->can('reports.official.export')
            && $this->view($user, $snapshot);
    }

    public function viewExport(User $user, ReportExportJob $export): bool
    {
        return $user->can('reports.official.archive')
            || (int) $export->requested_by === (int) $user->getKey();
    }

    public function downloadExport(User $user, ReportExportJob $export): bool
    {
        return $export->status === 'ready'
            && $user->can('reports.official.export')
            && $this->viewExport($user, $export);
    }

    public function archive(User $user, OfficialReportSnapshot $snapshot): bool
    {
        return in_array($snapshot->status, ['approved', 'superseded'], true)
            && $user->can('reports.official.archive');
    }
}
