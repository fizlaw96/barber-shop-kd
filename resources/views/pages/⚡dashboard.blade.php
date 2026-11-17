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
<main class="mx-auto max-w-5xl space-y-8 p-6">
    <header class="flex items-center justify-between">
        <h1 class="text-3xl font-bold">Queue</h1>
        <p class="text-neutral-400">Served {{ $servedToday }} &middot; Burned {{ $burnedToday }}</p>
    </header>

    <section class="grid gap-4 md:grid-cols-2">
        @foreach ($barbers as $barber)
            @php($ticket = $barber->queueTickets->first())
            <div wire:key="barber-{{ $barber->id }}" class="space-y-3 rounded-2xl bg-neutral-900 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold">{{ $barber->name }}</h2>
                    <button wire:click="toggleBarber({{ $barber->id }})" class="rounded-full px-3 py-1 text-xs {{ $barber->is_available ? 'bg-green-500/20 text-green-300' : 'bg-neutral-700 text-neutral-400' }}">
                        {{ $barber->is_available ? 'Available' : 'Off' }}
                    </button>
                </div>
                @if ($ticket)
                    <p class="text-4xl font-black text-amber-400">#{{ $ticket->number }}
                        <span class="text-base font-normal text-neutral-300">{{ $ticket->customer->name ?? $ticket->customer->phone }}</span></p>
                    <p class="text-sm text-neutral-400">Points {{ $ticket->customer->points }}/{{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}
                        @if ($ticket->status === QueueTicketStatus::Called) &middot; arrive by {{ $ticket->call_expires_at->format('H:i:s') }} @endif</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($ticket->status === QueueTicketStatus::Called)
                            <button wire:click="checkIn({{ $ticket->id }})" class="rounded-lg bg-green-500 px-4 py-2 font-semibold text-black">Arrived</button>
                            <button wire:click="burn({{ $ticket->id }})" class="rounded-lg bg-red-500/80 px-4 py-2">No show</button>
                        @else
                            <button wire:click="complete({{ $ticket->id }}, false)" class="rounded-lg bg-amber-400 px-4 py-2 font-semibold text-black">Paid</button>
                            @if ($ticket->customer->hasFreeCutAvailable())
                                <button wire:click="complete({{ $ticket->id }}, true)" class="rounded-lg bg-purple-500 px-4 py-2 font-semibold">Free cut</button>
                            @endif
                        @endif
                    </div>
                @elseif ($barber->is_available)
                    <button wire:click="callNext({{ $barber->id }})" class="w-full rounded-xl bg-amber-400 p-3 font-bold text-black">Call next</button>
                @endif
            </div>
        @endforeach
    </section>

    <section class="space-y-2">
        <h2 class="text-xl font-semibold">Waiting ({{ $waiting->count() }})</h2>
        @forelse ($waiting as $ticket)
            <div wire:key="t-{{ $ticket->id }}" class="flex items-center justify-between rounded-xl bg-neutral-900 p-4">
                <div>
                    <span class="text-2xl font-bold">#{{ $ticket->number }}</span>
                    <span class="ml-2">{{ $ticket->customer->name ?? '' }} {{ $ticket->customer->phone }}</span>
                    @if ($ticket->customer->hasFreeCutAvailable()) <span class="ml-2 rounded bg-purple-500/30 px-2 text-xs">free cut ready</span> @endif
                </div>
                <div class="flex items-center gap-4 text-sm text-neutral-400">
                    @isset($starts[$ticket->id]) ~{{ max(0, (int) ceil(now()->diffInSeconds($starts[$ticket->id], false) / 60)) }} min @endisset
                    <button wire:click="cancel({{ $ticket->id }})" wire:confirm="Remove from queue?" class="text-red-400">Remove</button>
                </div>
            </div>
        @empty
            <p class="text-neutral-500">Nobody waiting.</p>
        @endforelse
    </section>
</main>
</div>
