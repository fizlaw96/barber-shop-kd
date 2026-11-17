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
    <main class="mx-auto max-w-4xl space-y-6 px-5 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow mb-2">Schedule</p>
                <h1 class="display text-4xl">Bookings</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <input type="date" wire:model.live="date" class="input w-auto py-2">
                <select wire:model.live="barberId" class="input w-auto py-2">
                    <option value="">All barbers</option>
                    @foreach ($barbers as $barber)
                        <option wire:key="f-{{ $barber->id }}" value="{{ $barber->id }}">{{ $barber->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @forelse ($bookings as $booking)
            @php($tone = match ($booking->status) { BookingStatus::Booked => 'badge-brass', BookingStatus::Arrived, BookingStatus::Completed => 'badge-ok', BookingStatus::NoShow => 'badge-bad', default => 'badge-mute' })
            <div wire:key="bk-{{ $booking->id }}" class="card space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4">
                        <p class="display text-3xl text-brass-400">{{ $booking->starts_at->format('g:i A') }}</p>
                        <div>
                            <p class="font-semibold">{{ $booking->customer->name }}</p>
                            <p class="text-sm text-muted">{{ $booking->customer->phone }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span class="{{ $tone }}">{{ str_replace('_', ' ', $booking->status->value) }}</span>
                        <span class="badge-mute">{{ $booking->source }}</span>
                    </div>
                </div>
                <p class="text-sm"><strong>{{ $booking->service->name }}</strong> <span class="text-muted">with</span> {{ $booking->barber->name }}@if ($booking->barber_was_random) <span class="text-muted">(auto-assigned)</span>@endif</p>
                @if ($booking->notes)
                    <p class="rounded-xl border border-brass-500/30 bg-brass-500/10 p-3 text-sm text-brass-300"><span class="font-semibold">Note:</span> {{ $booking->notes }}</p>
                @endif
                @if ($booking->status === BookingStatus::Booked)
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="arrived({{ $booking->id }})" class="btn-ok btn-sm">Arrived (+{{ $booking->service->points }} pt)</button>
                        <button wire:click="noShow({{ $booking->id }})" class="btn-danger btn-sm">No show</button>
                        <button wire:click="cancel({{ $booking->id }})" wire:confirm="Cancel and notify the customer?" class="btn-ghost btn-sm">Cancel</button>
                    </div>
                @elseif ($booking->status === BookingStatus::Arrived)
                    <button wire:click="complete({{ $booking->id }})" class="btn-primary btn-sm">Mark done</button>
                @endif
            </div>
        @empty
            <div class="card py-14 text-center text-muted">No bookings for this day.</div>
        @endforelse
    </main>
</div>
