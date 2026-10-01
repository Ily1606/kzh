<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('user.table.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label(__('user.table.columns.email'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope'),
                    
                TextColumn::make('status')
                    ->label(__('user.table.columns.status'))
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->trashed()) {
                            return 'deleted';
                        }
                        if ($record->locked_at) {
                            return 'locked';
                        }
                        return 'active';
                    })
                    ->formatStateUsing(fn (string $state): string => __('user.table.columns.status_options.' . $state))
                    ->color(fn (string $state): string => match ($state) {
                        'deleted' => 'danger',
                        'locked' => 'warning',
                        'active' => 'success',
                    }),

                TextColumn::make('created_at')
                    ->label(__('user.table.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('verified')
                    ->label(__('user.table.filters.verified'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'))
                    ->toggle(),
                
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label(__('user.table.columns.status'))
                    ->multiple()
                    ->options([
                        'active' => __('user.table.columns.status_options.active'),
                        'locked' => __('user.table.columns.status_options.locked'),
                        'deleted' => __('user.table.columns.status_options.deleted'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($values) {
                            if (in_array('deleted', $values)) {
                                $q->orWhereNotNull('deleted_at');
                            }
                            
                            if (in_array('locked', $values)) {
                                $q->orWhere(fn (Builder $sub) => $sub->whereNull('deleted_at')->whereNotNull('locked_at'));
                            }
                            
                            if (in_array('active', $values)) {
                                $q->orWhere(fn (Builder $sub) => $sub->whereNull('deleted_at')->whereNull('locked_at'));
                            }
                        });
                    }),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters()
            ->defaultSort('created_at', 'desc');
    }
}
