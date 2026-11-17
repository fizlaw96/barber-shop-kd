<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Booked = 'booked';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Whether the booking still occupies the barber's time.
     */
    public function blocksSlot(): bool
    {
        return in_array($this, [self::Booked, self::Arrived], true);
    }
}
