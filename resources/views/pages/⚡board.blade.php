<?php

use App\Enums\QueueTicketStatus;
use App\Models\Barber;
use App\Models\QueueTicket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Queue board')] class extends Component {
    public function with(): array
    {
        return [
            'barbers' => Barber::where('is_available', true)->with(['queueTickets' => fn ($q) => $q->today()
                ->withStatus(QueueTicketStatus::Called, QueueTicketStatus::InChair)])->get(),
            'waiting' => QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)->orderBy('number')->limit(12)->pluck('number'),
        ];
    }
};
?>

<main class="min-h-screen p-10" wire:poll.2s>
    <h1 class="mb-8 text-5xl font-black">Now serving</h1>
    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($barbers as $barber)
            @php($ticket = $barber->queueTickets->first())
            <div wire:key="barber-{{ $barber->id }}" class="rounded-3xl bg-neutral-900 p-8 text-center {{ $ticket?->status === QueueTicketStatus::Called ? 'ring-4 ring-green-400' : '' }}">
                <p class="text-2xl text-neutral-400">{{ $barber->name }}</p>
                <p class="my-2 text-9xl font-black text-amber-400">{{ $ticket ? '#'.$ticket->number : '—' }}</p>
                @if ($ticket?->status === QueueTicketStatus::Called)
                    <p class="text-2xl font-bold text-green-400">Please come now</p>
                @endif
            </div>
        @endforeach
    </div>
    <h2 class="mb-4 mt-12 text-3xl font-bold">Next in line</h2>
    <div class="flex flex-wrap gap-4">
        @forelse ($waiting as $number)
            <span wire:key="w-{{ $number }}" class="rounded-2xl bg-neutral-800 px-6 py-3 text-4xl font-bold">#{{ $number }}</span>
        @empty
            <p class="text-2xl text-neutral-500">No one waiting - walk right in!</p>
        @endforelse
    </div>
</main>
