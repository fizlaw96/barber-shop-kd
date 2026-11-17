<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopClosure;
use App\Services\Whatsapp\WhatsappSender;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    public function __construct(private WhatsappSender $whatsapp, private ShopSettings $settings) {}

    /**
     * Why the shop cannot take bookings on a date, or null when it is open.
     */
    public function closedReason(CarbonInterface $date): ?string
    {
        $closure = ShopClosure::whereDate('closed_on', $date)->first();
        if ($closure) {
            return $closure->reason ?: 'Shop closed';
        }

        if (in_array($date->dayOfWeek, $this->settings->weeklyClosedDays(), true)) {
            return 'Closed on '.$date->format('l').'s';
        }

        return null;
    }

    /**
     * Free start times on a date. With no barber chosen, a slot is free when any barber is free.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function availableSlots(CarbonInterface $date, Service $service, ?Barber $barber = null): Collection
    {
        $config = config('queue_shop.booking');
        $day = CarbonImmutable::parse($date->toDateString());

        if ($this->closedReason($day) || $day->lt(today()) || $day->gt(today()->addDays($config['max_advance_days']))) {
            return collect();
        }

        $open = $day->setTimeFromTimeString($this->settings->openTime());
        $close = $day->setTimeFromTimeString($this->settings->closeTime());
        $barbers = $barber ? collect([$barber]) : Barber::all();
        $bookings = Booking::whereDate('starts_at', $day)->whereIn('status', [BookingStatus::Booked, BookingStatus::Arrived])->get();

        $slots = collect();
        for ($start = $open; $start->addMinutes($service->duration_minutes)->lte($close); $start = $start->addMinutes($config['slot_step_minutes'])) {
            if ($start->lte(now())) {
                continue;
            }
            $end = $start->addMinutes($service->duration_minutes);
            if ($this->freeBarbers($barbers, $bookings, $start, $end)->isNotEmpty()) {
                $slots->push($start);
            }
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Barber>  $barbers
     * @param  Collection<int, Booking>  $bookings
     * @return Collection<int, Barber>
     */
    private function freeBarbers(Collection $barbers, Collection $bookings, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $barbers->filter(fn (Barber $b) => ! $bookings->contains(
            fn (Booking $x) => $x->barber_id === $b->id && $x->starts_at->lt($end) && $x->ends_at->gt($start)
        ))->values();
    }

    /**
     * @throws InvalidArgumentException when the slot cannot be booked
     */
    public function book(string $phone, ?string $name, Service $service, CarbonInterface $startsAt, ?Barber $barber = null, ?string $notes = null, string $source = 'web'): Booking
    {
        $customer = Customer::firstOrCreate(['phone' => Customer::normalizePhone($phone)]);
        if ($name) {
            $customer->update(['name' => $name]);
        }

        $activeCount = $customer->bookings()->where('status', BookingStatus::Booked)->where('starts_at', '>', now())->count();
        if ($activeCount >= config('queue_shop.booking.max_active_per_customer')) {
            throw new InvalidArgumentException('You already have the maximum number of upcoming bookings.');
        }

        $booking = DB::transaction(function () use ($customer, $service, $startsAt, $barber, $notes, $source): Booking {
            $slot = CarbonImmutable::parse($startsAt);

            if ($reason = $this->closedReason($slot)) {
                throw new InvalidArgumentException("Sorry, the shop is closed that day ({$reason}).");
            }

            $available = $this->availableSlots($slot, $service, $barber);
            if (! $available->contains(fn (CarbonImmutable $s) => $s->equalTo($slot))) {
                throw new InvalidArgumentException('That time is no longer available. Please pick another.');
            }

            $end = $slot->addMinutes($service->duration_minutes);
            $bookings = Booking::whereDate('starts_at', $slot)->whereIn('status', [BookingStatus::Booked, BookingStatus::Arrived])->lockForUpdate()->get();
            $candidates = $this->freeBarbers($barber ? collect([$barber]) : Barber::all(), $bookings, $slot, $end);
            $assigned = $candidates->random();

            return Booking::create([
                'customer_id' => $customer->id,
                'barber_id' => $assigned->id,
                'service_id' => $service->id,
                'starts_at' => $slot,
                'ends_at' => $end,
                'status' => BookingStatus::Booked,
                'source' => $source,
                'notes' => $notes,
                'barber_was_random' => $barber === null,
            ]);
        });

        $booking->load(['customer', 'barber', 'service']);
        $this->whatsapp->send($customer->phone, sprintf(
            'Booking confirmed! %s with %s on %s at %s. Please arrive on time - we hold your slot for %d minutes.',
            $booking->service->name,
            $booking->barber->name,
            $booking->starts_at->format('D j M'),
            $booking->starts_at->format('g:i A'),
            config('queue_shop.booking.grace_minutes'),
        ));

        return $booking;
    }

    /**
     * Customer showed up: awards the service's points (capped at 3).
     */
    public function markArrived(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::Booked) {
            return $booking;
        }

        $points = min(Service::MAX_POINTS, $booking->service->points);

        DB::transaction(function () use ($booking, $points): void {
            $booking->update(['status' => BookingStatus::Arrived, 'points_awarded' => $points]);
            $booking->customer()->increment('points', $points);
        });

        return $booking->refresh();
    }

    public function complete(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::Arrived) {
            $booking->update(['status' => BookingStatus::Completed]);
        }

        return $booking;
    }

    public function cancel(Booking $booking, bool $notify = false, ?string $reason = null): Booking
    {
        if ($booking->status === BookingStatus::Booked) {
            $booking->update(['status' => BookingStatus::Cancelled]);
            if ($notify) {
                $this->whatsapp->send($booking->customer->phone, sprintf(
                    'Sorry, your booking on %s at %s was cancelled%s. Please book another time.',
                    $booking->starts_at->format('D j M'),
                    $booking->starts_at->format('g:i A'),
                    $reason ? " ({$reason})" : '',
                ));
            }
        }

        return $booking;
    }

    /**
     * Close the shop on a date and cancel + notify anyone already booked that day.
     *
     * @return int number of bookings cancelled
     */
    public function closeShopOn(CarbonInterface $date, ?string $reason = null): int
    {
        ShopClosure::updateOrCreate(['closed_on' => $date->toDateString()], ['reason' => $reason]);

        $affected = Booking::whereDate('starts_at', $date)->where('status', BookingStatus::Booked)->with('customer')->get();
        $affected->each(fn (Booking $b) => $this->cancel($b, notify: true, reason: $reason ?: 'shop closed'));

        return $affected->count();
    }

    /**
     * Release bookings whose customer is more than the grace period late.
     */
    public function releaseNoShows(): int
    {
        $late = Booking::where('status', BookingStatus::Booked)
            ->where('starts_at', '<=', now()->subMinutes(config('queue_shop.booking.grace_minutes')))->get();

        $late->each(fn (Booking $b) => $b->update(['status' => BookingStatus::NoShow]));

        return $late->count();
    }

    /**
     * Send the day-before and 2-hour reminders that are due.
     */
    public function sendDueReminders(): int
    {
        $sent = 0;

        Booking::where('status', BookingStatus::Booked)->where('starts_at', '>', now())
            ->with(['customer', 'barber', 'service'])->get()
            ->each(function (Booking $b) use (&$sent): void {
                $hoursAway = now()->diffInHours($b->starts_at, false);

                if (! $b->hour_reminder_sent_at && $hoursAway <= 2) {
                    $this->whatsapp->send($b->customer->phone, sprintf('Reminder: %s with %s today at %s. See you soon!', $b->service->name, $b->barber->name, $b->starts_at->format('g:i A')));
                    $b->update(['hour_reminder_sent_at' => now(), 'day_reminder_sent_at' => $b->day_reminder_sent_at ?? now()]);
                    $sent++;
                } elseif (! $b->day_reminder_sent_at && $hoursAway <= 24 && $hoursAway > 2) {
                    $this->whatsapp->send($b->customer->phone, sprintf('Reminder: %s with %s tomorrow at %s. Reply here if you need to change it.', $b->service->name, $b->barber->name, $b->starts_at->format('g:i A')));
                    $b->update(['day_reminder_sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }
}
