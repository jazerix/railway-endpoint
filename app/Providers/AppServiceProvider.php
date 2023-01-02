<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('padToNearest', function() {
            $padTo = pow(2, ceil(log($this->count(), 2)));
            for($i = $this->count(); $i < $padTo; $i++)
                $this[] = 0;

            return $this;
        });
    }
}
