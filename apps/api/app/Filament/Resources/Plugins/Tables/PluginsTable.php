<?php

namespace App\Filament\Resources\Plugins\Tables;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Services\PluginEventService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PluginsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->with('user')
                ->withCount([
                    'stars as star_count',
                    'comments as comment_count' => fn(Builder $comments) => $comments->visible(),
                ]))
            ->columns([

                TextColumn::make('name')
                    ->label(__('plugin.table.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label(__('plugin.table.columns.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                TextColumn::make('user.name')
                    ->label(__('plugin.table.columns.author'))
                    ->description(fn($record): string => $record->user->email ?? '')
                    ->icon('heroicon-m-user')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('plugin.table.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                    ->color(fn($state): string => match ($state) {
                        PluginStatus::Pending, 'pending' => 'warning',
                        PluginStatus::Approved, 'approved' => 'success',
                        PluginStatus::Rejected, 'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('star_count')
                    ->label(__('plugin.table.columns.stars'))
                    ->alignCenter()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label(__('plugin.table.columns.views'))
                    ->alignCenter()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comment_count')
                    ->label(__('plugin.table.columns.comments'))
                    ->alignCenter()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('license')
                    ->label(__('plugin.table.columns.license'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source_link')
                    ->label(__('plugin.table.columns.source_link'))
                    ->icon('heroicon-m-link')
                    ->url(fn($record): ?string => $record->source_link)
                    ->openUrlInNewTab()
                    ->limit(25)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label(__('plugin.table.columns.approved_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('plugin.table.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('plugin.table.filters.status'))
                    ->options(PluginStatus::class)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false),

                TrashedFilter::make()
                    ->native(false),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters()
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('reject')
                    ->label(__('plugin.table.actions.reject'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === PluginStatus::Pending)
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
                            fn(Plugin $plugin) => $plugin->forceFill([
                                'status' => PluginStatus::Rejected,
                                'approved_at' => null,
                            ])->save(),
                        );
                    })
                    ->modalHeading(__('plugin.table.actions.reject_modal_heading'))
                    ->modalDescription(__('plugin.table.actions.reject_modal_description'))
                    ->modalSubmitActionLabel(__('plugin.table.actions.reject_modal_submit')),
                Action::make('approve')
                    ->label(__('plugin.table.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === PluginStatus::Pending)
                    ->action(function (Plugin $record) {
                        app(PluginEventService::class)->review(
                            $record,
                            PluginEventType::Approved,
                            Auth::user(),
                            message: null,
                            write: fn(Plugin $plugin) => $plugin->forceFill([
                                'status' => PluginStatus::Approved,
                                'approved_at' => now(),
                            ])->save(),
                        );
                    }),
                Action::make('requestChanges')
                    ->label(__('plugin.table.actions.request_changes'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === PluginStatus::Pending)
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
            ]);
    }
}
