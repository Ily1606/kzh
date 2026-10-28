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
            Action::make('deletePlugin')
                ->label('Delete Plugin')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->visible(fn ($record) => $record->plugin && ! $record->plugin->trashed())
                ->requiresConfirmation()
                ->modalHeading('Delete Plugin')
                ->modalDescription('Are you sure you want to delete this plugin? This will soft-delete the plugin and cascade clean up all its data.')
                ->modalSubmitActionLabel('Delete')
                ->action(function (Action $action, $record): void {
                    $record->plugin->delete();
                    $action->success();
                })
                ->successNotificationTitle('Plugin deleted successfully.')
                ->successRedirectUrl(fn () => $this->getPreviousUrl()),

            DeleteAction::make()
                ->label('Dismiss Report')
                ->visible(fn ($record) => ! $record->trashed())
                ->successNotificationTitle('Report dismissed successfully')
                ->successRedirectUrl(fn () => $this->getPreviousUrl()),

            RestoreAction::make()
                ->label('Restore Report')
                ->visible(fn ($record) => $record->trashed())
                ->successRedirectUrl(fn ($record) => static::getResource()::getUrl('view', ['record' => $record])),
        ];
    }
}
