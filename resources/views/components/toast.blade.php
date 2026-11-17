<div
    x-data="{
        items: [],
        push(message, tone = 'ok') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone });
            setTimeout(() => this.items = this.items.filter(item => item.id !== id), 5000);
        },
    }"
    @if (session('toast')) x-init="push(@js(session('toast')))" @endif
    x-on:toast.window="push($event.detail.message, $event.detail.tone ?? 'ok')"
    class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4"
>
    <template x-for="item in items" :key="item.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="-translate-y-4 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="pointer-events-auto flex max-w-md items-start gap-3 rounded-2xl border bg-ink-900 px-5 py-4 shadow-2xl shadow-black/50"
            :class="item.tone === 'error' ? 'border-bad/50' : 'border-ok/50'"
            role="status"
        >
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-sm font-bold text-ink-950" :class="item.tone === 'error' ? 'bg-bad' : 'bg-ok'" x-text="item.tone === 'error' ? '!' : '✓'"></span>
            <p class="text-sm font-medium" x-text="item.message"></p>
        </div>
    </template>
</div>
