<?php

use App\Enums\BookingStatus;
use App\Livewire\Concerns\VerifiesPhone;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('My points')] class extends Component {
    use VerifiesPhone;

    public string $phone = '';

    public ?int $customerId = null;

    public function lookup(): void
    {
        $this->validate(['phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+\s\-]+$/']]);

        if (! $this->phoneIsVerified($this->phone)) {
            return;
        }

        $customer = Customer::where('phone', Customer::normalizePhone($this->phone))->first();

        if (! $customer) {
            $this->addError('phone', 'No visits found for this number yet.');

            return;
        }

        $this->customerId = $customer->id;
    }

    public function with(): array
    {
        $customer = $this->customerId ? Customer::find($this->customerId) : null;

        return [
            'customer' => $customer,
            'upcoming' => $customer?->bookings()->with(['service', 'barber'])->where('status', BookingStatus::Booked)->where('starts_at', '>', now())->orderBy('starts_at')->get() ?? collect(),
            'cutsDone' => $customer?->queueTickets()->where('status', 'completed')->count() ?? 0,
        ];
    }
};
?>
<div>
    <x-site-header />
    <main class="mx-auto max-w-md space-y-6 px-5 py-12">
        <div>
            <p class="eyebrow mb-3">Loyalty</p>
            <h1 class="display text-4xl">My points</h1>
        </div>

        @if ($customer)
            <section class="card space-y-4 p-8 text-center">
                <p class="text-muted">{{ $customer->name ?? $customer->phone }}</p>
                <p class="display text-8xl leading-none text-brass-400">{{ $customer->points }}<span class="text-3xl text-muted"> / {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}</span></p>
                <div class="flex justify-center gap-2">
                    @foreach (range(1, \App\Models\Customer::POINTS_FOR_FREE_CUT) as $stamp)
                        <span wire:key="st-{{ $stamp }}" class="size-4 rounded-full {{ $stamp <= $customer->points ? 'bg-brass-400' : 'bg-ink-600' }}"></span>
                    @endforeach
                </div>
                @if ($customer->hasFreeCutAvailable())
                    <span class="badge-ok text-sm">You have a free cut waiting!</span>
                @else
                    <p class="text-sm text-muted">{{ \App\Models\Customer::POINTS_FOR_FREE_CUT - $customer->points }} more point(s) to a free cut</p>
                @endif
                <p class="border-t border-ink-700 pt-4 text-sm text-muted">Free cuts used: {{ $customer->free_cuts_redeemed }} &middot; Walk-in cuts: {{ $cutsDone }}</p>
            </section>
            @if ($upcoming->isNotEmpty())
                <section class="space-y-2">
                    <p class="label">Upcoming bookings</p>
                    @foreach ($upcoming as $booking)
                        <div wire:key="u-{{ $booking->id }}" class="card-flat text-sm"><strong>{{ $booking->starts_at->format('D j M, g:i A') }}</strong> &middot; {{ $booking->service->name }} with {{ $booking->barber->name }}</div>
                    @endforeach
                </section>
            @endif
        @else
            <form wire:submit="lookup" class="card space-y-4">
                <div>
                    <label class="label">WhatsApp number</label>
                    <input wire:model="phone" type="tel" placeholder="012-345 6789" class="input">
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>
                @if ($awaitingCode)
                    <div>
                        <label class="label">Verification code</label>
                        <input wire:model="code" inputmode="numeric" maxlength="6" class="input text-center text-xl tracking-[0.5em]" placeholder="------">
                        @error('code') <p class="hint">{{ $message }}</p> @enderror
                    </div>
                @endif
                <button class="btn-primary w-full">{{ $awaitingCode ? 'Verify' : 'Check my points' }}</button>
            </form>
        @endif
    </main>
    <x-site-footer />
</div>
