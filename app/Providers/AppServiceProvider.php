<?php

namespace App\Providers;

use App\Events\TaskUpdated;
use App\Listeners\SendTaskNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Repository bindings
        $this->app->bind(
            \App\Repositories\Contracts\TaskRepositoryInterface::class,
            \App\Repositories\TaskRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners
        Event::listen(
            TaskUpdated::class,
            SendTaskNotification::class,
        );
    }
}
