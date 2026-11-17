<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'name', 'points', 'free_cuts_redeemed', 'notifications_enabled'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public const POINTS_FOR_FREE_CUT = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notifications_enabled' => 'boolean'];
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function hasFreeCutAvailable(): bool
    {
        return $this->points >= self::POINTS_FOR_FREE_CUT;
    }

    /**
     * Digits only, in international format: a leading 0 becomes the default country code.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '0')
            ? config('queue_shop.default_country_code').substr($digits, 1)
            : $digits;
    }
}
