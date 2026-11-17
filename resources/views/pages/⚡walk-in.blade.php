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
<main class="mx-auto flex min-h-screen max-w-md flex-col px-5 py-8" wire:poll.5s>
    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-full border-2 border-brass-400 font-display font-bold text-brass-400">KD</span>
        <span class="eyebrow">Walk-in queue</span>
    </a>

    <div class="flex flex-1 flex-col justify-center gap-6 py-8">
        @if ($ticket)
            @php($isCalled = $ticket->status === \App\Enums\QueueTicketStatus::Called)
            <section class="card space-y-3 p-8 text-center {{ $isCalled ? 'calling border-ok' : '' }}">
                <p class="eyebrow">Your number</p>
                <p class="display text-9xl leading-none text-brass-400">#{{ $ticket->number }}</p>
                <div>
                    @if ($isCalled)
                        <span class="badge-ok text-base">It's your turn</span>
                    @elseif ($ticket->status === \App\Enums\QueueTicketStatus::Waiting)
                        <span class="badge-brass">Waiting</span>
                    @else
                        <span class="badge-mute">{{ str_replace('_', ' ', $ticket->status->value) }}</span>
                    @endif
                </div>
                @if ($ticket->status === \App\Enums\QueueTicketStatus::Waiting)
                    <p class="text-muted">Estimated wait <strong class="text-cream">~{{ $wait }} min</strong></p>
                @elseif ($isCalled)
                    <p class="text-xl font-semibold text-ok">Head to {{ $ticket->barber?->name }} now</p>
                    <p class="text-sm text-muted">Arrive before {{ $ticket->call_expires_at->format('g:i:s A') }} or your number is released.</p>
                @endif
            </section>

            <section class="card-flat flex items-center justify-between">
                <div>
                    <p class="label mb-0">Loyalty points</p>
                    <p class="text-sm text-muted">{{ $ticket->customer->hasFreeCutAvailable() ? 'Free cut ready!' : (\App\Models\Customer::POINTS_FOR_FREE_CUT - $ticket->customer->points).' more for a free cut' }}</p>
                </div>
                <p class="display text-3xl text-brass-400">{{ $ticket->customer->points }}<span class="text-lg text-muted">/{{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}</span></p>
            </section>

            <button wire:click="resetForm" class="btn-ghost mx-auto">Register another number</button>
        @else
            <div class="space-y-2">
                <h1 class="display text-4xl">Get in line</h1>
                <p class="text-muted">Enter your number and we'll WhatsApp you before it's your turn.</p>
            </div>

            <form wire:submit="join" class="card space-y-5">
                <div>
                    <label class="label" for="phone">Phone / WhatsApp</label>
                    <input id="phone" wire:model="phone" type="tel" inputmode="tel" placeholder="012-345 6789" class="input py-4 text-xl">
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="name">Name (optional)</label>
                    <input id="name" wire:model="name" type="text" class="input">
                </div>
                @if ($awaitingCode)
                    <div>
                        <label class="label">Verification code</label>
                        <input wire:model="code" inputmode="numeric" maxlength="6" class="input py-4 text-center text-2xl tracking-[0.5em]" placeholder="------">
                        @error('code') <p class="hint">{{ $message }}</p> @enderror
                    </div>
                @endif
                <label class="flex cursor-pointer items-center gap-3 text-sm text-muted">
                    <input type="checkbox" wire:model="notify" class="size-5 accent-brass-400"> Remind me on WhatsApp before my turn
                </label>
                <button class="btn-primary btn-lg w-full">{{ $awaitingCode ? 'Verify & get my number' : 'Get my number' }}</button>
            </form>
            <p class="text-center text-xs text-muted">When we call your number you have 30 seconds to arrive. Arrive on time to earn points &mdash; {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }} points = a free cut.</p>
        @endif
    </div>
</main>
