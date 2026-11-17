<?php

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Customers')] class extends Component {
    public string $search = '';

    public string $filter = 'points';

    public function with(): array
    {
        $term = trim($this->search);
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $customers = Customer::query()
            ->withCount([
                'queueTickets as walk_in_cuts' => fn ($q) => $q->where('status', 'completed'),
                'bookings as booking_visits' => fn ($q) => $q->whereIn('status', ['arrived', 'completed']),
                'bookings as upcoming_bookings' => fn ($q) => $q->where('status', 'booked')->where('starts_at', '>', now()),
            ])
            ->when($term === '' && $this->filter === 'points', fn ($q) => $q->where('points', '>', 0))
            ->when($term === '' && $this->filter === 'free', fn ($q) => $q->where('points', '>=', Customer::POINTS_FOR_FREE_CUT))
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term, $digits) {
                $q->where('name', 'like', "%{$term}%");
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%'.Customer::normalizePhone($digits).'%');
                }
            }))
            ->orderByDesc('points')->orderByDesc('free_cuts_redeemed')->orderByDesc('updated_at')
            ->limit(50)->get();

        return [
            'customers' => $customers,
            'total' => Customer::count(),
            'withPoints' => Customer::where('points', '>', 0)->count(),
            'freeCutsReady' => Customer::where('points', '>=', Customer::POINTS_FOR_FREE_CUT)->count(),
        ];
    }
};
?>

<div>
    <x-staff-nav />
    <main class="mx-auto max-w-4xl space-y-6 px-5 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow mb-2">Loyalty</p>
                <h1 class="display text-4xl">Customers &amp; points</h1>
            </div>
            <div class="grid grid-cols-2 gap-3 text-center">
                <div class="card-flat px-5"><p class="display text-3xl text-brass-400">{{ $total }}</p><p class="text-xs uppercase tracking-wider text-muted">Customers</p></div>
                <div class="card-flat px-5"><p class="display text-3xl text-ok">{{ $freeCutsReady }}</p><p class="text-xs uppercase tracking-wider text-muted">Free cut ready</p></div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-1 rounded-xl bg-ink-800 p-1 text-sm font-semibold">
                @foreach (['points' => 'With points ('.$withPoints.')', 'free' => 'Free cut ready ('.$freeCutsReady.')', 'all' => 'All ('.$total.')'] as $key => $label)
                    <button wire:key="f-{{ $key }}" wire:click="$set('filter', '{{ $key }}')" class="rounded-lg px-4 py-2 transition {{ $filter === $key && $search === '' ? 'bg-brass-400 text-ink-950' : 'text-muted hover:text-cream' }}">{{ $label }}</button>
                @endforeach
            </div>
            <input type="search" wire:model.live.debounce.250ms="search" class="input min-w-48 flex-1" placeholder="Search phone or name...">
        </div>

        <div class="space-y-3">
            @forelse ($customers as $customer)
                <div wire:key="cu-{{ $customer->id }}" class="card-flat flex flex-wrap items-center gap-x-5 gap-y-3">
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="truncate font-semibold">{{ $customer->name ?: 'No name' }}</p>
                        <p class="text-sm text-muted">{{ $customer->phone }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @foreach (range(1, \App\Models\Customer::POINTS_FOR_FREE_CUT) as $stamp)
                            <span wire:key="st-{{ $customer->id }}-{{ $stamp }}" class="size-3.5 rounded-full {{ $stamp <= $customer->points ? 'bg-brass-400' : 'bg-ink-600' }}"></span>
                        @endforeach
                        <span class="display ml-1 text-xl text-brass-400">{{ $customer->points }}<span class="text-sm text-muted">/{{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}</span></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($customer->hasFreeCutAvailable())
                            <span class="badge-ok">Free cut ready</span>
                        @endif
                        <span class="badge-mute">{{ $customer->walk_in_cuts + $customer->booking_visits }} visits</span>
                        @if ($customer->free_cuts_redeemed)
                            <span class="badge-brass">{{ $customer->free_cuts_redeemed }} free used</span>
                        @endif
                        @if ($customer->upcoming_bookings)
                            <span class="badge-mute">{{ $customer->upcoming_bookings }} upcoming</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card-flat py-10 text-center text-muted">{{ $search !== '' ? 'No customer matches that search.' : ($filter === 'all' ? 'No customers yet.' : 'Nobody here yet.') }}</div>
            @endforelse
        </div>
    </main>
</div>
