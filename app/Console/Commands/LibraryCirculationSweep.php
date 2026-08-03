<?php

namespace App\Console\Commands;

use App\Services\Catalog\CirculationService;
use App\Services\Catalog\IncidentCaseService;
use App\Services\Catalog\ReservationQueueService;
use Illuminate\Console\Command;

class LibraryCirculationSweep extends Command
{
    protected $signature = 'library:circulation-sweep';

    protected $description = 'Mark overdue loans, warn readers about approaching due dates, and expire uncollected reservations';

    public function handle(CirculationService $circulation, ReservationQueueService $reservations, IncidentCaseService $incidents): int
    {
        $loanStats = $circulation->sweepOverdue();
        $reservationStats = $reservations->sweepExpired();
        $incidentWarnings = $incidents->notifyDueSoon();

        $this->info(sprintf(
            'Overdue marked: %d, due-soon warned: %d, reservations expired: %d, incident deadlines warned: %d',
            $loanStats['overdue'],
            $loanStats['due_soon'],
            $reservationStats['expired'],
            $incidentWarnings,
        ));

        return self::SUCCESS;
    }
}
