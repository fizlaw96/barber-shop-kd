<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Staff login')] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        session()->regenerate();
        $this->redirectIntended(route('dashboard'), navigate: true);
    }
};
?>

<main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center p-6">
    <form wire:submit="login" class="space-y-4 rounded-2xl bg-neutral-900 p-6">
        <h1 class="text-2xl font-bold">Staff login</h1>
        <input wire:model="email" type="email" placeholder="Email" class="w-full rounded-lg bg-neutral-800 p-3">
        @error('email') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
        <input wire:model="password" type="password" placeholder="Password" class="w-full rounded-lg bg-neutral-800 p-3">
        <button class="w-full rounded-xl bg-amber-400 p-3 font-bold text-black">Sign in</button>
    </form>
</main>
