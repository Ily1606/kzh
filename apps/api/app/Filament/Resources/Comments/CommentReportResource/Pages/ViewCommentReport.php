<?php

namespace App\Filament\Resources\Comments\CommentReportResource\Pages;

use App\Filament\Resources\Comments\CommentReportResource;
use App\Models\CommentReport;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewCommentReport extends ViewRecord
{
    protected static string $resource = CommentReportResource::class;

    #[On('comment-state-updated')]
    public function refreshRecordState(): void
    {
        $this->record = $this->resolveRecord($this->getRecord()->getKey());
    }

    public function getTitle(): string
    {
        return 'Comment Report Detail';
    }

    public function getHeading(): string
    {
        return 'Comment Report Detail';
    }

    public function getSubheading(): ?string
    {
        /** @var CommentReport $record */
        $record = $this->getRecord();

        $reporter = $record->user?->name ?? 'Unknown Reporter';
        $plugin = $record->plugin?->name ?? 'Unknown Plugin';

        return "Reporter: {$reporter} · Plugin: {$plugin}";
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
            DeleteAction::make()
                ->label('Dismiss')
                ->visible(fn ($record) => ! $record->trashed())
                ->successNotificationTitle('Report dismissed successfully')
                ->using(function ($record): bool {
                    $deleted = (bool) $record->delete();
                    $this->record = $this->resolveRecord($record->getKey());
                    $this->dispatch('comment-state-updated');

                    return $deleted;
                })
                ->successRedirectUrl(''),
        ];
    }
}
