<?php

namespace App\Providers;

use App\Services\Whatsapp\CloudApiWhatsappSender;
use App\Services\Whatsapp\LogWhatsappSender;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsappSender::class, fn () => config('queue_shop.whatsapp.driver') === 'cloud'
            ? new CloudApiWhatsappSender
            : new LogWhatsappSender);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
