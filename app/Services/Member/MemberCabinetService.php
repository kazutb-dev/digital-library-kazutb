<?php

namespace App\Services\Member;

use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class MemberCabinetService
{
    /** @return Collection<int, array{key:string,severity:string,message:string}> */
    public function restrictions(User $reader): Collection
    {
        $profile = ReaderProfile::forUser($reader);
        $rows = collect();
        if (! $reader->is_active || $profile->status !== 'active') {
            $rows->push(['key' => 'profile', 'severity' => 'critical', 'message' => $profile->block_reason ?: __('librarian.member_portal.restrictions.inactive')]);
        }
        if ((bool) Setting::valueFor('overdue_blocking_enabled', true) && Loan::query()->open()->where('user_id', $reader->getKey())->get()->contains(fn (Loan $loan): bool => $loan->isOverdue())) {
            $rows->push(['key' => 'overdue', 'severity' => 'critical', 'message' => __('librarian.member_portal.restrictions.overdue')]);
        }
        if (Fine::query()->where('user_id', $reader->getKey())->where('status', 'pending')->exists()) {
            $rows->push(['key' => 'fine', 'severity' => 'warning', 'message' => __('librarian.member_portal.restrictions.fine')]);
        }
        if (CirculationIncidentCase::query()->open()->where('reader_id', $reader->getKey())->exists()) {
            $rows->push(['key' => 'incident', 'severity' => 'critical', 'message' => __('librarian.member_portal.restrictions.incident')]);
        }

        return $rows;
    }

    public function canRenew(Loan $loan, User $reader): array
    {
        if ((int) $loan->user_id !== (int) $reader->getKey()) {
            return ['allowed' => false, 'reason' => 'not_own'];
        }
        if (! in_array($loan->status, Loan::OPEN_STATUSES, true)) {
            return ['allowed' => false, 'reason' => 'closed'];
        }
        if ($loan->isOverdue()) {
            return ['allowed' => false, 'reason' => 'overdue'];
        }
        if ($loan->renewal_count >= (int) Setting::valueFor('max_renewals', 1)) {
            return ['allowed' => false, 'reason' => 'limit'];
        }
        if ($this->restrictions($reader)->isNotEmpty()) {
            return ['allowed' => false, 'reason' => 'restricted'];
        }
        $waiting = Reservation::query()->where('bibliographic_record_id', $loan->copy?->bibliographic_record_id)
            ->where('user_id', '!=', $reader->getKey())->whereIn('status', Reservation::ACTIVE_STATUSES)->exists();

        return ['allowed' => ! $waiting, 'reason' => $waiting ? 'queue' : null];
    }
}
