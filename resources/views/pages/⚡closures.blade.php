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
    <main class="mx-auto max-w-2xl space-y-6 px-5 py-8">
        <div>
            <p class="eyebrow mb-2">Holidays &amp; leave</p>
            <h1 class="display text-4xl">Closed days</h1>
            <p class="mt-2 text-sm text-muted">Customers can't book these dates. Anyone already booked is cancelled and told on WhatsApp.</p>
        </div>

        <form wire:submit="add" class="card space-y-4">
            <div class="grid gap-4 sm:grid-cols-[auto_1fr]">
                <div>
                    <label class="label">Date</label>
                    <input type="date" wire:model="closedOn" class="input">
                    @error('closedOn') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reason</label>
                    <input wire:model="reason" placeholder="e.g. Hari Raya" class="input">
                </div>
            </div>
            <button class="btn-primary">Close the shop on this day</button>
        </form>

        @if ($result) <p class="badge-ok text-sm">{{ $result }}</p> @endif

        <section class="space-y-2">
            <p class="label">Upcoming</p>
            @forelse ($closures as $closure)
                <div wire:key="c-{{ $closure->id }}" class="card-flat flex items-center justify-between">
                    <div>
                        <p class="font-semibold">{{ $closure->closed_on->format('l, j F Y') }}</p>
                        <p class="text-sm text-muted">{{ $closure->reason ?: 'Closed' }}</p>
                    </div>
                    <button wire:click="remove({{ $closure->id }})" class="btn-ghost btn-sm text-bad">Remove</button>
                </div>
            @empty
                <div class="card-flat py-8 text-center text-muted">No closed days set.</div>
            @endforelse
        </section>
    </main>
</div>
