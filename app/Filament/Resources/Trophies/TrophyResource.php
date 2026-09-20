<?php

namespace App\Filament\Resources\Trophies;

use App\Filament\Resources\Trophies\Pages\CreateTrophy;
use App\Filament\Resources\Trophies\Pages\EditTrophy;
use App\Filament\Resources\Trophies\Pages\ListTrophies;
use App\Filament\Resources\Trophies\Schemas\TrophyForm;
use App\Filament\Resources\Trophies\Tables\TrophiesTable;
use App\Models\Trophy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrophyResource extends Resource
{
    protected static ?string $model = Trophy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'League';

    public static function form(Schema $schema): Schema
    {
        return TrophyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrophiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrophies::route('/'),
            'create' => CreateTrophy::route('/create'),
            'edit' => EditTrophy::route('/{record}/edit'),
        ];
    }
}
