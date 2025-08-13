<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Console\Commands\BenchRun;
use App\Console\Commands\BenchRunAll;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            BenchRun::class,
            BenchRunAll::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
