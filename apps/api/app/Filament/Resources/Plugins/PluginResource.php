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
            ->components(static::pluginInfolistSections());
    }

    public static function overviewSection(string $prefix = ''): Section
    {
        return Section::make('Plugin Overview')
            ->components([
                TextEntry::make($prefix.'name')
                    ->label('Plugin Identifier')
                    ->weight('bold'),
                TextEntry::make($prefix.'title')
                    ->label('Title'),
                TextEntry::make($prefix.'status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make($prefix.'license')
                    ->label('License')
                    ->badge()
                    ->color('gray'),
                TextEntry::make($prefix.'categories.name')
                    ->label('Categories')
                    ->badge()
                    ->placeholder('None'),
                TextEntry::make($prefix.'tags.name')
                    ->label('Tags')
                    ->badge()
                    ->placeholder('None'),
                TextEntry::make($prefix.'source_link')
                    ->label('Source Link')
                    ->icon('heroicon-m-link')
                    ->url(fn ($record): ?string => data_get($record, $prefix.'source_link'))
                    ->openUrlInNewTab()
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull();
    }

    public static function authorSection(string $prefix = ''): Section
    {
        return Section::make('Author Information')
            ->components([
                TextEntry::make($prefix.'user.name')
                    ->label('Author Name')
                    ->icon('heroicon-m-user'),
                TextEntry::make($prefix.'user.email')
                    ->label('Author Email')
                    ->icon('heroicon-m-envelope'),
            ])->columns(2)->columnSpanFull();
    }

    public static function statisticsSection(string $prefix = ''): Section
    {
        $resolvePlugin = fn ($record) => $prefix !== '' ? data_get($record, rtrim($prefix, '.')) : $record;

        return Section::make('Statistics & Engagement')
            ->components([
                TextEntry::make($prefix.'view_count')
                    ->label('Total Views')
                    ->icon('heroicon-m-eye')
                    ->numeric(),
                TextEntry::make('stars_count')
                    ->label('Total Stars')
                    ->icon('heroicon-m-star')
                    ->state(fn ($record): int => (int) (($plugin = $resolvePlugin($record)) ? ($plugin->stars_count ?? $plugin->stars()->count()) : 0)),
                TextEntry::make('comments_count')
                    ->label('Visible Comments')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->state(fn ($record): int => (int) (($plugin = $resolvePlugin($record)) ? ($plugin->comments_count ?? $plugin->comments()->visible()->count()) : 0)),
                TextEntry::make('reports_count')
                    ->label('Active Reports')
                    ->icon('heroicon-m-shield-exclamation')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                    ->state(fn ($record): int => (int) (($plugin = $resolvePlugin($record)) ? ($plugin->reports_count ?? $plugin->reports()->whereNull('deleted_at')->count()) : 0)),
            ])->columns(4)->columnSpanFull();
    }

    public static function timelineSection(string $prefix = ''): Section
    {
        $resolvePlugin = fn ($record) => $prefix !== '' ? data_get($record, rtrim($prefix, '.')) : $record;

        return Section::make('Timeline & Dates')
            ->components([
                TextEntry::make($prefix.'created_at')
                    ->label('Submitted At')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make($prefix.'approved_at')
                    ->label('Approved At')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Not approved yet'),
                TextEntry::make($prefix.'updated_at')
                    ->label('Last Updated')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make($prefix.'deleted_at')
                    ->label('Deleted At')
                    ->dateTime('d/m/Y H:i')
                    ->color('danger')
                    ->visible(fn ($record): bool => (bool) $resolvePlugin($record)?->trashed()),
            ])->columns(2)->columnSpanFull();
    }

    /**
     * @return array<Section>
     */
    public static function pluginInfolistSections(string $prefix = ''): array
    {
        return [
            static::overviewSection($prefix),
            static::authorSection($prefix),
            static::statisticsSection($prefix),
            static::timelineSection($prefix),
        ];
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
