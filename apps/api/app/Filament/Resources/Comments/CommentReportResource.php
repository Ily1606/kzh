<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\CommentReportResource\Pages\ListCommentReports;
use App\Filament\Resources\Comments\CommentReportResource\Pages\ViewCommentReport;
use App\Models\CommentReport;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CommentReportResource extends Resource
{
    protected static ?string $model = CommentReport::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Reports';

    protected static \UnitEnum|string|null $navigationGroup = 'Comments';

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Report Details')
                    ->components([
                        \Filament\Infolists\Components\TextEntry::make('user.name')->label('Reporter'),
                        \Filament\Infolists\Components\TextEntry::make('plugin.name')->label('Plugin'),
                        \Filament\Infolists\Components\TextEntry::make('reason'),
                        \Filament\Infolists\Components\TextEntry::make('status')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    ])->columns(2)->columnSpanFull(),

                \Filament\Schemas\Components\Section::make('Comment Context')
                    ->components([
                        \Filament\Infolists\Components\ViewEntry::make('comment_tree')
                            ->view('filament.infolists.comment-tree')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Reporter')->sortable()->searchable(),
                TextColumn::make('comment.content')->label('Comment')->limit(50),
                TextColumn::make('plugin.name')->label('Plugin'),
                TextColumn::make('reason')->limit(50),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommentReports::route('/'),
            'view' => ViewCommentReport::route('/{record}'),
        ];
    }
}
