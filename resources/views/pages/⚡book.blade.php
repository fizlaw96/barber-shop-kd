<?php

use App\Livewire\Concerns\VerifiesPhone;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Book online')] class extends Component {
    use VerifiesPhone;

    public ?int $serviceId = null;

    public ?int $barberId = null;

    public string $date = '';

    public string $time = '';

    public string $name = '';

    public string $phone = '';

    public string $notes = '';

    public ?int $bookingId = null;

    public function mount(): void
    {
        $this->date = today()->toDateString();
        $this->serviceId = Service::where('is_active', true)->orderBy('price')->value('id');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['serviceId', 'barberId', 'date'], true)) {
            $this->time = '';
        }
    }

    public function submit(BookingService $bookings): void
    {
        $this->validate([
            'serviceId' => 'required|exists:services,id',
            'barberId' => 'nullable|exists:barbers,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'name' => 'required|string|max:60',
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+\s\-]+$/'],
            'notes' => 'nullable|string|max:500',
        ]);

        if (! $this->phoneIsVerified($this->phone)) {
            return;
        }

        try {
            $booking = $bookings->book(
                $this->phone,
                $this->name,
                Service::findOrFail($this->serviceId),
                CarbonImmutable::parse("{$this->date} {$this->time}"),
                $this->barberId ? Barber::findOrFail($this->barberId) : null,
                $this->notes ?: null,
                auth()->check() ? 'whatsapp' : 'web',
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('time', $e->getMessage());
            $this->time = '';

            return;
        }

        $this->bookingId = $booking->id;
    }

    public function with(BookingService $bookings): array
    {
        $service = $this->serviceId ? Service::find($this->serviceId) : null;
        $date = $this->date ? CarbonImmutable::parse($this->date) : null;
        $closedReason = $date ? $bookings->closedReason($date) : null;

        return [
            'services' => Service::where('is_active', true)->orderBy('price')->get(),
            'barbers' => Barber::orderBy('name')->get(),
            'closedReason' => $closedReason,
            'slots' => ($service && $date && ! $closedReason)
                ? $bookings->availableSlots($date, $service, $this->barberId ? Barber::find($this->barberId) : null)
                : collect(),
            'booking' => $this->bookingId ? Booking::with(['service', 'barber'])->find($this->bookingId) : null,
            'maxDate' => today()->addDays(config('queue_shop.booking.max_advance_days'))->toDateString(),
        ];
    }
};
?>

<main class="mx-auto max-w-lg space-y-6 p-6">
    <a href="{{ route('home') }}" wire:navigate class="text-sm text-neutral-400">&larr; Back</a>
    <h1 class="text-3xl font-bold">Book a cut</h1>

    @if ($booking)
        <section class="space-y-2 rounded-2xl bg-neutral-900 p-6 text-center">
            <p class="text-2xl font-bold text-green-400">You're booked!</p>
            <p>{{ $booking->service->name }} with {{ $booking->barber->name }}</p>
            <p class="text-xl font-semibold">{{ $booking->starts_at->format('D j M Y, g:i A') }}</p>
            <p class="text-sm text-neutral-400">A WhatsApp confirmation was sent. We hold your slot for {{ config('queue_shop.booking.grace_minutes') }} minutes after the start time.</p>
        </section>
    @else
        <form wire:submit="submit" class="space-y-4 rounded-2xl bg-neutral-900 p-6">
            <div>
                <label class="mb-1 block text-sm text-neutral-400">Service</label>
                <select wire:model.live="serviceId" class="w-full rounded-lg bg-neutral-800 p-3">
                    @foreach ($services as $service)
                        <option wire:key="s-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }} - RM {{ number_format($service->price, 2) }} ({{ $service->duration_minutes }} min)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm text-neutral-400">Barber</label>
                <select wire:model.live="barberId" class="w-full rounded-lg bg-neutral-800 p-3">
                    <option value="">Any barber (assigned for you)</option>
                    @foreach ($barbers as $barber)
                        <option wire:key="b-{{ $barber->id }}" value="{{ $barber->id }}">{{ $barber->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm text-neutral-400">Date</label>
                <input type="date" wire:model.live="date" min="{{ today()->toDateString() }}" max="{{ $maxDate }}" class="w-full rounded-lg bg-neutral-800 p-3">
                @error('date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            @if ($closedReason)
                <p class="rounded-lg bg-red-500/15 p-3 text-red-300">We're closed on this day: {{ $closedReason }}. Please pick another date.</p>
            @else
                <div>
                    <label class="mb-1 block text-sm text-neutral-400">Time</label>
                    <div class="grid grid-cols-4 gap-2">
                        @forelse ($slots as $slot)
                            <button type="button" wire:key="slot-{{ $slot->format('Hi') }}" wire:click="$set('time', '{{ $slot->format('H:i') }}')"
                                class="rounded-lg p-2 text-sm {{ $time === $slot->format('H:i') ? 'bg-amber-400 font-bold text-black' : 'bg-neutral-800' }}">{{ $slot->format('g:i A') }}</button>
                        @empty
                            <p class="col-span-4 text-sm text-neutral-500">No free times on this day.</p>
                        @endforelse
                    </div>
                    @error('time') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <input wire:model="name" placeholder="Your name" class="w-full rounded-lg bg-neutral-800 p-3">
                @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <input wire:model="phone" type="tel" placeholder="WhatsApp number e.g. 60123456789" class="w-full rounded-lg bg-neutral-800 p-3">
                @error('phone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            @if ($awaitingCode)
                <div>
                    <input wire:model="code" inputmode="numeric" maxlength="6" placeholder="6-digit WhatsApp code" class="w-full rounded-lg bg-neutral-800 p-3 tracking-widest">
                    @error('code') <p class="mt-1 text-sm text-amber-300">{{ $message }}</p> @enderror
                </div>
            @endif
            <textarea wire:model="notes" rows="2" placeholder="Notes for the barber (style, preferences...)" class="w-full rounded-lg bg-neutral-800 p-3"></textarea>
            <button class="w-full rounded-xl bg-amber-400 p-4 text-lg font-bold text-black">Confirm booking</button>
        </form>
    @endif
</main>
