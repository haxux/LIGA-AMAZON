<?php

namespace App\Filament\Resources\Cups;

use App\Filament\Resources\Cups\Pages\CreateCup;
use App\Filament\Resources\Cups\Pages\EditCup;
use App\Filament\Resources\Cups\Pages\ListCups;
use App\Filament\Resources\Cups\RelationManagers\GroupsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\RoundsRelationManager;
use App\Models\Cup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Una copa de la temporada (Fase 15).
 *
 * Se arma en este orden: la copa, sus grupos si los lleva, los equipos que la
 * juegan y sus rondas. Los cruces de cada ronda viven en su propia pantalla,
 * porque son los que se tocan una y otra vez a medida que avanza el cuadro.
 */
class CupResource extends Resource
{
    protected static ?string $model = Cup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Cups';

    protected static string|\UnitEnum|null $navigationGroup = 'Competition';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->helperText('As it will read on the public site, e.g. Copa Amazonas.'),
                Toggle::make('has_group_stage')
                    ->label('Group stage first')
                    ->helperText('Off means a straight bracket from the first round.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                IconColumn::make('has_group_stage')->label('Groups')->boolean(),
                TextColumn::make('participants_count')->counts('participants')->label('Teams'),
                TextColumn::make('rounds_count')->counts('rounds')->label('Rounds'),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GroupsRelationManager::class,
            ParticipantsRelationManager::class,
            RoundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCups::route('/'),
            'create' => CreateCup::route('/create'),
            'edit' => EditCup::route('/{record}/edit'),
        ];
    }
}
