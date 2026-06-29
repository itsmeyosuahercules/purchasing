<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Cegah lazy loading tak sengaja & mass-assignment diam-diam saat development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Paksa HTTPS di production agar cookie sesi tidak bocor lewat HTTP.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
