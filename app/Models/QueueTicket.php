<?php

namespace App\Models;

use App\Enums\QueueTicketStatus;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'barber_id', 'queue_date', 'number', 'status', 'is_free_cut', 'called_at', 'call_expires_at', 'checked_in_at', 'served_at', 'reminded_at'])]
class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueTicketStatus::class,
            'queue_date' => 'date',
            'is_free_cut' => 'boolean',
            'called_at' => 'datetime',
            'call_expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'served_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * Queue numbers restart at 001 each day and are shown with at least 3 digits.
     */
    public static function formatNumber(int $number): string
    {
        return str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function getDisplayNumberAttribute(): string
    {
        return self::formatNumber($this->number);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Barber, $this>
     */
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    /**
     * @param  Builder<QueueTicket>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('queue_date', today());
    }

    /**
     * @param  Builder<QueueTicket>  $query
     */
    public function scopeWithStatus(Builder $query, QueueTicketStatus ...$statuses): void
    {
        $query->whereIn('status', array_map(fn (QueueTicketStatus $s) => $s->value, $statuses));
    }
}
