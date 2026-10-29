<?php

namespace App\Filament\Resources\Comments\Tables;

use App\Enums\PluginStatus;
use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['plugin', 'author'])
                ->withCount([
                    'reports as reports_count' => fn (Builder $reports) => $reports->whereNull('deleted_at'),
                ])
            )
            ->columns([
                TextColumn::make('reports_count')
                    ->label(__('comment.table.columns.reports'))
                    ->icon('heroicon-m-shield-exclamation')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('plugin.name')
                    ->label(__('comment.table.columns.plugin'))
                    ->icon('heroicon-m-cube')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('author.name')
                    ->label(__('comment.table.columns.author'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('content')
                    ->label(__('comment.table.columns.content'))
                    ->lineClamp(2)
                    ->wrap()
                    ->extraCellAttributes([
                        'style' => 'max-width: 25rem; overflow-wrap: break-word;',
                    ]),

                TextColumn::make('created_at')
                    ->label(__('comment.table.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('plugin.status')
                    ->label(__('comment.table.columns.plugin_status'))
                    ->badge()
                    ->formatStateUsing(fn (PluginStatus $state): string => ucfirst($state->value))
                    ->color(fn (PluginStatus $state): string => match ($state) {
                        PluginStatus::Pending => 'warning',
                        PluginStatus::Approved => 'success',
                        PluginStatus::Rejected => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('author.email')
                    ->label(__('comment.table.columns.author_email'))
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hidden_at')
                    ->label(__('comment.table.columns.hidden_at'))
                    ->icon('heroicon-m-eye-slash')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('comment.table.values.visible'))
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('comment.table.columns.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plugin')
                    ->label(__('comment.table.filters.plugin'))
                    ->relationship('plugin', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('status')
                    ->label(__('comment.table.filters.status'))
                    ->options(PluginStatus::class)
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if (blank($status)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'plugin',
                            fn (Builder $pluginQuery): Builder => $pluginQuery->where('status', $status),
                        );
                    }),

                SelectFilter::make('trashed')
                    ->label(__('comment.table.filters.trashed'))
                    ->options([
                        'with'  => __('comment.table.filters.trashed_with'),
                        'only'  => __('comment.table.filters.trashed_only'),
                    ])
                    ->placeholder(__('comment.table.filters.trashed_without'))
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with' => $query->withTrashed(),
                            'only' => $query->onlyTrashed(),
                            default => $query->withoutTrashed(),
                        };
                    })
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters()
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Comment $record): string => CommentResource::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),

                Action::make('hide')
                    ->label(__('Hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Comment $record) => $record->hidden_at === null && !$record->trashed())
                    ->action(fn (Comment $record) => $record->cascadeHide())
                    ->requiresConfirmation()
                    ->modalHeading('Hide Comment')
                    ->modalDescription('Are you sure you want to hide this comment and its replies? You can unhide it at any time.'),

                Action::make('unhide')
                    ->label(__('Unhide'))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Comment $record) => $record->hidden_at !== null && !$record->trashed())
                    ->action(fn (Comment $record) => $record->cascadeUnhide())
                    ->requiresConfirmation()
                    ->modalHeading('Unhide Comment')
                    ->modalDescription('Are you sure you want to unhide this comment and its replies, making them visible again?'),

                DeleteAction::make()
                    ->visible(fn (Comment $record) => !$record->trashed())
                    ->modalHeading('Delete Comment')
                    ->modalDescription('Are you sure you want to delete this comment and its replies? Once deleted, it cannot be restored.')
                    ->using(function (Comment $record): bool {
                        $record->cascadeDelete();

                        return true;
                    }),
            ]);
    }
}
