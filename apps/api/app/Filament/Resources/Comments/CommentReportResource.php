<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\CommentReportResource\Pages\ListCommentReports;
use App\Filament\Resources\Comments\CommentReportResource\Pages\ViewCommentReport;
use App\Models\CommentReport;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommentReportResource extends Resource
{
    protected static ?string $model = CommentReport::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Comment Reports';

    protected static ?string $navigationParentItem = 'Comments';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Details')
                    ->components([
                        TextEntry::make('user.name')->label('Reporter'),
                        TextEntry::make('plugin.name')->label('Plugin'),
                        TextEntry::make('reason'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Dismissed At')
                            ->dateTime()
                            ->visible(fn ($record) => $record->trashed()),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Comment Context')
                    ->components([
                        ViewEntry::make('comment_tree')
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
                TextColumn::make('comment.content')
                    ->label('Comment')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->comment->content ?? null),
                TextColumn::make('plugin.name')->label('Plugin'),
                TextColumn::make('reason')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reason),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Dismissed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
