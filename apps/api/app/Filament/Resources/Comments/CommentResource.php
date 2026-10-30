<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Livewire\CommentContextTree;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Resources\Comments\Pages\ViewComment;
use App\Filament\Resources\Comments\Tables\CommentsTable;
use App\Models\Comment;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Comments';

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Comment) {
            return null;
        }

        return Str::limit($record->content, 35);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comment Details')
                    ->components([
                        TextEntry::make('author.name')->label('Author'),
                        TextEntry::make('author.email')->label('Author Email'),
                        TextEntry::make('plugin.name')->label('Plugin'),
                        TextEntry::make('created_at')->label('Submitted At')->dateTime('d/m/Y H:i'),
                        TextEntry::make('hidden_at')
                            ->label('Hidden At')
                            ->dateTime('d/m/Y H:i')
                            ->color('warning')
                            ->visible(fn ($record) => $record->hidden_at !== null),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('d/m/Y H:i')
                            ->color('danger')
                            ->visible(fn ($record) => $record->trashed()),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Comment Context')
                    ->components([
                        Livewire::make(CommentContextTree::class, fn (Comment $record): array => [
                            'commentId' => $record->id,
                        ])->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['plugin', 'author'])
            ->withCount([
                'reports as reports_count' => fn (Builder $reports) => $reports->whereNull('deleted_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CommentsTable::configure($table);
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
            'index' => ListComments::route('/'),
            'view' => ViewComment::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['plugin', 'author'])
            ->withCount([
                'reports as reports_count' => fn (Builder $reports) => $reports->whereNull('deleted_at'),
            ]);
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
}
