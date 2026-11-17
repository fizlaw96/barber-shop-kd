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

    /** @var array<int, int|string> */
    public array $weeklyClosedDays = [];

    public ?int $serviceId = null;

    public string $serviceName = '';

    public string $serviceDescription = '';

    public string $servicePrice = '';

    public int $serviceDuration = 30;

    public int $servicePoints = 1;

    public string $barberName = '';

    public ?string $message = null;

    public function mount(ShopSettings $settings): void
    {
        $this->openTime = $settings->openTime();
        $this->closeTime = $settings->closeTime();
        $this->minutesPerCut = $settings->minutesPerCut();
        $this->weeklyClosedDays = $settings->weeklyClosedDays();
    }

    public function saveSettings(ShopSettings $settings): void
    {
        $this->validate([
            'openTime' => 'required|date_format:H:i',
            'closeTime' => 'required|date_format:H:i|after:openTime',
            'minutesPerCut' => 'required|integer|min:5|max:120',
            'weeklyClosedDays' => 'array',
            'weeklyClosedDays.*' => 'integer|between:0,6',
        ]);

        $settings->save([
            'open_time' => $this->openTime,
            'close_time' => $this->closeTime,
            'minutes_per_cut' => $this->minutesPerCut,
            'weekly_closed_days' => array_map('intval', $this->weeklyClosedDays),
        ]);
        $this->message = 'Settings saved.';
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
        $this->message = 'Service saved.';
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
        $this->reset('barberName');
    }

    public function removeBarber(int $id): void
    {
        $barber = Barber::withCount('bookings')->findOrFail($id);

        if ($barber->bookings_count > 0 || $barber->queueTickets()->exists()) {
            $this->message = "{$barber->name} has booking or queue history and can't be deleted. Switch them off on the queue page instead.";

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
<div>
    <x-staff-nav />
    <main class="mx-auto max-w-4xl space-y-8 px-5 py-8">
        <div>
            <p class="eyebrow mb-2">Settings</p>
            <h1 class="display text-4xl">Manage shop</h1>
        </div>
        @if ($message) <p class="badge-brass text-sm">{{ $message }}</p> @endif

        <form wire:submit="saveSettings" class="card space-y-5">
            <h2 class="display text-2xl">Opening hours</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="label">Opens</label><input type="time" wire:model="openTime" class="input"></div>
                <div><label class="label">Closes</label><input type="time" wire:model="closeTime" class="input">@error('closeTime') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Minutes per walk-in cut</label><input type="number" wire:model="minutesPerCut" class="input"></div>
            </div>
            <div>
                <p class="label">Closed every</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $day)
                        <label wire:key="d-{{ $i }}" class="cursor-pointer">
                            <input type="checkbox" wire:model="weeklyClosedDays" value="{{ $i }}" class="peer sr-only">
                            <span class="block rounded-xl border border-ink-600 bg-ink-800 px-4 py-2 text-sm font-semibold transition peer-checked:border-bad/50 peer-checked:bg-bad/15 peer-checked:text-bad">{{ $day }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="btn-primary">Save hours</button>
        </form>

        <section class="card space-y-5">
            <h2 class="display text-2xl">Services &amp; prices</h2>
            <div class="space-y-2">
                @foreach ($services as $service)
                    <div wire:key="sv-{{ $service->id }}" class="card-flat flex flex-wrap items-center gap-x-4 gap-y-1 {{ $service->is_active ? '' : 'opacity-50' }}">
                        <span class="min-w-0 flex-1 font-semibold">{{ $service->name }}</span>
                        <span class="display text-lg text-brass-400">RM{{ rtrim(rtrim(number_format($service->price, 2), '0'), '.') }}</span>
                        <span class="badge-mute">{{ $service->duration_minutes }} min</span>
                        <span class="badge-brass">{{ $service->points }} pt</span>
                        <button wire:click="editService({{ $service->id }})" class="btn-secondary btn-sm">Edit</button>
                        <button wire:click="toggleService({{ $service->id }})" class="btn-ghost btn-sm">{{ $service->is_active ? 'Hide' : 'Show' }}</button>
                    </div>
                @endforeach
            </div>

            <form wire:submit="saveService" class="space-y-4 border-t border-ink-700 pt-5">
                <p class="label">{{ $serviceId ? 'Edit service' : 'Add a service' }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Name</label><input wire:model="serviceName" class="input">@error('serviceName') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Price (RM)</label><input wire:model="servicePrice" class="input">@error('servicePrice') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Duration (min)</label><input type="number" wire:model="serviceDuration" class="input">@error('serviceDuration') <p class="error">{{ $message }}</p> @enderror</div>
                    <div><label class="label">Points (1-3)</label><input type="number" min="1" max="3" wire:model="servicePoints" class="input">@error('servicePoints') <p class="error">{{ $message }}</p> @enderror</div>
                    <div class="sm:col-span-2"><label class="label">Description</label><input wire:model="serviceDescription" class="input"></div>
                </div>
                <button class="btn-primary">{{ $serviceId ? 'Update service' : 'Add service' }}</button>
            </form>
        </section>

        <section class="card space-y-4">
            <h2 class="display text-2xl">Barbers</h2>
            <div class="space-y-2">
                @foreach ($barbers as $barber)
                    <div wire:key="br-{{ $barber->id }}" class="card-flat flex items-center justify-between">
                        <span class="font-semibold">{{ $barber->name }}</span>
                        <button wire:click="removeBarber({{ $barber->id }})" wire:confirm="Delete this barber?" class="btn-ghost btn-sm text-bad">Delete</button>
                    </div>
                @endforeach
            </div>
            <form wire:submit="addBarber" class="flex gap-3">
                <input wire:model="barberName" placeholder="New barber name" class="input">
                <button class="btn-primary">Add</button>
            </form>
            @error('barberName') <p class="error">{{ $message }}</p> @enderror
        </section>
    </main>
</div>
