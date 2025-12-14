<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Sommelier\AI\OpenAIClient;
use App\Services\Sommelier\AI\OpenAISommelier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIClient::class);

        $this->app->singleton(OpenAISommelier::class, function ($app) {
            return new OpenAISommelier(
                $app->make(OpenAIClient::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
