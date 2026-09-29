<?php

namespace App\Filament\Resources\Plugins\Pages;

use App\Filament\Resources\Plugins\PluginResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả'),

            'pending' => Tab::make('Đang chờ')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', \App\Enums\PluginStatus::Pending->value)),

            'approved' => Tab::make('Đã duyệt')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', \App\Enums\PluginStatus::Approved->value)),

            'rejected' => Tab::make('Từ chối')
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', \App\Enums\PluginStatus::Rejected->value)),
        ];
    }
}
