<?php

use App\Models\Barber;
use App\Models\Customer;
use App\Models\PhoneVerification;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PhoneVerifier;
use App\Services\QueueService;
use App\Services\ShopSettings;
use App\Services\Whatsapp\CloudApiWhatsappSender;
use App\Services\Whatsapp\WhatsappSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
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

function lastCode(array $sent): string
{
    preg_match('/code is (\d{6})/', end($sent)[1], $m);

    return $m[1];
}

it('requires a WhatsApp code before joining the queue', function () {
    config(['queue_shop.verify_phone' => true]);
    Barber::factory()->create();

    $page = Livewire::test('pages::walk-in')->set('phone', '0123456789')->call('join');
    $page->assertSet('awaitingCode', true)->assertSet('ticketId', null);
    expect(QueueTicket::count())->toBe(0);

    $page->set('code', '000000')->call('join')->assertHasErrors('code');

    $page->set('code', lastCode($this->sent))->call('join')->assertHasNoErrors();
    expect(QueueTicket::count())->toBe(1);
});

it('locks the code after too many wrong attempts', function () {
    config(['queue_shop.verify_phone' => true]);
    $verifier = app(PhoneVerifier::class);
    $verifier->sendCode('0123456789');
    $real = lastCode($this->sent);

    foreach (range(1, 5) as $i) {
        expect($verifier->verify('0123456789', '000000'))->toBeFalse();
    }

    expect($verifier->verify('0123456789', $real))->toBeFalse();
});

it('rejects expired codes and throttles resends', function () {
    config(['queue_shop.verify_phone' => true]);
    $verifier = app(PhoneVerifier::class);
    $verifier->sendCode('0123456789');

    expect($verifier->sendCode('0123456789'))->toBeFalse();

    $this->travel(11)->minutes();
    expect($verifier->verify('0123456789', lastCode($this->sent)))->toBeFalse();
});

it('skips verification for logged in staff', function () {
    config(['queue_shop.verify_phone' => true]);
    $this->actingAs(User::factory()->create());

    expect(app(PhoneVerifier::class)->isVerified('0123456789'))->toBeTrue();
});

it('shows points only after verification on my-points', function () {
    config(['queue_shop.verify_phone' => true]);
    Customer::factory()->create(['phone' => '60123456789', 'points' => 3]);

    $page = Livewire::test('pages::my-points')->set('phone', '0123456789')->call('lookup');
    $page->assertSet('customerId', null);

    $page->set('code', lastCode($this->sent))->call('lookup')->assertSee('3');
    expect($page->get('customerId'))->not->toBeNull();
});

it('treats local and international formats as the same customer', function () {
    expect(Customer::normalizePhone('012-345 6789'))->toBe('60123456789')
        ->and(Customer::normalizePhone('+60 12-345 6789'))->toBe('60123456789');
});

it('stores a code hash, never the code', function () {
    config(['queue_shop.verify_phone' => true]);
    app(PhoneVerifier::class)->sendCode('0123456789');

    expect(PhoneVerification::first()->code_hash)->not->toContain(lastCode($this->sent));
});

it('lets staff change opening hours which the booking engine uses', function () {
    $this->actingAs(User::factory()->create());
    Barber::factory()->create();
    $service = Service::factory()->create();

    Livewire::test('pages::manage')
        ->set('openTime', '12:00')->set('closeTime', '14:00')->set('minutesPerCut', 25)->set('weeklyClosedDays', [3])
        ->call('saveSettings')->assertHasNoErrors();

    $settings = app(ShopSettings::class);
    expect($settings->openTime())->toBe('12:00')->and($settings->minutesPerCut())->toBe(25);

    $wednesday = CarbonImmutable::parse('next wednesday');
    $thursday = $wednesday->addDay();
    expect(app(BookingService::class)->availableSlots($wednesday, $service))->toBeEmpty();

    $slots = app(BookingService::class)->availableSlots($thursday, $service)->map->format('H:i');
    expect($slots->first())->toBe('12:00')->and($slots->last())->toBe('13:30');
});

it('rejects a closing time before opening', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::manage')->set('openTime', '12:00')->set('closeTime', '10:00')->call('saveSettings')->assertHasErrors('closeTime');
});

it('adds and edits services and keeps points within 1 to 3', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::manage')
        ->set('serviceName', 'Kids cut')->set('servicePrice', '15')->set('serviceDuration', 30)->set('servicePoints', 5)
        ->call('saveService')->assertHasErrors('servicePoints')
        ->set('servicePoints', 1)->call('saveService')->assertHasNoErrors();

    $service = Service::firstWhere('name', 'Kids cut');
    expect($service->points)->toBe(1);

    Livewire::test('pages::manage')->call('editService', $service->id)->set('servicePrice', '18')->call('saveService');
    expect($service->refresh()->price)->toBe('18.00');

    Livewire::test('pages::manage')->call('toggleService', $service->id);
    expect($service->refresh()->is_active)->toBeFalse();
});

it('adds barbers and protects those with history', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test('pages::manage')->set('barberName', 'Zed')->call('addBarber');
    $zed = Barber::firstWhere('name', 'Zed');

    app(BookingService::class)->book('0111111111', 'A', Service::factory()->create(), now()->addDay()->setTime(11, 0), $zed);
    Livewire::test('pages::manage')->call('removeBarber', $zed->id);
    expect(Barber::find($zed->id))->not->toBeNull();

    $other = Barber::factory()->create();
    Livewire::test('pages::manage')->call('removeBarber', $other->id);
    expect(Barber::find($other->id))->toBeNull();
});

it('keeps the manage page behind login', function () {
    $this->get(route('manage'))->assertRedirect(route('login'));
});

it('pushes walk-in estimates past a barber booking', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 11:15'));
    $barber = Barber::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    app(BookingService::class)->book('0111111111', 'A', $service, CarbonImmutable::parse('2026-11-17 11:30'), $barber);

    $ticket = app(QueueService::class)->join('0122222222');

    expect(app(QueueService::class)->estimatedWaitMinutes($ticket))->toBe(75);
});

it('falls back to a template when WhatsApp rejects free-form text', function () {
    config(['queue_shop.whatsapp' => [
        'driver' => 'cloud', 'phone_number_id' => '123', 'token' => 't', 'api_version' => 'v21.0',
        'fallback_template' => 'shop_notification', 'template_language' => 'en',
    ]]);
    Http::fakeSequence()->push(['error' => ['code' => 131047]], 400)->push(['messages' => [['id' => 'x']]], 200);

    (new CloudApiWhatsappSender)->send('60123', 'hello');

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'template' && $request['template']['name'] === 'shop_notification');
});
