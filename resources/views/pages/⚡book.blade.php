<?php

use App\Livewire\Concerns\VerifiesPhone;
use App\Models\Barber;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Book online')] class extends Component {
    use VerifiesPhone;

    public int $step = 1;

    public string $month = '';

    public ?int $serviceId = null;

    public ?int $barberId = null;

    public string $date = '';

    public string $time = '';

    public string $name = '';

    public string $phone = '';

    public string $notes = '';

    public function mount(BookingService $bookings): void
    {
        $this->serviceId = Service::where('is_active', true)->orderBy('price')->value('id');
        $this->date = today()->toDateString();
        $this->month = today()->format('Y-m');

        $service = $this->serviceId ? Service::find($this->serviceId) : null;
        if ($service) {
            foreach (range(0, 14) as $offset) {
                $day = today()->addDays($offset);
                if ($bookings->availableSlots($day, $service)->isNotEmpty()) {
                    $this->date = $day->toDateString();
                    $this->month = $day->format('Y-m');
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

    public function next(BookingService $bookings): void
    {
        if ($this->step === 1) {
            $this->validate([
                'serviceId' => 'required|exists:services,id',
                'barberId' => 'nullable|exists:barbers,id',
            ], ['serviceId.required' => 'Please choose a service.']);
        }

        if ($this->step === 2) {
            $this->validate([
                'date' => 'required|date|after_or_equal:today',
                'time' => 'required|date_format:H:i',
            ], ['time.required' => 'Please pick a time.']);

            if ($reason = $bookings->closedReason(CarbonImmutable::parse($this->date))) {
                $this->addError('date', "We're closed on this day ({$reason}).");

                return;
            }
        }

        $this->step = min(3, $this->step + 1);
    }

    public function previousMonth(): void
    {
        $previous = CarbonImmutable::parse($this->month.'-01')->subMonth();

        if ($previous->endOfMonth()->gte(today())) {
            $this->month = $previous->format('Y-m');
        }
    }

    public function nextMonth(): void
    {
        $next = CarbonImmutable::parse($this->month.'-01')->addMonth();

        if ($next->lte(today()->addDays(config('queue_shop.booking.max_advance_days')))) {
            $this->month = $next->format('Y-m');
        }
    }

    public function selectDate(string $date, BookingService $bookings): void
    {
        $day = CarbonImmutable::parse($date);
        $last = today()->addDays(config('queue_shop.booking.max_advance_days'));

        if ($day->lt(today()) || $day->gt($last)) {
            return;
        }

        if ($reason = $bookings->closedReason($day)) {
            $this->addError('date', "We're closed on {$day->format('j M')} ({$reason}).");

            return;
        }

        $this->resetErrorBag('date');
        $this->date = $day->toDateString();
        $this->month = $day->format('Y-m');
        $this->time = '';
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        if ($step < $this->step) {
            $this->step = $step;
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
            $this->step = 2;

            return;
        }

        session()->flash('toast', sprintf(
            'Booking sent! %s with %s on %s at %s. We sent the details to your WhatsApp.',
            $booking->service->name,
            $booking->barber->name,
            $booking->starts_at->format('D, j M'),
            $booking->starts_at->format('g:i A'),
        ));

        $this->redirect(route('home'), navigate: true);
    }

    /**
     * Month grid (Monday first), leaving out weeks that are entirely in the past.
     * Each day is open, closed (with reason), past or beyond the booking window.
     *
     * @return array<int, array{date: string, day: int, inMonth: bool, state: string, reason: ?string}>
     */
    private function buildCalendar(BookingService $bookings): array
    {
        $first = CarbonImmutable::parse($this->month.'-01');
        $start = $first->startOfWeek(CarbonImmutable::MONDAY);
        $end = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $last = today()->addDays(config('queue_shop.booking.max_advance_days'));
        $closed = $bookings->closedDates(max($start, today()), min($end, $last));

        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $state = match (true) {
                $day->lt(today()) => 'past',
                $day->gt($last) => 'far',
                isset($closed[$key]) => 'closed',
                default => 'open',
            };
            $days[] = ['date' => $key, 'day' => $day->day, 'inMonth' => $day->month === $first->month, 'state' => $state, 'reason' => $closed[$key] ?? null];
        }

        $weeks = array_filter(
            array_chunk($days, 7),
            fn (array $week) => collect($week)->contains(fn (array $day) => $day['state'] !== 'past'),
        );

        return array_merge(...array_values($weeks));
    }

    public function with(BookingService $bookings): array
    {
        $service = $this->serviceId ? Service::find($this->serviceId) : null;
        $date = $this->date ? CarbonImmutable::parse($this->date) : null;
        $closedReason = $date ? $bookings->closedReason($date) : null;

        return [
            'calendar' => $this->step === 2 ? $this->buildCalendar($bookings) : [],
            'services' => Service::where('is_active', true)->orderBy('price')->get(),
            'barbers' => Barber::orderBy('name')->get(),
            'service' => $service,
            'barber' => $this->barberId ? Barber::find($this->barberId) : null,
            'closedReason' => $closedReason,
            'timeSlots' => ($this->step === 2 && $service && $date && ! $closedReason)
                ? $bookings->availableSlots($date, $service, $this->barberId ? Barber::find($this->barberId) : null)
                : collect(),
            'steps' => [1 => 'Service', 2 => 'Date & time', 3 => 'Your details'],
        ];
    }
};
?>

<div>
    <x-site-header />

    <main class="mx-auto max-w-2xl px-5 py-12">
        <p class="eyebrow mb-3">Online booking</p>
        <h1 class="display mb-8 text-4xl">Book a chair</h1>

        {{-- Step indicator --}}
        <ol class="mb-8 flex items-center">
            @foreach ($steps as $number => $label)
                <li wire:key="ind-{{ $number }}" class="flex items-center {{ $number < 3 ? 'flex-1' : '' }}">
                    <button type="button" wire:click="goTo({{ $number }})" @disabled($number >= $step)
                        class="flex items-center gap-3 {{ $number < $step ? 'cursor-pointer' : 'cursor-default' }}">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-bold transition
                            {{ $number < $step ? 'border-brass-400 bg-brass-400 text-ink-950' : ($number === $step ? 'border-brass-400 text-brass-300' : 'border-ink-600 text-muted') }}">
                            {{ $number < $step ? '✓' : $number }}
                        </span>
                        <span class="hidden text-sm font-semibold sm:block {{ $number <= $step ? 'text-cream' : 'text-muted' }}">{{ $label }}</span>
                    </button>
                    @if ($number < 3)
                        <span class="mx-3 h-0.5 flex-1 rounded {{ $number < $step ? 'bg-brass-400' : 'bg-ink-600' }}"></span>
                    @endif
                </li>
            @endforeach
        </ol>

        <form wire:submit="{{ $step === 3 ? 'submit' : 'next' }}" class="space-y-6">
            @if ($step === 1)
                <section wire:key="step-1" class="card space-y-5">
                    <h2 class="display text-2xl">What are you after?</h2>
                    <div>
                        <label class="label">Service</label>
                        <select wire:model.live="serviceId" class="input">
                            @foreach ($services as $svc)
                                <option wire:key="s-{{ $svc->id }}" value="{{ $svc->id }}">{{ $svc->name }} &middot; RM{{ rtrim(rtrim(number_format($svc->price, 2), '0'), '.') }} &middot; {{ $svc->duration_minutes }} min</option>
                            @endforeach
                        </select>
                        @error('serviceId') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Barber</label>
                        <select wire:model.live="barberId" class="input">
                            <option value="">Any barber (we'll pick for you)</option>
                            @foreach ($barbers as $b)
                                <option wire:key="b-{{ $b->id }}" value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </section>
            @elseif ($step === 2)
                @php($monthStart = \Carbon\CarbonImmutable::parse($month.'-01'))
                @php($canGoBack = $monthStart->subMonth()->endOfMonth()->gte(today()))
                @php($canGoForward = $monthStart->addMonth()->lte(today()->addDays(config('queue_shop.booking.max_advance_days'))))
                <section wire:key="step-2" class="space-y-6">
                    <div class="card space-y-4">
                        <h2 class="display text-2xl">Pick a day</h2>

                        <div class="flex items-center justify-between">
                            <button type="button" wire:click="previousMonth" @disabled(! $canGoBack) class="btn-secondary btn-sm px-4" aria-label="Previous month">&larr;</button>
                            <p class="display text-xl">{{ $monthStart->format('F Y') }}</p>
                            <button type="button" wire:click="nextMonth" @disabled(! $canGoForward) class="btn-secondary btn-sm px-4" aria-label="Next month">&rarr;</button>
                        </div>

                        <div class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold uppercase tracking-wider text-muted">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                                <span wire:key="dow-{{ $dow }}">{{ $dow }}</span>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7 gap-1.5">
                            @foreach ($calendar as $cell)
                                @php($selected = $cell['date'] === $date)
                                <button type="button" wire:key="day-{{ $cell['date'] }}" data-date="{{ $cell['date'] }}" data-state="{{ $cell['state'] }}"
                                    @if ($cell['state'] === 'open') wire:click="selectDate('{{ $cell['date'] }}')" @else disabled @endif
                                    @if ($cell['reason']) title="{{ $cell['reason'] }}" @endif
                                    class="relative aspect-square rounded-xl text-sm font-semibold transition
                                        {{ ! $cell['inMonth'] ? 'opacity-30' : '' }}
                                        {{ $selected ? 'bg-brass-400 text-ink-950' : '' }}
                                        {{ ! $selected && $cell['state'] === 'open' ? 'border border-ink-600 bg-ink-800 hover:border-brass-400' : '' }}
                                        {{ $cell['state'] === 'closed' ? 'cursor-not-allowed border border-bad/30 bg-bad/10 text-bad/70 line-through' : '' }}
                                        {{ in_array($cell['state'], ['past', 'far'], true) ? 'cursor-not-allowed text-ink-600' : '' }}
                                        {{ $cell['date'] === today()->toDateString() && ! $selected ? 'ring-1 ring-brass-400/60' : '' }}">
                                    {{ $cell['day'] }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-muted">
                            <span class="flex items-center gap-2"><span class="size-3 rounded border border-ink-600 bg-ink-800"></span> Available</span>
                            <span class="flex items-center gap-2"><span class="size-3 rounded border border-bad/30 bg-bad/10"></span> Shop closed</span>
                            <span class="flex items-center gap-2"><span class="size-3 rounded bg-brass-400"></span> Selected</span>
                        </div>
                        @error('date') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <div class="card space-y-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="display text-2xl">Pick a time</h2>
                            <p class="text-sm text-muted">{{ \Carbon\CarbonImmutable::parse($date)->format('l, j F') }}</p>
                        </div>

                        @if ($closedReason)
                            <div class="rounded-xl border border-bad/30 bg-bad/10 p-4 text-bad">
                                <p class="font-semibold">We're closed on this day: {{ $closedReason }}.</p>
                                <p class="mt-1 text-sm opacity-80">Please pick another date.</p>
                            </div>
                        @elseif ($timeSlots->isEmpty())
                            <p class="rounded-xl border border-ink-700 bg-ink-800/60 p-4 text-sm text-muted">No free times on this day. Try another date or barber.</p>
                        @else
                            @foreach (['Morning' => $timeSlots->filter(fn ($s) => $s->hour < 12), 'Afternoon' => $timeSlots->filter(fn ($s) => $s->hour >= 12 && $s->hour < 17), 'Evening' => $timeSlots->filter(fn ($s) => $s->hour >= 17)] as $label => $group)
                                @if ($group->isNotEmpty())
                                    <div wire:key="grp-{{ $label }}">
                                        <p class="label">{{ $label }}</p>
                                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                            @foreach ($group as $slot)
                                                <button type="button" wire:key="slot-{{ $slot->format('Hi') }}" wire:click="$set('time', '{{ $slot->format('H:i') }}')"
                                                    class="rounded-xl border px-2 py-2.5 text-sm font-semibold transition {{ $time === $slot->format('H:i') ? 'border-brass-400 bg-brass-400 text-ink-950' : 'border-ink-600 bg-ink-800 hover:border-brass-500/60' }}">{{ $slot->format('g:i A') }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                        @error('time') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </section>
            @else
                <section wire:key="step-3" class="space-y-6">
                    <div class="card-flat flex flex-wrap items-center justify-between gap-3 border-brass-500/30">
                        <div>
                            <p class="font-semibold">{{ $service?->name }} <span class="font-normal text-muted">with</span> {{ $barber?->name ?? 'any barber' }}</p>
                            <p class="text-sm text-muted">{{ \Carbon\CarbonImmutable::parse($date)->format('l, j F Y') }} &middot; {{ $time ? \Carbon\CarbonImmutable::parse($time)->format('g:i A') : '' }}</p>
                        </div>
                        <p class="display text-2xl text-brass-400">RM{{ rtrim(rtrim(number_format((float) $service?->price, 2), '0'), '.') }}</p>
                    </div>

                    <div class="card space-y-4">
                        <h2 class="display text-2xl">Your details</h2>
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
                        @error('time') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </section>
            @endif

            <div class="flex gap-3">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="btn-secondary btn-lg">Back</button>
                @endif
                <button class="btn-primary btn-lg flex-1" wire:loading.attr="disabled">
                    {{ $step === 3 ? ($awaitingCode ? 'Verify & confirm booking' : 'Confirm booking') : 'Next' }}
                </button>
            </div>
        </form>
    </main>

    <x-site-footer />
</div>
