<?php

namespace App\Filament\Resources\Plugins\Pages;

use App\Filament\Resources\Plugins\PluginResource;
use Filament\Resources\Pages\ListRecords;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;
}
