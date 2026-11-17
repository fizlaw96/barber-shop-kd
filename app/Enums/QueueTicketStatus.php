<?php

namespace App\Enums;

enum QueueTicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InChair = 'in_chair';
    case Completed = 'completed';
    case Burned = 'burned';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::Called, self::InChair], true);
    }
}
