<?php

namespace App\Filament\Resources\Plugins\Pages;

use App\Filament\Resources\Plugins\PluginResource;
use App\Filament\Resources\Plugins\Tables\PluginsTable;
use Filament\Resources\Pages\ViewRecord;

class ViewPlugin extends ViewRecord
{
    protected static string $resource = PluginResource::class;

    public ?string $previousUrl = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->previousUrl = url()->previous();
    }

    protected function getPreviousUrl(): string
    {
        $fallback = static::getResource()::getUrl('index');
        $previous = $this->previousUrl;

        if (blank($previous)) {
            return $fallback;
        }

        $previousHost = parse_url($previous, PHP_URL_HOST);
        if ($previousHost && $previousHost !== request()->getHost()) {
            return $fallback;
        }

        $previousPath = parse_url($previous, PHP_URL_PATH);
        $viewPath = parse_url(static::getResource()::getUrl('view', ['record' => $this->getRecord()]), PHP_URL_PATH);

        if ($previousPath && $viewPath && str_starts_with($previousPath, $viewPath)) {
            return $fallback;
        }

        return $previous;
    }

    protected function getHeaderActions(): array
    {
        return [
            PluginsTable::approveAction(),
            PluginsTable::rejectAction(),
            PluginsTable::requestChangesAction(),
            PluginsTable::deleteAction(fn () => $this->getPreviousUrl(), 'Delete Plugin'),
        ];
    }
}
