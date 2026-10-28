<?php

namespace App\Filament\Resources\Plugins;

use App\Filament\Resources\Plugins\PluginReportResource\Pages\ListPluginReports;
use App\Filament\Resources\Plugins\PluginReportResource\Pages\ViewPluginReport;
use App\Models\PluginReport;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PluginReportResource extends Resource
{
    protected static ?string $model = PluginReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Plugin Reports';

    protected static ?string $navigationParentItem = 'Plugins';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Details')
                    ->components([
                        TextEntry::make('user.name')
                            ->label('Reporter Name')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('user.email')
                            ->label('Reporter Email')
                            ->icon('heroicon-m-envelope'),
                        TextEntry::make('created_at')
                            ->label('Reported At')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('deleted_at')
                            ->label('Dismissed At')
                            ->dateTime('d/m/Y H:i')
                            ->color('warning')
                            ->visible(fn ($record) => $record->trashed()),
                        TextEntry::make('reason')
                            ->label('Report Reason')
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),

                ...PluginResource::pluginInfolistSections('plugin.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plugin.user', 'plugin.categories', 'plugin.tags']))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Reporter')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('plugin.name')
                    ->label('Plugin')
                    ->icon('heroicon-m-cube')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('plugin.user.name')
                    ->label('Author')
                    ->icon('heroicon-m-user')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reason')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reason),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->label('Dismissed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make()
                    ->label('Dismiss')
                    ->successNotificationTitle('Report dismissed successfully'),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user', 'plugin.user', 'plugin.categories', 'plugin.tags']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPluginReports::route('/'),
            'view' => ViewPluginReport::route('/{record}'),
        ];
    }
}
