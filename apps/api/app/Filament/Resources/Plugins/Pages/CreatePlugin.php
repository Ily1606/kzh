<?php

namespace App\Filament\Resources\Plugins\Pages;

use App\Filament\Resources\Plugins\PluginResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlugin extends CreateRecord
{
    protected static string $resource = PluginResource::class;
}
