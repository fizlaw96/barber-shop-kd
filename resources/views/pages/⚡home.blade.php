<?php

use App\Models\Barber;
use App\Models\Service;
use App\Models\ShopClosure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('KD Barber Shop')] class extends Component {
    public function with(): array
    {
        return [
            'services' => Service::where('is_active', true)->orderBy('price')->get(),
            'barbers' => Barber::orderBy('name')->get(),
            'closures' => ShopClosure::whereDate('closed_on', '>=', today())->orderBy('closed_on')->limit(5)->get(),
            'open' => app(\App\Services\ShopSettings::class)->openTime(),
            'close' => app(\App\Services\ShopSettings::class)->closeTime(),
        ];
    }
};
?>

<main class="mx-auto max-w-4xl space-y-12 p-6 py-12">
    <header class="space-y-4 text-center">
        <h1 class="text-5xl font-black">KD Barber Shop</h1>
        <p class="text-neutral-400">Open daily {{ $open }} - {{ $close }}</p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ route('book') }}" class="rounded-xl bg-amber-400 px-6 py-3 font-bold text-black">Book online</a>
            <a href="{{ route('walk-in') }}" class="rounded-xl bg-neutral-800 px-6 py-3 font-bold">Join walk-in queue</a>
        </div>
    </header>

    @if ($closures->isNotEmpty())
        <section class="rounded-2xl border border-red-500/40 bg-red-500/10 p-5">
            <h2 class="mb-2 font-semibold text-red-300">Upcoming closed days</h2>
            <ul class="text-sm">
                @foreach ($closures as $closure)
                    <li wire:key="c-{{ $closure->id }}">{{ $closure->closed_on->format('D j M Y') }} &mdash; {{ $closure->reason ?: 'Closed' }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h2 class="mb-4 text-2xl font-bold">Services</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($services as $service)
                <div wire:key="s-{{ $service->id }}" class="rounded-2xl bg-neutral-900 p-5">
                    <div class="flex justify-between"><h3 class="text-lg font-semibold">{{ $service->name }}</h3><span class="font-bold text-amber-400">RM {{ number_format($service->price, 2) }}</span></div>
                    <p class="mt-1 text-sm text-neutral-400">{{ $service->description }}</p>
                    <p class="mt-2 text-xs text-neutral-500">{{ $service->duration_minutes }} min &middot; earns {{ $service->points }} pt</p>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="mb-4 text-2xl font-bold">Our barbers</h2>
        <div class="flex flex-wrap gap-3">
            @foreach ($barbers as $barber)
                <span wire:key="b-{{ $barber->id }}" class="rounded-full bg-neutral-800 px-5 py-2">{{ $barber->name }}</span>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl bg-neutral-900 p-6">
        <h2 class="mb-2 text-xl font-bold">Loyalty</h2>
        <p class="text-neutral-300">Collect points every visit &mdash; arrive on time and earn up to 3 points per booking. Reach {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }} points for a free cut.</p>
        <a href="{{ route('my-points') }}" class="mt-3 inline-block text-amber-400 underline">Check my points</a>
    </section>
</main>
