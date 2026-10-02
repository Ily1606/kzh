<?php

namespace App\Filament\Resources\Comments\Tables;

use App\Enums\PluginStatus;
use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['plugin', 'author']))
            ->columns([
                TextColumn::make('plugin.name')
                    ->label(__('comment.table.columns.plugin'))
                    ->icon('heroicon-m-cube')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label(__('comment.table.columns.author'))
                    ->searchable()
                    ->sortable(),

                // The cell itself is the trigger: Filament renders it as a button
                // that mounts the `view` action, so no bespoke JS is needed.
                TextColumn::make('content')
                    ->label(__('comment.table.columns.content'))
                    ->lineClamp(2)
                    ->wrap()
                    // Filament's `lineClamp()` only emits `--line-clamp`, and `.fi-wrapped`
                    // only sets `white-space: normal`, so neither constrains the
                    // column width — `max-width` does. `overflow-wrap: break-word`
                    // covers the occasional long unbroken token (a URL, a hashtag)
                    // without shrinking min-content width for ordinary prose,
                    // which already wraps on spaces. Bodies with no spaces at all
                    // would need `overflow-wrap: anywhere` instead, as that is the
                    // only value which also relaxes intrinsic sizing.
                    ->extraCellAttributes([
                        'style' => 'max-width: 25rem; overflow-wrap: break-word;',
                    ])
                    ->action(
                        Action::make('view')
                            ->label(__('comment.table.actions.view'))
                            ->modalHeading(fn (Comment $record): string => $record->plugin?->name ?? __('comment.table.actions.view_modal_heading'))
                            ->modalContent(fn (Comment $record) => view('filament.comments.show', [
                                'comment' => $record,
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel(__('comment.table.actions.close'))
                    ),

                TextColumn::make('created_at')
                    ->label(__('comment.table.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Optional columns. `toggleable(isToggledHiddenByDefault: true)`
                // keeps them out of the way until an admin switches them on from
                // the table's column manager, mirroring how PluginsTable exposes
                // its secondary fields.
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

                // `status` lives on the plugins table, not the comments table, so
                // this filter has to walk the relation instead of filtering a column.
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
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters()
            ->defaultSort('created_at', 'desc');
    }
}
