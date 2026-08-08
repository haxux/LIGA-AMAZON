<?php

namespace App\Filament\Resources\Matchdays;

use App\Filament\Resources\Matchdays\Pages\CreateMatchday;
use App\Filament\Resources\Matchdays\Pages\EditMatchday;
use App\Filament\Resources\Matchdays\Pages\ListMatchdays;
use App\Filament\Resources\Matchdays\RelationManagers\GamesRelationManager;
use App\Filament\Resources\Matchdays\Schemas\MatchdayForm;
use App\Filament\Resources\Matchdays\Tables\MatchdaysTable;
use App\Models\Matchday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MatchdayResource extends Resource
{
    protected static ?string $model = Matchday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Competition';

    public static function form(Schema $schema): Schema
    {
        return MatchdayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatchdaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GamesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatchdays::route('/'),
            'create' => CreateMatchday::route('/create'),
            'edit' => EditMatchday::route('/{record}/edit'),
        ];
    }
}
