<?php

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Bookings')] class extends Component {
    public string $date = '';

    public ?int $barberId = null;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function arrived(int $id, BookingService $bookings): void
    {
        $bookings->markArrived(Booking::findOrFail($id));
    }

    public function complete(int $id, BookingService $bookings): void
    {
        $bookings->complete(Booking::findOrFail($id));
    }

    public function noShow(int $id): void
    {
        Booking::where('status', BookingStatus::Booked)->whereKey($id)->update(['status' => BookingStatus::NoShow]);
    }

    public function cancel(int $id, BookingService $bookings): void
    {
        $bookings->cancel(Booking::with('customer')->findOrFail($id), notify: true);
    }

    public function with(): array
    {
        return [
            'barbers' => Barber::orderBy('name')->get(),
            'bookings' => Booking::with(['customer', 'barber', 'service'])
                ->whereDate('starts_at', $this->date ?: today())
                ->when($this->barberId, fn ($q) => $q->where('barber_id', $this->barberId))
                ->orderBy('starts_at')->get(),
        ];
    }
};
?>

<div wire:poll.10s>
    <x-staff-nav />
    <main class="mx-auto max-w-5xl space-y-4 p-6">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="mr-auto text-3xl font-bold">Bookings</h1>
            <input type="date" wire:model.live="date" class="rounded-lg bg-neutral-800 p-2">
            <select wire:model.live="barberId" class="rounded-lg bg-neutral-800 p-2">
                <option value="">All barbers</option>
                @foreach ($barbers as $barber)
                    <option wire:key="f-{{ $barber->id }}" value="{{ $barber->id }}">{{ $barber->name }}</option>
                @endforeach
            </select>
        </div>

        @forelse ($bookings as $booking)
            <div wire:key="bk-{{ $booking->id }}" class="space-y-2 rounded-xl bg-neutral-900 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="text-xl font-bold text-amber-400">{{ $booking->starts_at->format('g:i A') }}</span>
                        <span class="ml-2 font-semibold">{{ $booking->customer->name }}</span>
                        <span class="ml-1 text-sm text-neutral-400">{{ $booking->customer->phone }}</span>
                    </div>
                    <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs">{{ str_replace('_', ' ', $booking->status->value) }} &middot; {{ $booking->source }}</span>
                </div>
                <p class="text-sm">{{ $booking->service->name }} &middot; {{ $booking->barber->name }}{{ $booking->barber_was_random ? ' (auto-assigned)' : '' }}</p>
                @if ($booking->notes)
                    <p class="rounded-lg bg-amber-400/10 p-2 text-sm text-amber-200">Note: {{ $booking->notes }}</p>
                @endif
                <div class="flex flex-wrap gap-2 text-sm">
                    @if ($booking->status === BookingStatus::Booked)
                        <button wire:click="arrived({{ $booking->id }})" class="rounded-lg bg-green-500 px-3 py-1 font-semibold text-black">Arrived (+{{ $booking->service->points }} pt)</button>
                        <button wire:click="noShow({{ $booking->id }})" class="rounded-lg bg-red-500/80 px-3 py-1">No show</button>
                        <button wire:click="cancel({{ $booking->id }})" wire:confirm="Cancel and notify the customer?" class="rounded-lg bg-neutral-700 px-3 py-1">Cancel</button>
                    @elseif ($booking->status === BookingStatus::Arrived)
                        <button wire:click="complete({{ $booking->id }})" class="rounded-lg bg-amber-400 px-3 py-1 font-semibold text-black">Done</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-neutral-500">No bookings for this day.</p>
        @endforelse
    </main>
</div>
