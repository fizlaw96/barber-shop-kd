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
<main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-5">
    <div class="text-center">
        <span class="mx-auto mb-4 grid size-16 place-items-center rounded-full border-2 border-brass-400 font-display text-2xl font-bold text-brass-400">KD</span>
        <h1 class="display text-3xl">Staff login</h1>
    </div>
    <form wire:submit="login" class="card space-y-4">
        <div>
            <label class="label">Email</label>
            <input wire:model="email" type="email" class="input" autocomplete="username">
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Password</label>
            <input wire:model="password" type="password" class="input" autocomplete="current-password">
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <button class="btn-primary w-full">Sign in</button>
    </form>
    <a href="{{ route('home') }}" wire:navigate class="btn-ghost mx-auto">&larr; Back to site</a>
</main>
