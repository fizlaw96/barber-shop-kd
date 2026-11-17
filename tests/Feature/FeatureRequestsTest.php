<?php

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\ShopClosure;
use App\Models\User;
use App\Services\BookingService;
use App\Services\QueueService;
use App\Services\ShopSettings;
use App\Services\Whatsapp\WhatsappSender;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
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
});

// ---- Queue numbers

it('shows queue numbers with three digits', function () {
    expect(QueueTicket::formatNumber(1))->toBe('001')
        ->and(QueueTicket::formatNumber(42))->toBe('042')
        ->and(QueueTicket::formatNumber(1234))->toBe('1234');

    $ticket = app(QueueService::class)->join('0123456789');

    expect($ticket->display_number)->toBe('001')->and(end($this->sent)[1])->toContain('001');
});

it('restarts at 001 each day without duplicates within a day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 10:00'));
    $queue = app(QueueService::class);

    $numbers = collect(['0111111111', '0122222222', '0133333333'])->map(fn ($p) => $queue->join($p)->display_number);
    expect($numbers->all())->toBe(['001', '002', '003']);

    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00'));
    expect($queue->join('0111111111')->display_number)->toBe('001');

    expect(QueueTicket::selectRaw('queue_date, number, count(*) c')->groupBy('queue_date', 'number')->having('c', '>', 1)->count())->toBe(0);
});

// ---- Dashboard: add barber and call straight away

it('adds a barber from the dashboard popup and lets them call a customer immediately', function () {
    $this->actingAs(User::factory()->create());
    app(QueueService::class)->join('0123456789');

    Livewire::test('pages::dashboard')
        ->call('openAddBarber')->assertSet('showAddBarber', true)
        ->set('barberName', 'Hakim')->call('addBarber')
        ->assertHasNoErrors()->assertSet('showAddBarber', false)
        ->assertDispatched('toast')
        ->assertSee('Call next customer');

    $hakim = Barber::firstWhere('name', 'Hakim');
    expect($hakim->is_available)->toBeTrue();

    Livewire::test('pages::dashboard')->call('callNext', $hakim->id);
    expect(QueueTicket::first()->barber_id)->toBe($hakim->id);
});

it('requires a name in the add barber popup', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::dashboard')->call('openAddBarber')->call('addBarber')->assertHasErrors('barberName');
});

// ---- Board countdown

it('shows the 30 second countdown on the board once a customer is called', function () {
    Barber::factory()->create();
    $queue = app(QueueService::class);
    $queue->join('0123456789');

    Livewire::test('pages::board')->assertDontSee('Please come now');

    $queue->callNext(Barber::first());

    Livewire::test('pages::board')->assertSee('Please come now')->assertSee('left: 30', false)->assertSee('001');
});

// ---- Walk-in kiosk reset

it('returns the kiosk to the registration form', function () {
    Barber::factory()->create();

    Livewire::test('pages::walk-in')
        ->set('phone', '0123456789')->call('join')->assertSee('001')
        ->call('resetForm')->assertSet('ticketId', null)->assertSet('phone', '')->assertSee('Get my number');
});

// ---- 12-hour time

it('shows opening hours in 12-hour time', function () {
    $this->get(route('home'))->assertSee('10:00 AM')->assertSee('9:00 PM')->assertDontSee('21:00');
});

// ---- Booking wizard

it('walks through the booking steps and sends the customer home with a toast', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));
    Barber::factory()->create();
    Service::factory()->create();
    $tomorrow = CarbonImmutable::parse('2026-11-18');

    $page = Livewire::test('pages::book')->assertSet('step', 1)
        ->call('next')->assertSet('step', 2)
        ->set('date', $tomorrow->toDateString())
        ->call('next')->assertHasErrors('time')->assertSet('step', 2)
        ->set('time', '11:00')->call('next')->assertSet('step', 3)
        ->call('back')->assertSet('step', 2)
        ->call('next')->assertSet('step', 3)
        ->call('submit')->assertHasErrors(['name', 'phone']);

    $page->set('name', 'Ali')->set('phone', '0123456789')->call('submit')->assertRedirect(route('home'));

    expect(Booking::count())->toBe(1)->and(session('toast'))->toContain('Booking sent')->toContain('11:00 AM');
});

it('stops the wizard on a closed day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));
    Barber::factory()->create();
    Service::factory()->create();
    ShopClosure::factory()->create(['closed_on' => '2026-11-18', 'reason' => 'Hari Raya']);

    Livewire::test('pages::book')->set('step', 2)->set('date', '2026-11-18')->set('time', '11:00')
        ->call('next')->assertHasErrors('date')->assertSet('step', 2);
});

// ---- Closed days: weekly + specific

it('closes a weekday every week, cancelling and notifying existing bookings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00')); // Tuesday
    $this->actingAs(User::factory()->create());
    $barber = Barber::factory()->create();
    $service = Service::factory()->create();
    $wednesday = CarbonImmutable::parse('2026-11-18');
    $booking = app(BookingService::class)->book('0111111111', 'A', $service, $wednesday->setTime(11, 0), $barber);
    $this->sent = [];

    Livewire::test('pages::closures')->set('type', 'weekly')->set('weekdays', [3])->call('add')->assertHasNoErrors()->assertDispatched('toast');

    expect(app(ShopSettings::class)->weeklyClosedDays())->toBe([3])
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(end($this->sent)[1])->toContain('cancelled')
        ->and(app(BookingService::class)->availableSlots($wednesday->addWeek(), $service))->toBeEmpty()
        ->and(app(BookingService::class)->closedReason($wednesday))->toContain('Wednesday');

    Livewire::test('pages::closures')->call('removeWeekday', 3);
    expect(app(ShopSettings::class)->weeklyClosedDays())->toBe([]);
});

it('requires at least one weekday for a weekly closure', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::closures')->set('type', 'weekly')->call('add')->assertHasErrors('weekdays');
});

it('adds and removes a specific closed date', function () {
    $this->actingAs(User::factory()->create());

    $page = Livewire::test('pages::closures')->set('closedOn', now()->addDays(3)->toDateString())->set('reason', 'Hari Raya')->call('add')->assertHasNoErrors();
    expect(ShopClosure::count())->toBe(1);

    $page->assertSee('Hari Raya')->call('removeDate', ShopClosure::first()->id);
    expect(ShopClosure::count())->toBe(0);
});

// ---- Manage tabs

it('renders all manage tabs with services in a scrollable list', function () {
    $this->actingAs(User::factory()->create());
    Service::factory()->count(15)->create();

    Livewire::test('pages::manage')->assertSee('Opening hours')->assertSee('Services')->assertSee('Barbers')->assertSeeHtml('overflow-y-auto');
});

// ---- Free cut announced at registration

it('flags the ticket and tells the customer when they register with a free cut earned', function () {
    Barber::factory()->create();
    Customer::factory()->withFreeCut()->create(['phone' => '60123456789']);

    $ticket = app(QueueService::class)->join('0123456789');

    expect($ticket->is_free_cut)->toBeTrue()
        ->and(end($this->sent)[1])->toContain('FREE haircut');

    Livewire::test('pages::walk-in')->set('phone', '0123456789')->call('join')->assertSee('You earned a FREE haircut');
});

it('does not flag customers who have not reached the free cut', function () {
    $ticket = app(QueueService::class)->join('0123456789');

    expect($ticket->is_free_cut)->toBeFalse()
        ->and(end($this->sent)[1])->not->toContain('FREE');

    Livewire::test('pages::walk-in')->set('phone', '0198765432')->call('join')->assertDontSee('You earned a FREE haircut');
});

it('shows staff the free cut on the dashboard and redeems it in one tap', function () {
    $this->actingAs(User::factory()->create());
    $barber = Barber::factory()->create();
    $customer = Customer::factory()->withFreeCut()->create(['phone' => '60123456789']);
    $queue = app(QueueService::class);
    $ticket = $queue->join('0123456789');

    Livewire::test('pages::dashboard')->assertSee('FREE CUT')->assertSee('tell the barber');

    $queue->callNext($barber);
    $queue->checkIn($ticket->refresh());

    Livewire::test('pages::dashboard')->assertSeeHtml('Free cut &middot; done')->assertSee('Paid instead')
        ->call('complete', $ticket->id, true);

    expect($ticket->refresh()->is_free_cut)->toBeTrue()
        ->and($customer->refresh()->free_cuts_redeemed)->toBe(1)
        ->and($customer->points)->toBe(1); // 5 - 5 + 1 point for arriving on time
});

it('lets staff charge instead of redeeming', function () {
    $this->actingAs(User::factory()->create());
    $barber = Barber::factory()->create();
    $customer = Customer::factory()->withFreeCut()->create(['phone' => '60123456789']);
    $queue = app(QueueService::class);
    $ticket = $queue->join('0123456789');
    $queue->callNext($barber);

    Livewire::test('pages::dashboard')->call('complete', $ticket->id, false);

    expect($ticket->refresh()->is_free_cut)->toBeFalse()->and($customer->refresh()->points)->toBe(5);
});

// ---- Customers page

it('lists customers with points first, highest on top, without typing', function () {
    $this->actingAs(User::factory()->create());
    Customer::factory()->create(['name' => 'Zero', 'points' => 0]);
    Customer::factory()->create(['name' => 'Two', 'points' => 2]);
    Customer::factory()->create(['name' => 'Five', 'points' => 5]);
    Customer::factory()->create(['name' => 'Three', 'points' => 3]);

    $page = Livewire::test('pages::customers')->assertSet('filter', 'points')->assertDontSee('Zero');

    $page->assertSeeInOrder(['Five', 'Three', 'Two']);

    $page->set('filter', 'free')->assertSee('Five')->assertDontSee('Three')
        ->set('filter', 'all')->assertSee('Zero')
        ->set('filter', 'points')->set('search', 'zero')->assertSee('Zero');
});

// ---- Booking calendar

it('greys out closed days, weekly closures and past days on the booking calendar', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00')); // Tuesday
    Barber::factory()->create();
    Service::factory()->create();
    ShopClosure::factory()->create(['closed_on' => '2026-11-22', 'reason' => 'Hari Raya']); // Sunday
    app(ShopSettings::class)->setWeeklyClosedDays([1]); // Mondays

    Livewire::test('pages::book')->call('next')
        ->assertSeeHtml('data-date="2026-11-22" data-state="closed"')   // specific closure
        ->assertSeeHtml('data-date="2026-11-23" data-state="closed"')   // a Monday
        ->assertSeeHtml('data-date="2026-11-16" data-state="past"')     // yesterday
        ->assertSeeHtml('data-date="2026-11-18" data-state="open"')
        ->assertSee('Hari Raya');
});

it('refuses to select a closed day from the calendar and clears the time on a valid pick', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));
    Barber::factory()->create();
    Service::factory()->create();
    ShopClosure::factory()->create(['closed_on' => '2026-11-22', 'reason' => 'Hari Raya']);

    Livewire::test('pages::book')->call('next')
        ->set('date', '2026-11-18')->set('time', '11:00')
        ->call('selectDate', '2026-11-22')->assertSet('date', '2026-11-18')->assertHasErrors('date')
        ->call('selectDate', '2026-11-16')->assertSet('date', '2026-11-18')
        ->call('selectDate', '2026-11-19')->assertSet('date', '2026-11-19')->assertSet('time', '')->assertHasNoErrors();
});

it('limits calendar navigation to the booking window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));
    Barber::factory()->create();
    Service::factory()->create();

    Livewire::test('pages::book')->call('next')
        ->assertSet('month', '2026-11')
        ->call('previousMonth')->assertSet('month', '2026-11')
        ->call('nextMonth')->assertSet('month', '2026-12')
        ->call('nextMonth')->assertSet('month', '2027-01')
        ->call('nextMonth')->assertSet('month', '2027-01'); // 60-day window ends mid-January
});

it('groups the available times into morning, afternoon and evening', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));
    Barber::factory()->create();
    Service::factory()->create();

    Livewire::test('pages::book')->set('date', '2026-11-18')->call('next')
        ->assertSee('Morning')->assertSee('Afternoon')->assertSee('Evening')->assertSee('11:00 AM')->assertSee('7:30 PM');
});
