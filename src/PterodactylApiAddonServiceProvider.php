<?php

namespace Chredeur\PterodactylApiAddon;

use Illuminate\Support\ServiceProvider;

class PterodactylApiAddonServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

}
