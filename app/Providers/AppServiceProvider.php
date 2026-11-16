<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em produção, recusa comandos que apagam o banco:
        // migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback e db:wipe.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
