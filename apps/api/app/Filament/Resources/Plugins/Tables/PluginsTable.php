<?php

namespace App\Filament\Resources\Plugins\Tables;

use App\Enums\PluginStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PluginsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
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
                    ->color(fn($state): string => match ($state) {
                        PluginStatus::Pending, 'pending' => 'warning',
                        PluginStatus::Approved, 'approved' => 'success',
                        PluginStatus::Rejected, 'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('star_count')
                    ->label(__('plugin.table.columns.stars'))
                    ->icon('heroicon-m-star')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label(__('plugin.table.columns.views'))
                    ->icon('heroicon-m-eye')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comment_count')
                    ->label(__('plugin.table.columns.comments'))
                    ->icon('heroicon-m-chat-bubble-left')
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
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
            ->deferFilters();
    }
}
