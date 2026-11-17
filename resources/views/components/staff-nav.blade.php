<nav class="flex flex-wrap items-center gap-4 border-b border-neutral-800 px-6 py-3 text-sm">
    <a href="{{ route('dashboard') }}" wire:navigate class="font-semibold">Queue</a>
    <a href="{{ route('bookings') }}" wire:navigate class="font-semibold">Bookings</a>
    <a href="{{ route('closures') }}" wire:navigate class="font-semibold">Closed days</a>
    <a href="{{ route('manage') }}" wire:navigate class="font-semibold">Manage</a>
    <a href="{{ route('book') }}" wire:navigate class="text-neutral-400">New booking</a>
    <a href="{{ route('board') }}" target="_blank" class="text-neutral-400">TV board</a>
    <form method="POST" action="{{ route('logout') }}" class="ml-auto">@csrf <button class="text-neutral-400">Log out</button></form>
</nav>
