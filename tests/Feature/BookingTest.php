<?php

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ShopClosure;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Whatsapp\WhatsappSender;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00')); // a Tuesday
    $this->sent = [];
    $sent = &$this->sent;
    app()->instance(WhatsappSender::class, new class($sent) implements WhatsappSender
    {
        public function __construct(public array &$sent) {}

        public function send(string $phone, string $message): void
        {
            $this->sent[] = [$phone, $message];
        }
    });
    $this->barber = Barber::factory()->create();
    $this->service = Service::factory()->create(['duration_minutes' => 30, 'points' => 2]);
    $this->tomorrow = CarbonImmutable::parse('2026-11-18');
});

it('books a slot and confirms on WhatsApp', function () {
    $booking = app(BookingService::class)->book('0123456789', 'Ali', $this->service, $this->tomorrow->setTime(11, 0), notes: 'Low fade');

    expect($booking->status)->toBe(BookingStatus::Booked)
        ->and($booking->barber_id)->toBe($this->barber->id)
        ->and($booking->barber_was_random)->toBeTrue()
        ->and($booking->notes)->toBe('Low fade')
        ->and($this->sent)->toHaveCount(1);
});

it('refuses to book a closed day', function () {
    ShopClosure::factory()->create(['closed_on' => $this->tomorrow, 'reason' => 'Hari Raya']);

    app(BookingService::class)->book('0123456789', 'Ali', $this->service, $this->tomorrow->setTime(11, 0));
})->throws(InvalidArgumentException::class, 'closed');

it('refuses weekly closed days', function () {
    config(['queue_shop.booking.weekly_closed_days' => [$this->tomorrow->dayOfWeek]]);

    expect(app(BookingService::class)->availableSlots($this->tomorrow, $this->service))->toBeEmpty();
});

it('prevents double booking the same barber', function () {
    $svc = app(BookingService::class);
    $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0), $this->barber);

    $svc->book('0122222222', 'B', $this->service, $this->tomorrow->setTime(11, 0), $this->barber);
})->throws(InvalidArgumentException::class, 'no longer available');

it('assigns a free barber when the first is taken', function () {
    $other = Barber::factory()->create();
    $svc = app(BookingService::class);
    $first = $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0), $this->barber);
    $second = $svc->book('0122222222', 'B', $this->service, $this->tomorrow->setTime(11, 0));

    expect($second->barber_id)->toBe($other->id)->and($first->barber_id)->toBe($this->barber->id);
});

it('hides slots that overlap and past times', function () {
    app(BookingService::class)->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0), $this->barber);

    $slots = app(BookingService::class)->availableSlots($this->tomorrow, $this->service, $this->barber)->map->format('H:i');
    $today = app(BookingService::class)->availableSlots(today(), $this->service)->map->format('H:i');

    expect($slots)->not->toContain('11:00')->and($slots)->toContain('11:30')->and($slots)->toContain('10:30')
        ->and($today)->not->toContain('09:30')->and($today)->toContain('10:00');
});

it('limits active bookings per customer', function () {
    $svc = app(BookingService::class);
    foreach ([10, 12, 14] as $hour) {
        $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime($hour, 0));
    }

    $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(16, 0));
})->throws(InvalidArgumentException::class, 'maximum');

it('awards the service points on arrival, capped at 3', function () {
    $this->service->update(['points' => 9]);
    $svc = app(BookingService::class);
    $booking = $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0));

    $svc->markArrived($booking);
    $svc->markArrived($booking);

    expect($booking->customer->refresh()->points)->toBe(3)->and($booking->refresh()->status)->toBe(BookingStatus::Arrived);
});

it('releases bookings 30 minutes after the start time', function () {
    $svc = app(BookingService::class);
    $booking = $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0));

    $this->travelTo($this->tomorrow->setTime(11, 29));
    expect($svc->releaseNoShows())->toBe(0);

    $this->travelTo($this->tomorrow->setTime(11, 31));
    expect($svc->releaseNoShows())->toBe(1)->and($booking->refresh()->status)->toBe(BookingStatus::NoShow);
    expect($booking->customer->points)->toBe(0);
});

it('cancels and notifies existing bookings when the shop closes that day', function () {
    $svc = app(BookingService::class);
    $booking = $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0));

    $cancelled = $svc->closeShopOn($this->tomorrow, 'Public holiday');

    expect($cancelled)->toBe(1)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(end($this->sent)[1])->toContain('cancelled')
        ->and(ShopClosure::count())->toBe(1);
});

it('sends day-before and 2-hour reminders once each', function () {
    $svc = app(BookingService::class);
    $svc->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0));
    $this->sent = [];

    $this->travelTo($this->tomorrow->setTime(9, 30)); // within 2h
    expect($svc->sendDueReminders())->toBe(1)->and($svc->sendDueReminders())->toBe(0);

    $svc->book('0122222222', 'B', $this->service, $this->tomorrow->setTime(20, 0));
    $this->travelTo($this->tomorrow->setTime(0, 30));
    expect($svc->sendDueReminders())->toBe(1)->and($svc->sendDueReminders())->toBe(0);
});

it('books through the public page', function () {
    Livewire::test('pages::book')
        ->set('date', $this->tomorrow->toDateString())
        ->set('time', '11:00')
        ->set('name', 'Ali')
        ->set('phone', '0123456789')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('booked');

    expect(Booking::count())->toBe(1)->and(Customer::first()->name)->toBe('Ali');
});

it('shows a closed message on the booking page', function () {
    ShopClosure::factory()->create(['closed_on' => $this->tomorrow, 'reason' => 'Hari Raya']);

    Livewire::test('pages::book')->set('date', $this->tomorrow->toDateString())->assertSee('closed on this day');
});

it('lets staff manage bookings and closed days but not guests', function () {
    $this->get(route('bookings'))->assertRedirect(route('login'));
    $this->get(route('closures'))->assertRedirect(route('login'));

    $booking = app(BookingService::class)->book('0111111111', 'A', $this->service, $this->tomorrow->setTime(11, 0));
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::bookings')->set('date', $this->tomorrow->toDateString())->assertSee('11:00 AM')->call('arrived', $booking->id);
    expect($booking->refresh()->status)->toBe(BookingStatus::Arrived);

    Livewire::test('pages::closures')->set('closedOn', $this->tomorrow->addDay()->toDateString())->call('add')->assertHasNoErrors();
    expect(ShopClosure::count())->toBe(1);
});

it('renders the public website', function () {
    $this->get(route('home'))->assertOk()->assertSee($this->service->name);
});
