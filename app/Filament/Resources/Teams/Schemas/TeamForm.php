<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                TextInput::make('name')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('season_id', $get('season_id')),
                    ),
                TextInput::make('short_name')
                    ->required(),
                Select::make('division_id')
                    ->relationship('division', 'name', fn (Builder $query, Get $get) => $query->where('season_id', $get('season_id')))
                    ->searchable()
                    ->preload(),
                FileUpload::make('crest_path')
                    ->image()
                    // ->image() sets acceptedFileTypes(['image/*']), which becomes the
                    // rule `mimetypes:image/*` and matches image/svg+xml. Public-disk
                    // files are served same-origin under /storage/, so a script-bearing
                    // SVG would run with this site's privileges, including access to an
                    // authenticated admin session. The explicit list below overrides that
                    // wildcard and MUST stay after ->image(), which would otherwise
                    // restore it. Raster formats only — they cannot carry script.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    // Below nginx's client_max_body_size and PHP's post_max_size, so an
                    // oversized file fails as a readable form error, not a 413.
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('crests')
                    ->visibility('public'),
                TextInput::make('founded_year')
                    ->numeric(),
            ]);
    }
}
