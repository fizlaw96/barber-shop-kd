<?php

use App\Enums\QueueTicketStatus;
use App\Models\Barber;
use App\Models\QueueTicket;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Queue board')] class extends Component {
    public function with(): array
    {
        return [
            'barbers' => Barber::where('is_available', true)->with(['queueTickets' => fn ($q) => $q->today()
                ->withStatus(QueueTicketStatus::Called, QueueTicketStatus::InChair)])->get(),
            'waiting' => QueueTicket::today()->withStatus(QueueTicketStatus::Waiting)->orderBy('number')->limit(12)->pluck('number'),
        ];
    }
};
?>
<main class="flex min-h-screen flex-col p-8 lg:p-12" wire:poll.2s>
    <header class="mb-10 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="grid size-14 place-items-center rounded-full border-2 border-brass-400 font-display text-2xl font-bold text-brass-400">KD</span>
            <span class="display text-3xl">KD Barber Shop</span>
        </div>
        <p class="display text-5xl text-brass-400" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)" x-text="now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })"></p>
    </header>

    <p class="eyebrow mb-4 text-base">Now serving</p>
    <div class="grid gap-6 [grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))]">
        @foreach ($barbers as $barber)
            @php($ticket = $barber->queueTickets->first())
            @php($called = $ticket?->status === QueueTicketStatus::Called)
            <div wire:key="barber-{{ $barber->id }}" class="card flex flex-col items-center gap-2 p-8 {{ $called ? 'calling border-ok' : '' }}">
                <p class="text-2xl font-semibold text-muted">{{ $barber->name }}</p>
                <p class="display text-[9rem] leading-none {{ $ticket ? 'text-brass-400' : 'text-ink-600' }}">{{ $ticket ? $ticket->display_number : '—' }}</p>
                @if ($called)
                    @php($remaining = max(0, (int) ceil(now()->diffInSeconds($ticket->call_expires_at, false))))
                    <div wire:key="cd-{{ $ticket->id }}" class="w-full space-y-3 text-center"
                        x-data="{ left: {{ $remaining }}, total: {{ config('queue_shop.call_window_seconds') }}, init() { const end = Date.now() + this.left * 1000; const timer = setInterval(() => { this.left = Math.max(0, Math.ceil((end - Date.now()) / 1000)); if (this.left <= 0) clearInterval(timer); }, 250); } }">
                        <span class="badge-ok px-5 py-2 text-xl">Please come now</span>
                        <p class="display text-6xl tabular-nums" :class="left <= 10 ? 'text-bad' : 'text-ok'"><span x-text="left"></span><span class="text-3xl text-muted">s</span></p>
                        <div class="h-2 overflow-hidden rounded-full bg-ink-700"><div class="h-full rounded-full transition-all duration-300" :class="left <= 10 ? 'bg-bad' : 'bg-ok'" :style="`width: ${(left / total) * 100}%`"></div></div>
                    </div>
                @elseif ($ticket)
                    <span class="badge-brass px-5 py-2 text-lg">In the chair</span>
                @else
                    <span class="badge-mute px-5 py-2 text-lg">Free</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-12">
        <p class="eyebrow mb-4 text-base">Next in line</p>
        <div class="flex flex-wrap gap-3">
            @forelse ($waiting as $number)
                <span wire:key="w-{{ $number }}" class="display rounded-2xl border border-ink-600 bg-ink-800 px-7 py-3 text-5xl">{{ \App\Models\QueueTicket::formatNumber($number) }}</span>
            @empty
                <p class="display text-3xl text-muted">No one waiting &mdash; walk right in.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-auto pt-10 text-muted">Walk-in? Scan or visit <span class="text-cream">{{ parse_url(config('app.url'), PHP_URL_HOST) }}/walk-in</span> or register at the counter.</div>
</main>
