<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    PluginServiceProvider::class,
];
