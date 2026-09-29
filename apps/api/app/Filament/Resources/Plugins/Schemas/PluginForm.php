<?php

namespace App\Filament\Resources\Plugins\Schemas;

use App\Enums\PluginStatus;
use Filament\Forms\Components\DateTimePicker as ComponentsDateTimePicker;
use Filament\Forms\Components\Select as ComponentsSelect;
use Filament\Forms\Components\TextInput as ComponentsTextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\DateTimePicker;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;

class PluginForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Thông tin Plugin')->schema([
                        ComponentsTextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->columnSpanFull(),
                        ComponentsTextInput::make('name')
                            ->label('Mã định danh (Name)')
                            ->required(),
                        ComponentsTextInput::make('license')
                            ->label('Giấy phép (License)')
                            ->default('MIT'),
                        ComponentsTextInput::make('source_link')
                            ->label('Link mã nguồn')
                            ->url()
                            ->prefixIcon('heroicon-m-link')
                            ->columnSpanFull(),
                    ])->columns(2),
                ])->columnSpan(['sm' => 1, 'md' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Trạng thái & Quyền')->schema([
                        ComponentsSelect::make('user_id')
                            ->label('Tác giả')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),
                        ToggleButtons::make('status')
                            ->label('Trạng thái')
                            ->options(PluginStatus::class)
                            ->inline()
                            ->required()
                            ->default('pending'),
                        ComponentsDateTimePicker::make('approved_at')
                            ->label('Ngày duyệt'),
                    ]),

                    \Filament\Schemas\Components\Section::make('Thống kê')->schema([
                        ComponentsTextInput::make('star_count')->label('Lượt sao')->numeric()->default(0),
                        ComponentsTextInput::make('view_count')->label('Lượt xem')->numeric()->default(0),
                        ComponentsTextInput::make('comment_count')->label('Bình luận')->numeric()->default(0),
                    ])->collapsed(),
                ])->columnSpan(['sm' => 1, 'md' => 1]),
            ])
            ->columns(3);
    }
}
