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

    public function mount(BookingService $bookings): void
    {
        $this->serviceId = Service::where('is_active', true)->orderBy('price')->value('id');
        $this->date = today()->toDateString();

        $service = $this->serviceId ? Service::find($this->serviceId) : null;
        if ($service) {
            foreach (range(0, 14) as $offset) {
                $day = today()->addDays($offset);
                if ($bookings->availableSlots($day, $service)->isNotEmpty()) {
                    $this->date = $day->toDateString();
                    break;
                }
            }
        }
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
<div>
    <x-site-header />

    <main class="mx-auto max-w-2xl px-5 py-12">
        <p class="eyebrow mb-3">Online booking</p>
        <h1 class="display mb-8 text-4xl">Book a chair</h1>

        @if ($booking)
            <section class="card space-y-4 p-8 text-center">
                <span class="mx-auto grid size-16 place-items-center rounded-full bg-ok/15 text-3xl text-ok">&#10003;</span>
                <h2 class="display text-3xl">You're booked!</h2>
                <p class="text-muted">{{ $booking->service->name }} with {{ $booking->barber->name }}</p>
                <p class="display text-4xl text-brass-400">{{ $booking->starts_at->format('g:i A') }}</p>
                <p class="text-lg">{{ $booking->starts_at->format('l, j F Y') }}</p>
                <p class="mx-auto max-w-sm text-sm text-muted">A WhatsApp confirmation is on its way. We hold your slot for {{ config('queue_shop.booking.grace_minutes') }} minutes after the start time.</p>
                <a href="{{ route('home') }}" wire:navigate class="btn-secondary">Back to home</a>
            </section>
        @else
            <form wire:submit="submit" class="space-y-6">
                <section class="card space-y-4">
                    <h2 class="flex items-center gap-3 font-semibold"><span class="grid size-7 place-items-center rounded-full bg-brass-400 text-sm text-ink-950">1</span> Service &amp; barber</h2>
                    <div>
                        <label class="label">Service</label>
                        <select wire:model.live="serviceId" class="input">
                            @foreach ($services as $service)
                                <option wire:key="s-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }} &middot; RM{{ rtrim(rtrim(number_format($service->price, 2), '0'), '.') }} &middot; {{ $service->duration_minutes }} min</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Barber</label>
                        <select wire:model.live="barberId" class="input">
                            <option value="">Any barber (we'll pick for you)</option>
                            @foreach ($barbers as $barber)
                                <option wire:key="b-{{ $barber->id }}" value="{{ $barber->id }}">{{ $barber->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </section>

                <section class="card space-y-4">
                    <h2 class="flex items-center gap-3 font-semibold"><span class="grid size-7 place-items-center rounded-full bg-brass-400 text-sm text-ink-950">2</span> Date &amp; time</h2>
                    <div>
                        <label class="label">Date</label>
                        <input type="date" wire:model.live="date" min="{{ today()->toDateString() }}" max="{{ $maxDate }}" class="input">
                        @error('date') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    @if ($closedReason)
                        <div class="rounded-xl border border-bad/30 bg-bad/10 p-4 text-bad">
                            <p class="font-semibold">We're closed on this day: {{ $closedReason }}.</p>
                            <p class="mt-1 text-sm opacity-80">Please pick another date.</p>
                        </div>
                    @else
                        <div>
                            <label class="label">Available times</label>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                @forelse ($slots as $slot)
                                    <button type="button" wire:key="slot-{{ $slot->format('Hi') }}" wire:click="$set('time', '{{ $slot->format('H:i') }}')"
                                        class="rounded-xl border px-2 py-2.5 text-sm font-semibold transition {{ $time === $slot->format('H:i') ? 'border-brass-400 bg-brass-400 text-ink-950' : 'border-ink-600 bg-ink-800 hover:border-brass-500/60' }}">{{ $slot->format('g:i A') }}</button>
                                @empty
                                    <p class="col-span-full text-sm text-muted">No free times on this day. Try another date or barber.</p>
                                @endforelse
                            </div>
                            @error('time') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </section>

                <section class="card space-y-4">
                    <h2 class="flex items-center gap-3 font-semibold"><span class="grid size-7 place-items-center rounded-full bg-brass-400 text-sm text-ink-950">3</span> Your details</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Name</label>
                            <input wire:model="name" class="input" placeholder="Your name">
                            @error('name') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">WhatsApp number</label>
                            <input wire:model="phone" type="tel" class="input" placeholder="012-345 6789">
                            @error('phone') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @if ($awaitingCode)
                        <div>
                            <label class="label">Verification code</label>
                            <input wire:model="code" inputmode="numeric" maxlength="6" class="input text-center text-xl tracking-[0.5em]" placeholder="------">
                            @error('code') <p class="hint">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <label class="label">Notes for your barber (optional)</label>
                        <textarea wire:model="notes" rows="2" class="input" placeholder="Style, reference, allergies..."></textarea>
                    </div>
                </section>

                <button class="btn-primary btn-lg w-full" wire:loading.attr="disabled">{{ $awaitingCode ? 'Verify & confirm booking' : 'Confirm booking' }}</button>
            </form>
        @endif
    </main>

    <x-site-footer />
</div>
