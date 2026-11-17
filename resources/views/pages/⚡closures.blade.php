<?php

use App\Models\ShopClosure;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Closed days')] class extends Component {
    public string $closedOn = '';

    public string $reason = '';

    public ?string $result = null;

    public function add(BookingService $bookings): void
    {
        $this->validate([
            'closedOn' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string|max:120',
        ]);

        $cancelled = $bookings->closeShopOn(CarbonImmutable::parse($this->closedOn), $this->reason ?: null);
        $this->result = $cancelled > 0 ? "{$cancelled} existing booking(s) were cancelled and the customers notified." : 'Closed day added.';
        $this->reset('closedOn', 'reason');
    }

    public function remove(int $id): void
    {
        ShopClosure::whereKey($id)->delete();
    }

    public function with(): array
    {
        return ['closures' => ShopClosure::whereDate('closed_on', '>=', today())->orderBy('closed_on')->get()];
    }
};
?>

<div>
    <x-staff-nav />
    <main class="mx-auto max-w-2xl space-y-6 p-6">
        <h1 class="text-3xl font-bold">Closed days</h1>
        <p class="text-sm text-neutral-400">Customers can't book these dates, and anyone already booked is cancelled and told on WhatsApp.</p>

        <form wire:submit="add" class="flex flex-wrap gap-3 rounded-2xl bg-neutral-900 p-4">
            <input type="date" wire:model="closedOn" class="rounded-lg bg-neutral-800 p-2">
            <input wire:model="reason" placeholder="Reason (e.g. Hari Raya)" class="min-w-0 flex-1 rounded-lg bg-neutral-800 p-2">
            <button class="rounded-lg bg-amber-400 px-4 py-2 font-bold text-black">Close shop</button>
            @error('closedOn') <p class="w-full text-sm text-red-400">{{ $message }}</p> @enderror
        </form>
        @if ($result) <p class="text-green-400">{{ $result }}</p> @endif

        @foreach ($closures as $closure)
            <div wire:key="c-{{ $closure->id }}" class="flex items-center justify-between rounded-xl bg-neutral-900 p-4">
                <span>{{ $closure->closed_on->format('D j M Y') }} &mdash; {{ $closure->reason ?: 'Closed' }}</span>
                <button wire:click="remove({{ $closure->id }})" class="text-sm text-red-400">Remove</button>
            </div>
        @endforeach
    </main>
</div>
