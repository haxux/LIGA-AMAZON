<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class NewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('body')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull(),
                FileUpload::make('cover_path')
                    ->image()
                    ->disk('public')
                    ->directory('news')
                    ->visibility('public'),
                DateTimePicker::make('published_at')
                    ->helperText('Leave empty to keep this a draft. A future date schedules it.'),
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
