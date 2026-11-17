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
    <main class="mx-auto max-w-3xl space-y-8 p-6">
        <h1 class="text-3xl font-bold">Manage shop</h1>
        @if ($message) <p class="rounded-lg bg-neutral-800 p-3 text-sm">{{ $message }}</p> @endif

        <form wire:submit="saveSettings" class="space-y-3 rounded-2xl bg-neutral-900 p-5">
            <h2 class="text-xl font-semibold">Opening hours</h2>
            <div class="flex flex-wrap gap-3">
                <label class="text-sm">Open <input type="time" wire:model="openTime" class="ml-1 rounded bg-neutral-800 p-2"></label>
                <label class="text-sm">Close <input type="time" wire:model="closeTime" class="ml-1 rounded bg-neutral-800 p-2"></label>
                <label class="text-sm">Minutes per walk-in cut <input type="number" wire:model="minutesPerCut" class="ml-1 w-20 rounded bg-neutral-800 p-2"></label>
            </div>
            @error('closeTime') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-3 text-sm">
                <span class="text-neutral-400">Closed every:</span>
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $day)
                    <label wire:key="d-{{ $i }}"><input type="checkbox" wire:model="weeklyClosedDays" value="{{ $i }}"> {{ $day }}</label>
                @endforeach
            </div>
            <button class="rounded-lg bg-amber-400 px-4 py-2 font-bold text-black">Save</button>
        </form>

        <section class="space-y-3 rounded-2xl bg-neutral-900 p-5">
            <h2 class="text-xl font-semibold">Services</h2>
            @foreach ($services as $service)
                <div wire:key="sv-{{ $service->id }}" class="flex items-center justify-between rounded-lg bg-neutral-800 p-3 text-sm {{ $service->is_active ? '' : 'opacity-50' }}">
                    <span>{{ $service->name }} &middot; RM {{ number_format($service->price, 2) }} &middot; {{ $service->duration_minutes }} min &middot; {{ $service->points }} pt</span>
                    <span class="flex gap-3">
                        <button wire:click="editService({{ $service->id }})" class="text-amber-400">Edit</button>
                        <button wire:click="toggleService({{ $service->id }})" class="text-neutral-400">{{ $service->is_active ? 'Hide' : 'Show' }}</button>
                    </span>
                </div>
            @endforeach
            <form wire:submit="saveService" class="grid gap-2 sm:grid-cols-2">
                <input wire:model="serviceName" placeholder="Name" class="rounded bg-neutral-800 p-2">
                <input wire:model="servicePrice" placeholder="Price (RM)" class="rounded bg-neutral-800 p-2">
                <input wire:model="serviceDuration" type="number" placeholder="Minutes" class="rounded bg-neutral-800 p-2">
                <input wire:model="servicePoints" type="number" min="1" max="3" placeholder="Points (1-3)" class="rounded bg-neutral-800 p-2">
                <input wire:model="serviceDescription" placeholder="Description" class="rounded bg-neutral-800 p-2 sm:col-span-2">
                @foreach (['serviceName', 'servicePrice', 'serviceDuration', 'servicePoints'] as $field)
                    @error($field) <p class="text-sm text-red-400 sm:col-span-2">{{ $message }}</p> @enderror
                @endforeach
                <button class="rounded-lg bg-amber-400 px-4 py-2 font-bold text-black sm:col-span-2">{{ $serviceId ? 'Update service' : 'Add service' }}</button>
            </form>
        </section>

        <section class="space-y-3 rounded-2xl bg-neutral-900 p-5">
            <h2 class="text-xl font-semibold">Barbers</h2>
            @foreach ($barbers as $barber)
                <div wire:key="br-{{ $barber->id }}" class="flex items-center justify-between rounded-lg bg-neutral-800 p-3 text-sm">
                    <span>{{ $barber->name }}</span>
                    <button wire:click="removeBarber({{ $barber->id }})" wire:confirm="Delete this barber?" class="text-red-400">Delete</button>
                </div>
            @endforeach
            <form wire:submit="addBarber" class="flex gap-2">
                <input wire:model="barberName" placeholder="New barber name" class="flex-1 rounded bg-neutral-800 p-2">
                <button class="rounded-lg bg-amber-400 px-4 py-2 font-bold text-black">Add</button>
            </form>
            @error('barberName') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        </section>
    </main>
</div>
