<?php

namespace App\Filament\Resources\Plugins\Tables;

use App\Enums\PluginStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PluginsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('plugin.table.columns.plugin_name'))
                    ->description(fn($record): string => $record->name ?? '')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
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
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label(__('plugin.table.filters.status'))
                    ->options(PluginStatus::class)
                    ->multiple(),
            ]);
    }
}
