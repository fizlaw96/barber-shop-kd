@php
    $links = [
        ['dashboard', 'Queue'],
        ['bookings', 'Bookings'],
        ['customers', 'Customers'],
        ['closures', 'Closed days'],
        ['manage', 'Manage'],
    ];
@endphp
<header class="sticky top-0 z-30 border-b border-ink-700/70 bg-ink-950/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            <span class="grid size-8 place-items-center rounded-full border-2 border-brass-400 font-display text-sm font-bold text-brass-400">KD</span>
            <span class="eyebrow">Staff</span>
        </a>
        <nav class="flex flex-wrap items-center gap-1 text-sm font-semibold">
            @foreach ($links as [$name, $label])
                <a href="{{ route($name) }}" wire:navigate wire:key="nav-{{ $name }}"
                   class="rounded-lg px-3 py-2 transition {{ request()->routeIs($name) ? 'bg-brass-400/15 text-brass-300' : 'text-muted hover:text-cream' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('book') }}" wire:navigate class="btn-secondary btn-sm">New booking</a>
            <a href="{{ route('board') }}" target="_blank" class="btn-secondary btn-sm">TV board</a>
            <form method="POST" action="{{ route('logout') }}">@csrf <button class="btn-ghost btn-sm">Log out</button></form>
        </div>
    </div>
    <div class="pole opacity-70"></div>
</header>
