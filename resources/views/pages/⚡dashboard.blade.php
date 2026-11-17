<?php

use App\Enums\QueueTicketStatus;
use App\Models\Barber;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Staff dashboard')] class extends Component {
    public function callNext(int $barberId, QueueService $queue): void
    {
        $queue->callNext(Barber::findOrFail($barberId));
    }

    public function checkIn(int $ticketId, QueueService $queue): void
    {
        $queue->checkIn(QueueTicket::findOrFail($ticketId));
    }

    public function complete(int $ticketId, bool $redeem, QueueService $queue): void
    {
        $queue->complete(QueueTicket::findOrFail($ticketId), $redeem);
    }

    public function burn(int $ticketId, QueueService $queue): void
    {
        $queue->burn(QueueTicket::findOrFail($ticketId));
    }

    public function cancel(int $ticketId, QueueService $queue): void
    {
        $queue->cancel(QueueTicket::findOrFail($ticketId));
    }

    public function toggleBarber(int $barberId): void
    {
        $barber = Barber::findOrFail($barberId);
        $barber->update(['is_available' => ! $barber->is_available]);
    }

    public function with(QueueService $queue): array
    {
        $queue->expireOverdueCalls();

        return [
            'barbers' => Barber::with(['queueTickets' => fn ($q) => $q->today()
                ->withStatus(QueueTicketStatus::Called, QueueTicketStatus::InChair)->with('customer')])->get(),
            'waiting' => QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)->with('customer')->orderBy('number')->get(),
            'servedToday' => QueueTicket::today()->withStatus(QueueTicketStatus::Completed)->count(),
            'burnedToday' => QueueTicket::today()->withStatus(QueueTicketStatus::Burned)->count(),
            'starts' => $queue->projectedStartTimes(),
        ];
    }
};
?>
<div wire:poll.3s>
    <x-staff-nav />
    <main class="mx-auto max-w-6xl space-y-8 px-5 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow mb-2">Live queue</p>
                <h1 class="display text-4xl">Today's chairs</h1>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="card-flat px-5"><p class="display text-3xl text-brass-400">{{ $waiting->count() }}</p><p class="text-xs uppercase tracking-wider text-muted">Waiting</p></div>
                <div class="card-flat px-5"><p class="display text-3xl text-ok">{{ $servedToday }}</p><p class="text-xs uppercase tracking-wider text-muted">Served</p></div>
                <div class="card-flat px-5"><p class="display text-3xl text-bad">{{ $burnedToday }}</p><p class="text-xs uppercase tracking-wider text-muted">Burned</p></div>
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            @foreach ($barbers as $barber)
                @php($ticket = $barber->queueTickets->first())
                @php($called = $ticket?->status === QueueTicketStatus::Called)
                <div wire:key="barber-{{ $barber->id }}" class="card space-y-4 {{ $called ? 'border-ok/60' : '' }} {{ $barber->is_available ? '' : 'opacity-60' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-full bg-brass-400/15 font-display text-lg font-bold text-brass-300">{{ mb_substr($barber->name, 0, 1) }}</span>
                            <h2 class="text-lg font-semibold">{{ $barber->name }}</h2>
                        </div>
                        <button wire:click="toggleBarber({{ $barber->id }})" class="{{ $barber->is_available ? 'badge-ok' : 'badge-mute' }} cursor-pointer">{{ $barber->is_available ? 'Available' : 'Off' }}</button>
                    </div>

                    @if ($ticket)
                        <div class="flex items-center gap-5">
                            <p class="display text-6xl leading-none text-brass-400">#{{ $ticket->number }}</p>
                            <div class="min-w-0 text-sm">
                                <p class="truncate font-semibold">{{ $ticket->customer->name ?? 'Walk-in' }}</p>
                                <p class="truncate text-muted">{{ $ticket->customer->phone }}</p>
                                <p class="mt-1 text-muted">{{ $ticket->customer->points }}/{{ \App\Models\Customer::POINTS_FOR_FREE_CUT }} pts
                                    @if ($called) &middot; <span class="text-ok">arrive by {{ $ticket->call_expires_at->format('H:i:s') }}</span> @endif</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($called)
                                <button wire:click="checkIn({{ $ticket->id }})" class="btn-ok flex-1">Arrived</button>
                                <button wire:click="burn({{ $ticket->id }})" class="btn-danger">No show</button>
                            @else
                                <button wire:click="complete({{ $ticket->id }}, false)" class="btn-primary flex-1">Paid &amp; done</button>
                                @if ($ticket->customer->hasFreeCutAvailable())
                                    <button wire:click="complete({{ $ticket->id }}, true)" class="btn-secondary border-brass-400/60 text-brass-300">Free cut</button>
                                @endif
                            @endif
                        </div>
                    @elseif ($barber->is_available)
                        <button wire:click="callNext({{ $barber->id }})" class="btn-primary btn-lg w-full">Call next customer</button>
                    @else
                        <p class="text-sm text-muted">Not taking customers.</p>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="space-y-3">
            <h2 class="display text-2xl">Waiting <span class="text-muted">({{ $waiting->count() }})</span></h2>
            @forelse ($waiting as $ticket)
                <div wire:key="t-{{ $ticket->id }}" class="card-flat flex items-center gap-4">
                    <span class="display w-16 text-3xl text-brass-400">#{{ $ticket->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $ticket->customer->name ?: 'Walk-in' }} <span class="font-normal text-muted">&middot; {{ $ticket->customer->phone }}</span></p>
                        @if ($ticket->customer->hasFreeCutAvailable()) <span class="badge-brass mt-1">Free cut ready</span> @endif
                    </div>
                    @isset($starts[$ticket->id])
                        <span class="badge-mute">~{{ max(0, (int) ceil(now()->diffInSeconds($starts[$ticket->id], false) / 60)) }} min</span>
                    @endisset
                    <button wire:click="cancel({{ $ticket->id }})" wire:confirm="Remove from queue?" class="btn-ghost btn-sm text-bad">Remove</button>
                </div>
            @empty
                <div class="card-flat py-10 text-center text-muted">Nobody waiting. Enjoy the quiet.</div>
            @endforelse
        </section>
    </main>
</div>
