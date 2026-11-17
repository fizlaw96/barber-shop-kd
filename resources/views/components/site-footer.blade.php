<footer class="mt-24 border-t border-ink-700/70">
    <div class="pole opacity-70"></div>
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm text-muted">
        <p class="display text-lg text-cream">KD Barber Shop</p>
        <nav class="flex gap-5">
            <a href="{{ route('book') }}" wire:navigate class="hover:text-cream">Book</a>
            <a href="{{ route('walk-in') }}" wire:navigate class="hover:text-cream">Walk-in</a>
            <a href="{{ route('my-points') }}" wire:navigate class="hover:text-cream">My points</a>
            <a href="{{ route('login') }}" wire:navigate class="hover:text-cream">Staff</a>
        </nav>
    </div>
</footer>
