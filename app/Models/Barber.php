<?php

namespace App\Models;

use Database\Factories\BarberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_available'])]
class Barber extends Model
{
    /** @use HasFactory<BarberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
