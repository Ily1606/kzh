<?php

namespace App\Filament\Resources\Comments\CommentReportResource\Pages;

use App\Filament\Resources\Comments\CommentReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommentReport extends ViewRecord
{
    protected static string $resource = CommentReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make()
                ->label('Dismiss')
                ->visible(fn ($record) => ! $record->trashed())
                ->successNotificationTitle('Report dismissed successfully')
                ->successRedirectUrl(fn ($record) => static::getResource()::getUrl('view', ['record' => $record])),
            \Filament\Actions\RestoreAction::make()
                ->label('Restore Report')
                ->visible(fn ($record) => $record->trashed())
                ->successRedirectUrl(fn ($record) => static::getResource()::getUrl('view', ['record' => $record])),
        ];
    }
}
