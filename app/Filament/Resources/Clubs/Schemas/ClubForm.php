<?php

namespace App\Filament\Resources\Clubs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('The club identity, shared by every season it plays.'),
                TextInput::make('short_name')
                    ->required(),
                FileUpload::make('crest_path')
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
                    ->directory('crests')
                    ->visibility('public'),
                TextInput::make('founded_year')
                    ->numeric(),
                // El punto de partida del saldo (Fase 12). Lo fija el
                // administrador y el saldo se deriva de él y del libro de
                // movimientos; puede ser negativo, que es un club endeudado.
                TextInput::make('initial_balance')
                    ->label('Initial balance')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Starting point of the budget; movements are added to it.'),
            ]);
    }
}
