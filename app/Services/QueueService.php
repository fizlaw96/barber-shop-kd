<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\QueueTicketStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Services\Whatsapp\WhatsappSender;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueService
{
    public function __construct(private WhatsappSender $whatsapp, private ShopSettings $settings) {}

    /**
     * Register a walk-in. A customer holding an active ticket today gets that ticket back.
     */
    public function join(string $phone, ?string $name = null, bool $notificationsEnabled = true): QueueTicket
    {
        $customer = Customer::firstOrCreate(['phone' => Customer::normalizePhone($phone)]);
        $customer->fill(['notifications_enabled' => $notificationsEnabled]);
        if ($name) {
            $customer->name = $name;
        }
        $customer->save();

        $existing = $this->activeTicketFor($customer);
        if ($existing) {
            return $existing;
        }

        $ticket = DB::transaction(function () use ($customer): QueueTicket {
            $next = (int) QueueTicket::today()->lockForUpdate()->max('number') + 1;

            return QueueTicket::create([
                'customer_id' => $customer->id,
                'queue_date' => today(),
                'number' => $next,
                'status' => QueueTicketStatus::Waiting,
            ]);
        });

        $this->notify($ticket, sprintf(
            'Hi %s! Your queue number is #%d. Estimated wait: about %d min. We will message you 1 minute before your turn.',
            $customer->name ?? 'there',
            $ticket->number,
            $this->estimatedWaitMinutes($ticket),
        ));

        return $ticket;
    }

    public function activeTicketFor(Customer $customer): ?QueueTicket
    {
        return $customer->queueTickets()->today()
            ->withStatus(QueueTicketStatus::Waiting, QueueTicketStatus::Called, QueueTicketStatus::InChair)
            ->first();
    }

    public function callNext(Barber $barber): ?QueueTicket
    {
        return DB::transaction(function () use ($barber): ?QueueTicket {
            $ticket = QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)
                ->orderBy('number')->lockForUpdate()->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => QueueTicketStatus::Called,
                'barber_id' => $barber->id,
                'called_at' => now(),
                'call_expires_at' => now()->addSeconds(config('queue_shop.call_window_seconds')),
            ]);

            $this->notify($ticket, sprintf(
                "It's your turn! Queue #%d - please come to %s within %d seconds.",
                $ticket->number,
                $barber->name,
                config('queue_shop.call_window_seconds'),
            ), force: true);

            return $ticket;
        });
    }

    /**
     * Customer arrived at the chair. On-time arrival earns a loyalty point.
     */
    public function checkIn(QueueTicket $ticket): QueueTicket
    {
        if ($ticket->status !== QueueTicketStatus::Called) {
            return $ticket;
        }

        $onTime = $ticket->call_expires_at?->isFuture() ?? false;

        if (! $onTime) {
            return $this->burn($ticket);
        }

        DB::transaction(function () use ($ticket): void {
            $ticket->update(['status' => QueueTicketStatus::InChair, 'checked_in_at' => now()]);
            $ticket->customer()->increment('points');
        });

        return $ticket->refresh();
    }

    public function complete(QueueTicket $ticket, bool $redeemFreeCut = false): QueueTicket
    {
        DB::transaction(function () use ($ticket, $redeemFreeCut): void {
            $customer = $ticket->customer()->lockForUpdate()->first();
            $isFree = $redeemFreeCut && $customer->hasFreeCutAvailable();

            if ($isFree) {
                $customer->decrement('points', Customer::POINTS_FOR_FREE_CUT);
                $customer->increment('free_cuts_redeemed');
            }

            $ticket->update([
                'status' => QueueTicketStatus::Completed,
                'served_at' => now(),
                'is_free_cut' => $isFree,
            ]);
        });

        return $ticket->refresh();
    }

    public function cancel(QueueTicket $ticket): QueueTicket
    {
        $ticket->update(['status' => QueueTicketStatus::Cancelled]);

        return $ticket;
    }

    public function burn(QueueTicket $ticket): QueueTicket
    {
        $ticket->update(['status' => QueueTicketStatus::Burned]);
        $this->notify($ticket, sprintf('Queue #%d missed the call and was released. Please register again if you still want a cut.', $ticket->number), force: true);

        return $ticket;
    }

    /**
     * Burn every called ticket whose countdown has run out. Returns how many were burned.
     */
    public function expireOverdueCalls(): int
    {
        $overdue = QueueTicket::withStatus(QueueTicketStatus::Called)
            ->where('call_expires_at', '<=', now())->get();

        $overdue->each(fn (QueueTicket $ticket) => $this->burn($ticket));

        return $overdue->count();
    }

    /**
     * Send the "your turn is in about a minute" reminder to waiting customers who are due one.
     */
    public function sendDueReminders(): int
    {
        $sent = 0;
        $starts = $this->projectedStartTimes();
        $threshold = now()->addSeconds(config('queue_shop.reminder_lead_seconds'));

        QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)
            ->whereNull('reminded_at')->with('customer')->orderBy('number')->get()
            ->each(function (QueueTicket $ticket) use ($starts, $threshold, &$sent): void {
                if (isset($starts[$ticket->id]) && $starts[$ticket->id]->lte($threshold)) {
                    $this->notify($ticket, sprintf('Heads up! Queue #%d - your turn is in about 1 minute. Please head to the shop now.', $ticket->number));
                    $ticket->update(['reminded_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }

    public function estimatedWaitMinutes(QueueTicket $ticket): int
    {
        $start = $this->projectedStartTimes()[$ticket->id] ?? now();

        return (int) max(0, ceil(now()->diffInSeconds($start, false) / 60));
    }

    /**
     * Simulate barbers taking waiting tickets in order, skipping over their booked appointments.
     *
     * @return array<int, CarbonInterface> ticket id => projected start time
     */
    public function projectedStartTimes(): array
    {
        $minutes = $this->settings->minutesPerCut();
        $now = now();
        $freeAt = [];

        Barber::where('is_available', true)->get()->each(function (Barber $barber) use (&$freeAt, $now, $minutes): void {
            $busy = $barber->queueTickets()->today()
                ->withStatus(QueueTicketStatus::Called, QueueTicketStatus::InChair)->first();
            $freeAt[$barber->id] = $busy
                ? ($busy->checked_in_at ?? $busy->called_at)->copy()->addMinutes($minutes)->max($now)
                : $now->copy();
        });

        if ($freeAt === []) {
            $freeAt[0] = $now->copy();
        }

        $bookings = Booking::whereIn('status', [BookingStatus::Booked, BookingStatus::Arrived])
            ->where('ends_at', '>', $now)->whereDate('starts_at', today())
            ->orderBy('starts_at')->get()->groupBy('barber_id');

        $starts = [];
        QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)->orderBy('number')->get(['id'])
            ->each(function (QueueTicket $ticket) use (&$freeAt, &$starts, $bookings, $minutes): void {
                $best = null;
                foreach ($freeAt as $barberId => $time) {
                    $start = $this->skipBookings($time->copy(), $bookings->get($barberId, collect()), $minutes);
                    if ($best === null || $start->lt($best['start'])) {
                        $best = ['barber' => $barberId, 'start' => $start];
                    }
                }
                $starts[$ticket->id] = $best['start']->copy();
                $freeAt[$best['barber']] = $best['start']->copy()->addMinutes($minutes);
            });

        return $starts;
    }

    /**
     * Push a start time past any booking that would overlap a cut starting then.
     *
     * @param  Collection<int, Booking>  $bookings
     */
    private function skipBookings(CarbonInterface $start, Collection $bookings, int $minutes): CarbonInterface
    {
        foreach ($bookings as $booking) {
            if ($booking->starts_at->lt($start->copy()->addMinutes($minutes)) && $booking->ends_at->gt($start)) {
                $start = $booking->ends_at->copy();
            }
        }

        return $start;
    }

    private function notify(QueueTicket $ticket, string $message, bool $force = false): void
    {
        $customer = $ticket->customer;

        if ($customer->notifications_enabled || $force) {
            $this->whatsapp->send($customer->phone, $message);
        }
    }
}
