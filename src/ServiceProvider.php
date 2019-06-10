<?php

namespace udartsev\laravel-postman-export;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the command.
     */
    public function register()
    {
        $this->commands(ExportRoutesToPostman::class);
    }
}
