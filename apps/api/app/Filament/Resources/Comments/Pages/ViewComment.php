<?php

namespace App\Filament\Resources\Comments\Pages;

use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewComment extends ViewRecord
{
    protected static string $resource = CommentResource::class;

    #[On('comment-state-updated')]
    public function refreshRecordState(): void
    {
        $this->record = $this->resolveRecord($this->getRecord()->getKey());
    }

    public function getTitle(): string
    {
        return 'Comment Detail';
    }

    public function getHeading(): string
    {
        return 'Comment Detail';
    }

    public function getSubheading(): ?string
    {
        /** @var Comment $record */
        $record = $this->getRecord();

        $author = $record->author?->name ?? 'Unknown Author';
        $plugin = $record->plugin?->name ?? 'Unknown Plugin';

        return "Author: {$author} · Plugin: {$plugin}";
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl('index') => $resource::getNavigationLabel(),
            'Detail',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hide')
                ->label('Hide')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn (Comment $record): bool => $record->hidden_at === null && ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('Hide Comment')
                ->modalDescription('Are you sure you want to hide this comment and its replies? You can unhide it at any time.')
                ->action(function (Comment $record) {
                    $record->cascadeHide();
                    $this->record = $this->resolveRecord($record->getKey());
                    $this->dispatch('comment-state-updated');
                }),

            Action::make('unhide')
                ->label('Unhide')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->visible(fn (Comment $record): bool => $record->hidden_at !== null && ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading('Unhide Comment')
                ->modalDescription('Are you sure you want to unhide this comment and its replies, making them visible again?')
                ->action(function (Comment $record) {
                    $record->cascadeUnhide();
                    $this->record = $this->resolveRecord($record->getKey());
                    $this->dispatch('comment-state-updated');
                }),

            DeleteAction::make()
                ->visible(fn (Comment $record): bool => ! $record->trashed())
                ->modalHeading('Delete Comment')
                ->modalDescription('Are you sure you want to delete this comment and its replies? Once deleted, it cannot be restored.')
                ->using(function (Comment $record): bool {
                    $record->cascadeDelete();
                    $this->record = $this->resolveRecord($record->getKey());
                    $this->dispatch('comment-state-updated');

                    return true;
                })
                ->successRedirectUrl(null),
        ];
    }
}

