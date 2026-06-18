<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Broadcasting Authentication
         * Required for WebSocket private channels
         */
        Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
