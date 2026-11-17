<?php

namespace App\Console\Commands;

use App\Services\QueueService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('queue-shop:process')]
#[Description('Burn overdue calls and send due WhatsApp reminders')]
class ProcessQueue extends Command
{
    public function handle(QueueService $queue): int
    {
        $burned = $queue->expireOverdueCalls();
        $reminded = $queue->sendDueReminders();

        $this->info("Burned {$burned}, reminded {$reminded}.");

        return self::SUCCESS;
    }
}
