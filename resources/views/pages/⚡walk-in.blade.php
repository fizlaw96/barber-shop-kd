<?php

use App\Livewire\Concerns\VerifiesPhone;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Join the queue')] class extends Component {
    use VerifiesPhone;

    #[Validate('required|string|min:8|max:20|regex:/^[0-9+\s\-]+$/')]
    public string $phone = '';

    #[Validate('nullable|string|max:60')]
    public string $name = '';

    public bool $notify = true;

    public ?int $ticketId = null;

    public function join(QueueService $queue): void
    {
        $this->validate();

        if (! $this->phoneIsVerified($this->phone)) {
            return;
        }

        $this->ticketId = $queue->join($this->phone, $this->name ?: null, $this->notify)->id;
    }

    public function resetForm(): void
    {
        $this->reset();
    }

    public function with(): array
    {
        $ticket = $this->ticketId ? QueueTicket::with('customer')->find($this->ticketId) : null;

        return [
            'ticket' => $ticket,
            'wait' => $ticket ? app(QueueService::class)->estimatedWaitMinutes($ticket) : null,
        ];
    }
};
?>

<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 p-6" wire:poll.5s>
    <h1 class="text-3xl font-bold">Walk-in queue</h1>

    @if ($ticket)
        <section class="rounded-2xl bg-neutral-900 p-8 text-center">
            <p class="text-neutral-400">Your number</p>
            <p class="my-2 text-8xl font-black text-amber-400">#{{ $ticket->number }}</p>
            <p class="text-lg">Status: <strong>{{ str_replace('_', ' ', $ticket->status->value) }}</strong></p>
            @if ($ticket->status === \App\Enums\QueueTicketStatus::Waiting)
                <p class="mt-2 text-neutral-300">Estimated wait: about {{ $wait }} min</p>
            @elseif ($ticket->status === \App\Enums\QueueTicketStatus::Called)
                <p class="mt-2 text-xl font-semibold text-green-400">Your turn! Go to {{ $ticket->barber?->name }} now.</p>
            @endif
            <p class="mt-4 text-sm text-neutral-400">
                Points: {{ $ticket->customer->points }} / {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}
                @if ($ticket->customer->hasFreeCutAvailable()) &middot; free cut ready! @endif
            </p>
            <button wire:click="resetForm" class="mt-6 text-sm text-neutral-500 underline">New registration</button>
        </section>
    @else
        <form wire:submit="join" class="space-y-4 rounded-2xl bg-neutral-900 p-6">
            <div>
                <label class="mb-1 block text-sm text-neutral-400" for="phone">Phone / WhatsApp number</label>
                <input id="phone" wire:model="phone" type="tel" inputmode="tel" placeholder="60123456789"
                    class="w-full rounded-lg bg-neutral-800 p-3 text-lg">
                @error('phone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm text-neutral-400" for="name">Name (optional)</label>
                <input id="name" wire:model="name" type="text" class="w-full rounded-lg bg-neutral-800 p-3 text-lg">
            </div>
            @if ($awaitingCode)
                <div>
                    <input wire:model="code" inputmode="numeric" maxlength="6" placeholder="6-digit WhatsApp code" class="w-full rounded-lg bg-neutral-800 p-3 text-lg tracking-widest">
                    @error('code') <p class="mt-1 text-sm text-amber-300">{{ $message }}</p> @enderror
                </div>
            @endif
            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" wire:model="notify" class="size-5"> Notify me on WhatsApp before my turn
            </label>
            <button class="w-full rounded-xl bg-amber-400 p-4 text-lg font-bold text-black">{{ $awaitingCode ? 'Verify and get my number' : 'Get my number' }}</button>
            <p class="text-xs text-neutral-500">When called you have 30 seconds to arrive, or your number is released. Arrive on time to earn points: 5 points = a free cut.</p>
        </form>
    @endif
</main>
