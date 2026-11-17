<?php

use App\Models\ShopClosure;
use App\Services\BookingService;
use App\Services\ShopSettings;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Closed days')] class extends Component {
    public string $type = 'date';

    public string $closedOn = '';

    public string $reason = '';

    /** @var array<int, int|string> */
    public array $weekdays = [];

    public function add(BookingService $bookings): void
    {
        if ($this->type === 'weekly') {
            $this->validate([
                'weekdays' => 'required|array|min:1',
                'weekdays.*' => 'integer|between:0,6',
            ], ['weekdays.required' => 'Pick at least one day of the week.', 'weekdays.min' => 'Pick at least one day of the week.']);

            $cancelled = $bookings->closeWeekdays(array_map('intval', $this->weekdays));
            $this->reset('weekdays');
            $this->announce('Weekly closed day added', $cancelled);

            return;
        }

        $this->validate([
            'closedOn' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string|max:120',
        ]);

        $cancelled = $bookings->closeShopOn(CarbonImmutable::parse($this->closedOn), $this->reason ?: null);
        $this->reset('closedOn', 'reason');
        $this->announce('Closed day added', $cancelled);
    }

    public function removeDate(int $id): void
    {
        ShopClosure::whereKey($id)->delete();
        $this->dispatch('toast', message: 'Closed day removed. Customers can book that date again.');
    }

    public function removeWeekday(int $weekday, BookingService $bookings): void
    {
        $bookings->reopenWeekday($weekday);
        $this->dispatch('toast', message: 'Weekly closure removed.');
    }

    private function announce(string $message, int $cancelled): void
    {
        $this->dispatch('toast', message: $cancelled > 0
            ? "{$message}. {$cancelled} existing booking(s) were cancelled and the customers notified."
            : "{$message}. Customers can no longer book it.");
    }

    public function with(ShopSettings $settings): array
    {
        $names = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        $closedWeekdays = $settings->weeklyClosedDays();

        return [
            'closures' => ShopClosure::whereDate('closed_on', '>=', today())->orderBy('closed_on')->get(),
            'closedWeekdays' => $closedWeekdays,
            'weekdayNames' => $names,
            'weekOrder' => [1, 2, 3, 4, 5, 6, 0],
        ];
    }
};
?>

<div>
    <x-staff-nav />
    <main class="mx-auto max-w-6xl space-y-8 px-5 py-8">
        <div>
            <p class="eyebrow mb-2">Holidays &amp; leave</p>
            <h1 class="display text-4xl">Closed days</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted">Customers can't book a closed day. If anyone is already booked, they're cancelled and told on WhatsApp.</p>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
            {{-- Left: what's closed --}}
            <div class="space-y-6">
                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="display text-2xl">Every week</h2>
                        <span class="badge-mute">Recurring</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($weekOrder as $day)
                            @if (in_array($day, $closedWeekdays, true))
                                <span wire:key="wd-{{ $day }}" class="badge-bad gap-2 py-2 text-sm">
                                    {{ $weekdayNames[$day] }}
                                    <button wire:click="removeWeekday({{ $day }})" wire:confirm="Open the shop again on {{ $weekdayNames[$day] }}s?" class="text-bad/70 hover:text-bad" aria-label="Remove {{ $weekdayNames[$day] }}">&times;</button>
                                </span>
                            @endif
                        @empty
                        @endforelse
                        @if ($closedWeekdays === [])
                            <p class="text-sm text-muted">Open every day of the week.</p>
                        @endif
                    </div>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="display text-2xl">Specific dates</h2>
                        <span class="badge-mute">{{ $closures->count() }} upcoming</span>
                    </div>
                    <div class="space-y-2">
                        @forelse ($closures as $closure)
                            <div wire:key="c-{{ $closure->id }}" class="card-flat flex items-center gap-4">
                                <div class="w-14 shrink-0 rounded-lg bg-bad/10 py-1.5 text-center text-bad">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider">{{ $closure->closed_on->format('M') }}</p>
                                    <p class="display text-2xl leading-none">{{ $closure->closed_on->format('j') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $closure->closed_on->format('l, j F Y') }}</p>
                                    <p class="truncate text-sm text-muted">{{ $closure->reason ?: 'Shop closed' }}</p>
                                </div>
                                <button wire:click="removeDate({{ $closure->id }})" wire:confirm="Reopen this date?" class="btn-ghost btn-sm text-bad">Remove</button>
                            </div>
                        @empty
                            <div class="card-flat py-8 text-center text-sm text-muted">No special closed dates yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            {{-- Right: add --}}
            <form wire:submit="add" class="card space-y-5 lg:sticky lg:top-24">
                <h2 class="display text-2xl">Add a closed day</h2>

                <div class="grid grid-cols-2 gap-1 rounded-xl bg-ink-800 p-1 text-sm font-semibold">
                    <button type="button" wire:click="$set('type', 'date')" class="rounded-lg py-2 transition {{ $type === 'date' ? 'bg-brass-400 text-ink-950' : 'text-muted hover:text-cream' }}">Specific date</button>
                    <button type="button" wire:click="$set('type', 'weekly')" class="rounded-lg py-2 transition {{ $type === 'weekly' ? 'bg-brass-400 text-ink-950' : 'text-muted hover:text-cream' }}">Every week</button>
                </div>

                @if ($type === 'date')
                    <div wire:key="form-date" class="space-y-4">
                        <p class="text-sm text-muted">The shop is closed for the whole day, e.g. Hari Raya or a holiday.</p>
                        <div>
                            <label class="label">Date</label>
                            <input type="date" wire:model="closedOn" class="input">
                            @error('closedOn') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Reason (optional)</label>
                            <input wire:model="reason" placeholder="e.g. Hari Raya" class="input">
                        </div>
                    </div>
                @else
                    <div wire:key="form-weekly" class="space-y-4">
                        <p class="text-sm text-muted">The shop is closed on these days every week, so no one can book them.</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($weekOrder as $day)
                                <label wire:key="pick-{{ $day }}" class="{{ in_array($day, $closedWeekdays, true) ? 'pointer-events-none opacity-40' : 'cursor-pointer' }}">
                                    <input type="checkbox" wire:model="weekdays" value="{{ $day }}" class="peer sr-only" @disabled(in_array($day, $closedWeekdays, true))>
                                    <span class="block rounded-xl border border-ink-600 bg-ink-800 px-4 py-2 text-sm font-semibold transition peer-checked:border-bad/50 peer-checked:bg-bad/15 peer-checked:text-bad">{{ substr($weekdayNames[$day], 0, 3) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('weekdays') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <button class="btn-primary w-full">{{ $type === 'weekly' ? 'Close every selected day' : 'Close the shop on this day' }}</button>
            </form>
        </div>
    </main>
</div>
