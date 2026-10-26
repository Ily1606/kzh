<?php

namespace App\Filament\Resources\Plugins\Pages;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\PluginResource;
use App\Models\Plugin;
use App\Services\PluginEventService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPlugin extends ViewRecord
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('plugin.table.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Plugin $record) => $record->status === PluginStatus::Pending && ! $record->trashed())
                ->action(function (Plugin $record) {
                    app(PluginEventService::class)->review(
                        $record,
                        PluginEventType::Approved,
                        Auth::user(),
                        message: null,
                        write: fn (Plugin $plugin) => $plugin->forceFill([
                            'status' => PluginStatus::Approved,
                            'approved_at' => now(),
                        ])->save(),
                    );
                }),

            Action::make('reject')
                ->label(__('plugin.table.actions.reject'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn (Plugin $record) => $record->status === PluginStatus::Pending && ! $record->trashed())
                ->form([
                    Textarea::make('rejected_reason')
                        ->label(__('plugin.table.actions.reject_reason_label'))
                        ->placeholder(__('plugin.table.actions.reject_reason_placeholder'))
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (Plugin $record, array $data) {
                    app(PluginEventService::class)->review(
                        $record,
                        PluginEventType::Rejected,
                        Auth::user(),
                        $data['rejected_reason'],
                        fn (Plugin $plugin) => $plugin->forceFill([
                            'status' => PluginStatus::Rejected,
                            'approved_at' => null,
                        ])->save(),
                    );
                })
                ->modalHeading(__('plugin.table.actions.reject_modal_heading'))
                ->modalDescription(__('plugin.table.actions.reject_modal_description'))
                ->modalSubmitActionLabel(__('plugin.table.actions.reject_modal_submit')),

            Action::make('requestChanges')
                ->label(__('plugin.table.actions.request_changes'))
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (Plugin $record) => $record->status === PluginStatus::Pending && ! $record->trashed())
                ->form([
                    Textarea::make('message')
                        ->label(__('plugin.table.actions.request_changes_message_label'))
                        ->placeholder(__('plugin.table.actions.request_changes_message_placeholder'))
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (Plugin $record, array $data) {
                    app(PluginEventService::class)->review(
                        $record,
                        PluginEventType::UpdateRequested,
                        Auth::user(),
                        $data['message'],
                    );
                })
                ->modalHeading(__('plugin.table.actions.request_changes_modal_heading'))
                ->modalDescription(__('plugin.table.actions.request_changes_modal_description'))
                ->modalSubmitActionLabel(__('plugin.table.actions.request_changes_modal_submit')),

            DeleteAction::make()
                ->label('Delete Plugin')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->visible(fn (Plugin $record) => ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('Delete Plugin')
                ->modalDescription('Are you sure you want to delete this plugin? This will soft-delete the plugin and cascade clean up related stars, comments, reports, and trending metrics.')
                ->modalSubmitActionLabel('Delete')
                ->action(fn (Plugin $record) => $record->delete())
                ->successNotificationTitle('Plugin deleted successfully.')
                ->successRedirectUrl(fn () => static::getResource()::getUrl('index')),
        ];
    }
}
