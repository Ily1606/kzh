<?php

namespace App\Filament\Resources\Plugins\PluginReportResource\Pages;

use App\Filament\Resources\Plugins\PluginReportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPluginReport extends ViewRecord
{
    protected static string $resource = PluginReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deletePlugin')
                ->label('Delete Plugin')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->visible(fn ($record) => $record->plugin && ! $record->plugin->trashed())
                ->requiresConfirmation()
                ->modalHeading('Delete Plugin')
                ->modalDescription('Are you sure you want to delete this plugin? This will soft-delete the plugin and cascade clean up all its data.')
                ->modalSubmitActionLabel('Delete')
                ->action(fn ($record) => $record->plugin->delete())
                ->successNotificationTitle('Plugin deleted successfully.'),

            DeleteAction::make()
                ->label('Dismiss Report')
                ->visible(fn ($record) => ! $record->trashed())
                ->successNotificationTitle('Report dismissed successfully')
                ->successRedirectUrl(fn ($record) => static::getResource()::getUrl('view', ['record' => $record])),

            RestoreAction::make()
                ->label('Restore Report')
                ->visible(fn ($record) => $record->trashed())
                ->successRedirectUrl(fn ($record) => static::getResource()::getUrl('view', ['record' => $record])),
        ];
    }
}
