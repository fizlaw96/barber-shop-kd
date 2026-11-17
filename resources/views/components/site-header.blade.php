<header class="sticky top-0 z-30 border-b border-ink-700/70 bg-ink-950/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-6 px-5 py-4">
        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-full border-2 border-brass-400 font-display text-lg font-bold text-brass-400">KD</span>
            <span class="display hidden text-xl sm:block">KD Barber Shop</span>
        </a>
        <nav class="ml-auto flex items-center gap-1 text-sm font-medium text-muted sm:gap-3">
            <a href="{{ route('home') }}#services" class="hidden px-2 py-1 hover:text-cream sm:block">Services</a>
            <a href="{{ route('walk-in') }}" wire:navigate class="btn-secondary btn-sm">Walk-in queue</a>
            <a href="{{ route('book') }}" wire:navigate class="btn-primary btn-sm">Book now</a>
        </nav>
    </div>
</header>
