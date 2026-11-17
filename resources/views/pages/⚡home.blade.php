<?php

use App\Models\Barber;
use App\Models\Service;
use App\Services\BookingService;
use App\Models\ShopClosure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('KD Barber Shop')] class extends Component {
    public function with(BookingService $bookings): array
    {
        return [
            'closedToday' => $bookings->closedReason(today()),
            'services' => Service::where('is_active', true)->orderBy('price')->get(),
            'barbers' => Barber::orderBy('name')->get(),
            'closures' => ShopClosure::whereDate('closed_on', '>=', today())->orderBy('closed_on')->limit(5)->get(),
            'open' => app(\App\Services\ShopSettings::class)->openTime(),
            'close' => app(\App\Services\ShopSettings::class)->closeTime(),
        ];
    }
};
?>
<div>
    <x-site-header />

    <main>
        <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-14 lg:grid-cols-[1.25fr_1fr] lg:pt-20">
            <div>
                <p class="eyebrow mb-5">Classic cuts &middot; Modern fades</p>
                <h1 class="display text-5xl leading-[1.05] sm:text-6xl lg:text-7xl">Sharp cuts.<br><span class="text-brass-400">No waiting game.</span></h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-muted">Book your chair online, or join the live walk-in queue. We'll WhatsApp you a minute before it's your turn, so you can grab a coffee instead of a plastic chair.</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('book') }}" wire:navigate class="btn-primary btn-lg">Book a chair</a>
                    <a href="{{ route('walk-in') }}" wire:navigate class="btn-secondary btn-lg">Join walk-in queue</a>
                </div>
            </div>

            <aside class="card space-y-5">
                <div class="flex items-center justify-between">
                    <p class="eyebrow">Today</p>
                    @if ($closedToday)
                        <span class="badge-bad">Closed today</span>
                    @else
                        <span class="badge-ok"><span class="size-2 rounded-full bg-ok"></span> Open now</span>
                    @endif
                </div>
                <p class="display text-4xl">{{ $open }} &ndash; {{ $close }}</p>
                @if ($closedToday)
                    <p class="text-sm text-bad">{{ $closedToday }}</p>
                @endif
                @if ($closures->isNotEmpty())
                    <div class="border-t border-ink-700 pt-4">
                        <p class="label">Upcoming closed days</p>
                        <ul class="space-y-1.5 text-sm">
                            @foreach ($closures as $closure)
                                <li wire:key="c-{{ $closure->id }}" class="flex justify-between gap-3"><span>{{ $closure->closed_on->format('D, j M') }}</span><span class="text-muted">{{ $closure->reason ?: 'Closed' }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </section>

        <div class="pole opacity-60"></div>

        <section id="services" class="mx-auto max-w-6xl scroll-mt-20 px-5 pt-20">
            <p class="eyebrow mb-3">The menu</p>
            <h2 class="display mb-10 text-4xl">Services &amp; prices</h2>
            <div class="grid gap-x-12 gap-y-7 md:grid-cols-2">
                @foreach ($services as $service)
                    <div wire:key="s-{{ $service->id }}">
                        <div class="flex items-baseline gap-3">
                            <h3 class="text-lg font-semibold">{{ $service->name }}</h3>
                            <span class="mb-1 flex-1 border-b border-dashed border-ink-600"></span>
                            <span class="display text-xl text-brass-400">RM{{ rtrim(rtrim(number_format($service->price, 2), '0'), '.') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $service->description }}</p>
                        <p class="mt-2 flex gap-2"><span class="badge-mute">{{ $service->duration_minutes }} min</span><span class="badge-brass">+{{ $service->points }} pt</span></p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-5 pt-24">
            <p class="eyebrow mb-3">The crew</p>
            <h2 class="display mb-8 text-4xl">Our barbers</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($barbers as $barber)
                    <div wire:key="b-{{ $barber->id }}" class="card flex items-center gap-4">
                        <span class="grid size-14 place-items-center rounded-full bg-brass-400/15 font-display text-2xl font-bold text-brass-300">{{ mb_substr($barber->name, 0, 1) }}</span>
                        <div><p class="text-lg font-semibold">{{ $barber->name }}</p><p class="text-sm text-muted">Barber</p></div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-6 px-5 pt-24 md:grid-cols-2">
            <div class="card space-y-4 p-8">
                <p class="eyebrow">Skip the line</p>
                <h3 class="display text-3xl">Book ahead</h3>
                <p class="text-muted">Pick your service, barber and time. Closed days are blocked, so you'll never turn up to a locked door.</p>
                <a href="{{ route('book') }}" wire:navigate class="btn-primary">Choose a time</a>
            </div>
            <div class="card space-y-4 p-8">
                <p class="eyebrow">Right now</p>
                <h3 class="display text-3xl">Walk in</h3>
                <p class="text-muted">Register your number at the shop or here. We'll message you with your queue number and when to head over.</p>
                <a href="{{ route('walk-in') }}" wire:navigate class="btn-secondary">Get a queue number</a>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-5 pt-24">
            <div class="card flex flex-col items-start gap-8 p-8 md:flex-row md:items-center md:justify-between">
                <div class="max-w-md space-y-3">
                    <p class="eyebrow">Loyalty</p>
                    <h3 class="display text-3xl">Every {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }}th cut is on us</h3>
                    <p class="text-muted">Arrive on time and earn up to 3 points per visit. Collect {{ \App\Models\Customer::POINTS_FOR_FREE_CUT }} for a free cut.</p>
                    <a href="{{ route('my-points') }}" wire:navigate class="btn-secondary btn-sm">Check my points</a>
                </div>
                <div class="flex gap-3">
                    @foreach (range(1, \App\Models\Customer::POINTS_FOR_FREE_CUT) as $stamp)
                        <span wire:key="st-{{ $stamp }}" class="grid size-14 place-items-center rounded-full border-2 {{ $stamp === \App\Models\Customer::POINTS_FOR_FREE_CUT ? 'border-brass-400 bg-brass-400 text-ink-950' : 'border-dashed border-ink-600 text-muted' }} font-display text-lg font-bold">{{ $stamp === \App\Models\Customer::POINTS_FOR_FREE_CUT ? 'FREE' : $stamp }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    <x-site-footer />
</div>
