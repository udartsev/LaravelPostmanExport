<?php

namespace udartsev\LaravelPostmanExport;

use Illuminate\Support\ServiceProvider;

class PostmanServiceProvider extends ServiceProvider
{
    /**
     * Register the command.
     */
    public function register()
    {
        $this->commands(ExportRoutesToPostman::class);
    }
}
