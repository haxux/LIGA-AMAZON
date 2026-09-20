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
                    // ->image() sets acceptedFileTypes(['image/*']), which becomes the
                    // rule `mimetypes:image/*` and matches image/svg+xml. On the local
                    // 'public' disk these files are served same-origin under /storage/,
                    // so a script-bearing SVG would run with this site's privileges,
                    // including access to an authenticated admin session. On object
                    // storage the serving origin differs, which narrows the blast radius
                    // but does not remove it: a stored SVG is still script that another
                    // visitor's browser will execute. The rule holds on both disks. The
                    // explicit list below overrides that wildcard and MUST stay after
                    // ->image(), which would otherwise restore it. Raster formats only —
                    // they cannot carry script.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    // Below the web server's body limit and PHP's post_max_size, so
                    // an oversized file fails as a readable form error, not a 413.
                    ->maxSize(2048)
                    ->disk(config('filesystems.uploads'))
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
