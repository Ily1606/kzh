<?php

namespace App\Filament\Resources\Plugins\Infolists;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PluginInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('plugin.infolist.sections.information'))
                    ->icon('heroicon-o-cube')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('name')
                            ->label(__('plugin.infolist.fields.name'))
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label(__('plugin.infolist.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state instanceof PluginStatus ? $state->value : (string) $state))
                            ->color(fn ($state): string => match ($state instanceof PluginStatus ? $state : PluginStatus::tryFrom((string) $state)) {
                                PluginStatus::Pending => 'warning',
                                PluginStatus::Approved => 'success',
                                PluginStatus::Rejected => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('categories.name')
                            ->label(__('plugin.infolist.fields.category'))
                            ->badge()
                            ->color('info')
                            ->placeholder(__('plugin.infolist.values.no_category')),

                        TextEntry::make('license')
                            ->label(__('plugin.infolist.fields.license'))
                            ->badge()
                            ->icon('heroicon-o-scale')
                            ->color('gray'),

                        TextEntry::make('tags.name')
                            ->label(__('plugin.infolist.fields.tags'))
                            ->badge()
                            ->color('gray')
                            ->separator(', ')
                            ->placeholder(__('plugin.infolist.values.no_tags'))
                            ->columnSpanFull(),

                        TextEntry::make('source_link')
                            ->label(__('plugin.infolist.fields.source_link'))
                            ->icon('heroicon-o-link')
                            ->url(fn (Plugin $record): ?string => $record->source_link)
                            ->openUrlInNewTab()
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('title')
                            ->label(__('plugin.infolist.fields.title'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('plugin.infolist.sections.author'))
                    ->icon('heroicon-o-user')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('user.name')
                            ->label(__('plugin.infolist.fields.author_name'))
                            ->icon('heroicon-o-user'),

                        TextEntry::make('user.email')
                            ->label(__('plugin.infolist.fields.author_email'))
                            ->icon('heroicon-o-envelope')
                            ->copyable(),

                        TextEntry::make('user.profile.github_name')
                            ->label(__('plugin.infolist.fields.author_github'))
                            ->icon('heroicon-o-code-bracket')
                            ->url(fn (Plugin $record): ?string => $record->user?->profile?->github_link)
                            ->openUrlInNewTab()
                            ->placeholder(__('plugin.infolist.values.not_connected')),

                        TextEntry::make('user.created_at')
                            ->label(__('plugin.infolist.fields.author_joined'))
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make(__('plugin.infolist.sections.status'))
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('created_at')
                            ->label(__('plugin.infolist.fields.submitted_at'))
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('approved_at')
                            ->label(__('plugin.infolist.fields.approved_at'))
                            ->dateTime('d/m/Y H:i')
                            ->placeholder(__('plugin.infolist.values.not_approved')),

                        TextEntry::make('updated_at')
                            ->label(__('plugin.infolist.fields.updated_at'))
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('deleted_at')
                            ->label(__('plugin.infolist.fields.deleted_at'))
                            ->dateTime('d/m/Y H:i')
                            ->color('danger')
                            ->visible(fn (Plugin $record): bool => $record->trashed()),
                    ]),

                Section::make(__('plugin.infolist.sections.statistics'))
                    ->icon('heroicon-o-chart-bar')
                    ->columnSpanFull()
                    ->columns(3)
                    ->components([
                        TextEntry::make('star_count')
                            ->label(__('plugin.infolist.fields.stars'))
                            ->icon('heroicon-o-star')
                            ->numeric()
                            ->color('warning'),

                        TextEntry::make('view_count')
                            ->label(__('plugin.infolist.fields.views'))
                            ->icon('heroicon-o-eye')
                            ->numeric()
                            ->color('info'),

                        TextEntry::make('comment_count')
                            ->label(__('plugin.infolist.fields.comments'))
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->numeric()
                            ->color('success'),
                    ]),

                Section::make(__('plugin.infolist.sections.history'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columnSpanFull()
                    ->collapsible()
                    ->components([
                        ViewEntry::make('events')
                            ->hiddenLabel()
                            ->view('filament.infolists.plugin-events')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
