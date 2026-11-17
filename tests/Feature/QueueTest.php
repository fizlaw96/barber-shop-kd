<?php

use App\Enums\QueueTicketStatus;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueService;
use App\Services\ShopSettings;
use App\Services\Whatsapp\WhatsappSender;
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
    Barber::factory()->create();
});

it('issues sequential numbers and normalizes the phone', function () {
    $queue = app(QueueService::class);

    $first = $queue->join('+60 12-345 6789');
    $second = $queue->join('0198765432');

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($first->customer->phone)->toBe('60123456789')
        ->and($this->sent)->toHaveCount(2);
});

it('returns the existing ticket when the same phone joins twice', function () {
    $queue = app(QueueService::class);

    $a = $queue->join('0123456789');
    $b = $queue->join('0123456789');

    expect($b->id)->toBe($a->id)->and(QueueTicket::count())->toBe(1);
});

it('does not send the confirmation when notifications are off', function () {
    app(QueueService::class)->join('0123456789', notificationsEnabled: false);

    expect($this->sent)->toBeEmpty();
});

it('calls the next waiting ticket with a 30 second window', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111');
    $queue->join('0122222222');

    $ticket = $queue->callNext(Barber::first());

    expect($ticket->number)->toBe(1)
        ->and($ticket->status)->toBe(QueueTicketStatus::Called)
        ->and((int) now()->diffInSeconds($ticket->call_expires_at, false))->toBeBetween(28, 30);
});

it('awards a point when the customer arrives within the window', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111');
    $ticket = $queue->callNext(Barber::first());

    $queue->checkIn($ticket);

    expect($ticket->refresh()->status)->toBe(QueueTicketStatus::InChair)
        ->and($ticket->customer->points)->toBe(1);
});

it('burns a late arrival and gives no point', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111');
    $ticket = $queue->callNext(Barber::first());

    $this->travel(31)->seconds();
    $queue->checkIn($ticket);

    expect($ticket->refresh()->status)->toBe(QueueTicketStatus::Burned)
        ->and($ticket->customer->points)->toBe(0);
});

it('burns overdue calls and lets the queue move on', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111');
    $queue->join('0122222222');
    $queue->callNext(Barber::first());

    $this->travel(31)->seconds();

    expect($queue->expireOverdueCalls())->toBe(1);
    expect($queue->callNext(Barber::first())->number)->toBe(2);
});

it('redeems a free cut after five points', function () {
    $queue = app(QueueService::class);
    $ticket = $queue->join('0111111111');
    $ticket->customer->update(['points' => 5]);
    $queue->callNext(Barber::first());

    $queue->complete($ticket->refresh(), redeemFreeCut: true);

    expect($ticket->refresh()->is_free_cut)->toBeTrue()
        ->and($ticket->customer->points)->toBe(0)
        ->and($ticket->customer->free_cuts_redeemed)->toBe(1);
});

it('refuses a free cut without enough points', function () {
    $queue = app(QueueService::class);
    $ticket = $queue->join('0111111111');

    $queue->complete($ticket, redeemFreeCut: true);

    expect($ticket->refresh()->is_free_cut)->toBeFalse();
});

it('sends a reminder about a minute before the turn, once', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111');
    $queue->join('0122222222');
    $queue->callNext(Barber::first());
    $sentBefore = count($this->sent);

    expect($queue->sendDueReminders())->toBe(0);

    $this->travel(app(ShopSettings::class)->minutesPerCut() * 60 - 30)->seconds();
    Customer::query()->update([]);

    expect($queue->sendDueReminders())->toBe(1)
        ->and($queue->sendDueReminders())->toBe(0)
        ->and(count($this->sent))->toBe($sentBefore + 1);
});

it('does not remind customers who opted out', function () {
    $queue = app(QueueService::class);
    $queue->join('0111111111', notificationsEnabled: false);

    $queue->sendDueReminders();

    expect($this->sent)->toBeEmpty();
});

it('registers a walk-in through the page', function () {
    Livewire::test('pages::walk-in')
        ->set('phone', '0123456789')
        ->call('join')
        ->assertHasNoErrors()
        ->assertSee('001');
});

it('validates the phone on the walk-in page', function () {
    Livewire::test('pages::walk-in')->set('phone', 'abc')->call('join')->assertHasErrors('phone');
});

it('protects the dashboard and lets staff call the next customer', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    app(QueueService::class)->join('0123456789');

    $this->actingAs(User::factory()->create());
    $this->get(route('dashboard'))->assertOk();

    Livewire::test('pages::dashboard')->call('callNext', Barber::first()->id);

    expect(QueueTicket::first()->status)->toBe(QueueTicketStatus::Called);
});

it('shows the public board without login', function () {
    $this->get(route('board'))->assertOk();
});
