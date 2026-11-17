<?php

use App\Models\Barber;
use App\Models\Service;
use App\Services\ShopSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Manage shop')] class extends Component {
    public string $openTime = '';

    public string $closeTime = '';

    public int $minutesPerCut = 20;

    public ?int $serviceId = null;

    public string $serviceName = '';

    public string $serviceDescription = '';

    public string $servicePrice = '';

    public int $serviceDuration = 30;

    public int $servicePoints = 1;

    public string $barberName = '';

    public function mount(ShopSettings $settings): void
    {
        $this->openTime = $settings->openTime();
        $this->closeTime = $settings->closeTime();
        $this->minutesPerCut = $settings->minutesPerCut();
    }

    public function saveSettings(ShopSettings $settings): void
    {
        $this->validate([
            'openTime' => 'required|date_format:H:i',
            'closeTime' => 'required|date_format:H:i|after:openTime',
            'minutesPerCut' => 'required|integer|min:5|max:120',
        ]);

        $settings->save([
            'open_time' => $this->openTime,
            'close_time' => $this->closeTime,
            'minutes_per_cut' => $this->minutesPerCut,
        ]);
        $this->dispatch('toast', message: 'Opening hours saved.');
    }

    public function editService(int $id): void
    {
        $service = Service::findOrFail($id);
        $this->serviceId = $service->id;
        $this->serviceName = $service->name;
        $this->serviceDescription = (string) $service->description;
        $this->servicePrice = (string) $service->price;
        $this->serviceDuration = $service->duration_minutes;
        $this->servicePoints = $service->points;
    }

    public function saveService(): void
    {
        $data = $this->validate([
            'serviceName' => 'required|string|max:80',
            'serviceDescription' => 'nullable|string|max:300',
            'servicePrice' => 'required|numeric|min:0|max:9999',
            'serviceDuration' => 'required|integer|min:15|max:240',
            'servicePoints' => 'required|integer|between:1,'.Service::MAX_POINTS,
        ]);

        Service::updateOrCreate(['id' => $this->serviceId], [
            'name' => $data['serviceName'],
            'description' => $data['serviceDescription'] ?: null,
            'price' => $data['servicePrice'],
            'duration_minutes' => $data['serviceDuration'],
            'points' => $data['servicePoints'],
        ]);

        $this->reset('serviceId', 'serviceName', 'serviceDescription', 'servicePrice', 'serviceDuration', 'servicePoints');
        $this->resetValidation();
        $this->dispatch('toast', message: 'Service saved.');
    }

    public function newService(): void
    {
        $this->reset('serviceId', 'serviceName', 'serviceDescription', 'servicePrice', 'serviceDuration', 'servicePoints');
        $this->resetValidation();
    }

    public function toggleService(int $id): void
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);
    }

    public function addBarber(): void
    {
        $this->validate(['barberName' => 'required|string|max:60']);
        Barber::create(['name' => $this->barberName]);
        $this->dispatch('toast', message: "{$this->barberName} added.");
        $this->reset('barberName');
    }

    public function removeBarber(int $id): void
    {
        $barber = Barber::withCount('bookings')->findOrFail($id);

        if ($barber->bookings_count > 0 || $barber->queueTickets()->exists()) {
            $this->dispatch('toast', message: "{$barber->name} has booking or queue history and can't be deleted. Switch them off on the queue page instead.", tone: 'error');

            return;
        }

        $barber->delete();
    }

    public function with(): array
    {
        return ['services' => Service::orderBy('price')->get(), 'barbers' => Barber::orderBy('name')->get()];
    }
};
?>
<div x-data="{ tab: 'hours', tabs: ['hours', 'services', 'barbers'] }">
    <x-staff-nav />
    <main class="mx-auto max-w-5xl space-y-6 px-5 py-8">
        <div>
            <p class="eyebrow mb-2">Settings</p>
            <h1 class="display text-4xl">Manage shop</h1>
        </div>

        {{-- Sliding tab bar --}}
        <div class="relative grid grid-cols-3 rounded-2xl border border-ink-700 bg-ink-900 p-1.5 text-sm font-semibold sm:max-w-md">
            <span class="absolute inset-y-1.5 left-1.5 w-[calc((100%-0.75rem)/3)] rounded-xl bg-brass-400 transition-transform duration-300 ease-out"
                  :style="`transform: translateX(${tabs.indexOf(tab) * 100}%)`"></span>
            <button type="button" @click="tab = 'hours'" class="relative z-10 rounded-xl py-2.5 transition" :class="tab === 'hours' ? 'text-ink-950' : 'text-muted hover:text-cream'">Hours</button>
            <button type="button" @click="tab = 'services'" class="relative z-10 rounded-xl py-2.5 transition" :class="tab === 'services' ? 'text-ink-950' : 'text-muted hover:text-cream'">Services &amp; prices</button>
            <button type="button" @click="tab = 'barbers'" class="relative z-10 rounded-xl py-2.5 transition" :class="tab === 'barbers' ? 'text-ink-950' : 'text-muted hover:text-cream'">Barbers</button>
        </div>

        {{-- Hours --}}
        <section x-show="tab === 'hours'" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
            <form wire:submit="saveSettings" class="card space-y-5">
                <h2 class="display text-2xl">Opening hours</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="label">Opens</label><input type="time" wire:model="openTime" class="input"></div>
                    <div><label class="label">Closes</label><input type="time" wire:model="closeTime" class="input">@error('closeTime') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Minutes per walk-in cut</label><input type="number" wire:model="minutesPerCut" class="input">@error('minutesPerCut') <p class="error">{{ $message }}</p> @enderror</div>
                </div>
                <p class="text-sm text-muted">Holidays and weekly days off are set on the <a href="{{ route('closures') }}" wire:navigate class="text-brass-300 underline">Closed days</a> page.</p>
                <button class="btn-primary">Save hours</button>
            </form>
        </section>

        {{-- Services --}}
        <section x-show="tab === 'services'" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
                 class="grid items-start gap-6 lg:grid-cols-[1.3fr_1fr]">
            <div class="card space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="display text-2xl">Services <span class="text-muted">({{ $services->count() }})</span></h2>
                    <button wire:click="newService" class="btn-secondary btn-sm">+ New</button>
                </div>
                <div class="max-h-[32rem] space-y-2 overflow-y-auto pr-1">
                    @foreach ($services as $service)
                        <div wire:key="sv-{{ $service->id }}" class="card-flat flex flex-wrap items-center gap-x-3 gap-y-2 {{ $service->is_active ? '' : 'opacity-50' }} {{ $serviceId === $service->id ? 'border-brass-400/60' : '' }}">
                            <span class="min-w-0 flex-1 basis-40 font-semibold">{{ $service->name }}</span>
                            <span class="display text-lg text-brass-400">RM{{ rtrim(rtrim(number_format($service->price, 2), '0'), '.') }}</span>
                            <span class="badge-mute">{{ $service->duration_minutes }} min</span>
                            <span class="badge-brass">{{ $service->points }} pt</span>
                            <button wire:click="editService({{ $service->id }})" class="btn-secondary btn-sm">Edit</button>
                            <button wire:click="toggleService({{ $service->id }})" class="btn-ghost btn-sm">{{ $service->is_active ? 'Hide' : 'Show' }}</button>
                        </div>
                    @endforeach
                </div>
            </div>

            <form wire:submit="saveService" class="card space-y-4 lg:sticky lg:top-24">
                <h2 class="display text-2xl">{{ $serviceId ? 'Edit service' : 'Add a service' }}</h2>
                <div><label class="label">Name</label><input wire:model="serviceName" class="input">@error('serviceName') <p class="error">{{ $message }}</p> @enderror</div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="label">Price (RM)</label><input wire:model="servicePrice" class="input">@error('servicePrice') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Minutes</label><input type="number" wire:model="serviceDuration" class="input">@error('serviceDuration') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Points</label><input type="number" min="1" max="3" wire:model="servicePoints" class="input">@error('servicePoints') <p class="error">{{ $message }}</p> @enderror</div>
                </div>
                <div><label class="label">Description</label><input wire:model="serviceDescription" class="input"></div>
                <button class="btn-primary w-full">{{ $serviceId ? 'Update service' : 'Add service' }}</button>
            </form>
        </section>

        {{-- Barbers --}}
        <section x-show="tab === 'barbers'" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
            <div class="card space-y-4">
                <h2 class="display text-2xl">Barbers</h2>
                <div class="space-y-2">
                    @foreach ($barbers as $barber)
                        <div wire:key="br-{{ $barber->id }}" class="card-flat flex items-center justify-between">
                            <span class="flex items-center gap-3 font-semibold"><span class="grid size-9 place-items-center rounded-full bg-brass-400/15 font-display font-bold text-brass-300">{{ mb_substr($barber->name, 0, 1) }}</span>{{ $barber->name }}</span>
                            <button wire:click="removeBarber({{ $barber->id }})" wire:confirm="Delete this barber?" class="btn-ghost btn-sm text-bad">Delete</button>
                        </div>
                    @endforeach
                </div>
                <form wire:submit="addBarber" class="flex gap-3">
                    <input wire:model="barberName" placeholder="New barber name" class="input">
                    <button class="btn-primary">Add</button>
                </form>
                @error('barberName') <p class="error">{{ $message }}</p> @enderror
            </div>
        </section>
    </main>
</div>
