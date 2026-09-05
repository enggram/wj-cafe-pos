<?php

namespace App\Providers;

use App\Contracts\MenuServiceInterface;
use App\Services\MenuService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MenuServiceInterface::class, MenuService::class);
    }

    public function boot(): void
    {
        // Force all generated URLs (including redirects) to use APP_URL,
        // so the host port (:8080) is never dropped behind Nginx/Docker.
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
