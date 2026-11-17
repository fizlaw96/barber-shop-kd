<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/book', 'pages::book')->name('book');
Route::livewire('/walk-in', 'pages::walk-in')->name('walk-in');
Route::livewire('/board', 'pages::board')->name('board');
Route::livewire('/login', 'pages::login')->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/dashboard/bookings', 'pages::bookings')->name('bookings');
    Route::livewire('/dashboard/customers', 'pages::customers')->name('customers');
    Route::livewire('/dashboard/manage', 'pages::manage')->name('manage');
    Route::livewire('/dashboard/closures', 'pages::closures')->name('closures');

    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
