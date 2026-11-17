<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('queue-shop:process-bookings')]
#[Description('Release late bookings and send booking reminders')]
class ProcessBookings extends Command
{
    public function handle(BookingService $bookings): int
    {
        $released = $bookings->releaseNoShows();
        $reminded = $bookings->sendDueReminders();

        $this->info("Released {$released}, reminded {$reminded}.");

        return self::SUCCESS;
    }
}
