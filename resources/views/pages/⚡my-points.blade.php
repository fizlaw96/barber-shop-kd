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

<main class="mx-auto max-w-md space-y-6 p-6">
    <a href="{{ route('home') }}" wire:navigate class="text-sm text-neutral-400">&larr; Back</a>
    <h1 class="text-3xl font-bold">My points</h1>

    @if ($customer)
        <section class="space-y-2 rounded-2xl bg-neutral-900 p-6 text-center">
            <p class="text-neutral-400">{{ $customer->name ?? $customer->phone }}</p>
            <p class="text-7xl font-black text-amber-400">{{ $customer->points }}<span class="text-2xl text-neutral-500"> / {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}</span></p>
            @if ($customer->hasFreeCutAvailable())
                <p class="font-semibold text-green-400">You have a free cut waiting!</p>
            @else
                <p class="text-sm text-neutral-400">{{ \App\Models\Customer::POINTS_FOR_FREE_CUT - $customer->points }} more point(s) to a free cut</p>
            @endif
            <p class="text-sm text-neutral-500">Free cuts used: {{ $customer->free_cuts_redeemed }} &middot; Walk-in cuts: {{ $cutsDone }}</p>
        </section>
        @if ($upcoming->isNotEmpty())
            <section class="space-y-2">
                <h2 class="font-semibold">Upcoming bookings</h2>
                @foreach ($upcoming as $booking)
                    <div wire:key="u-{{ $booking->id }}" class="rounded-xl bg-neutral-900 p-3 text-sm">{{ $booking->starts_at->format('D j M, g:i A') }} &middot; {{ $booking->service->name }} with {{ $booking->barber->name }}</div>
                @endforeach
            </section>
        @endif
    @else
        <form wire:submit="lookup" class="space-y-4 rounded-2xl bg-neutral-900 p-6">
            <input wire:model="phone" type="tel" placeholder="Your WhatsApp number" class="w-full rounded-lg bg-neutral-800 p-3">
            @error('phone') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            @if ($awaitingCode)
                <input wire:model="code" inputmode="numeric" maxlength="6" placeholder="6-digit WhatsApp code" class="w-full rounded-lg bg-neutral-800 p-3 tracking-widest">
                @error('code') <p class="text-sm text-amber-300">{{ $message }}</p> @enderror
            @endif
            <button class="w-full rounded-xl bg-amber-400 p-3 font-bold text-black">{{ $awaitingCode ? 'Verify' : 'Check my points' }}</button>
        </form>
    @endif
</main>
