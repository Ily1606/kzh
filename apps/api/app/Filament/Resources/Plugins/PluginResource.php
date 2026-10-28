<?php

namespace App\Filament\Resources\Plugins;

use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Filament\Resources\Plugins\Pages\ViewPlugin;
use App\Filament\Resources\Plugins\Tables\PluginsTable;
use App\Models\Plugin;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'plugin';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plugin Overview')
                    ->components([
                        TextEntry::make('name')
                            ->label('Plugin Identifier')
                            ->weight('bold'),
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state): string => match ($state?->value ?? $state) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('license')
                            ->label('License')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('categories.name')
                            ->label('Categories')
                            ->badge()
                            ->placeholder('None'),
                        TextEntry::make('tags.name')
                            ->label('Tags')
                            ->badge()
                            ->placeholder('None'),
                        TextEntry::make('source_link')
                            ->label('Source Link')
                            ->icon('heroicon-m-link')
                            ->url(fn (Plugin $record): ?string => $record->source_link)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Author Information')
                    ->components([
                        TextEntry::make('user.name')
                            ->label('Author Name')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('user.email')
                            ->label('Author Email')
                            ->icon('heroicon-m-envelope'),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Statistics & Engagement')
                    ->components([
                        TextEntry::make('view_count')
                            ->label('Total Views')
                            ->icon('heroicon-m-eye')
                            ->numeric(),
                        TextEntry::make('stars_count')
                            ->label('Total Stars')
                            ->icon('heroicon-m-star')
                            ->state(fn (Plugin $record): int => (int) ($record->stars_count ?? $record->stars()->count())),
                        TextEntry::make('comments_count')
                            ->label('Visible Comments')
                            ->icon('heroicon-m-chat-bubble-left-right')
                            ->state(fn (Plugin $record): int => (int) ($record->comments_count ?? $record->comments()->visible()->count())),
                        TextEntry::make('reports_count')
                            ->label('Active Reports')
                            ->icon('heroicon-m-shield-exclamation')
                            ->badge()
                            ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                            ->state(fn (Plugin $record): int => (int) ($record->reports_count ?? $record->reports()->whereNull('deleted_at')->count())),
                    ])->columns(4)->columnSpanFull(),

                Section::make('Timeline & Dates')
                    ->components([
                        TextEntry::make('created_at')
                            ->label('Submitted At')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Not approved yet'),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('d/m/Y H:i')
                            ->color('danger')
                            ->visible(fn (Plugin $record): bool => $record->trashed()),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return PluginsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlugins::route('/'),
            'view' => ViewPlugin::route('/{record}'),
        ];
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user', 'categories', 'tags'])
            ->withCount([
                'stars as stars_count',
                'comments as comments_count' => fn (Builder $q) => $q->visible(),
                'reports as reports_count' => fn (Builder $q) => $q->whereNull('deleted_at'),
            ]);
    }
}
