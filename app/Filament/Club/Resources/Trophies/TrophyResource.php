<?php

namespace App\Filament\Club\Resources\Trophies;

use App\Filament\Club\Resources\Trophies\Pages\ListTrophies;
use App\Models\Trophy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los trofeos del club, en el panel del técnico: sólo lectura.
 *
 * Quien los otorga es el administrador. Aquí no hay alta, edición ni borrado —
 * no por desconfianza, sino porque un palmarés que cada técnico pudiera
 * escribir no sería un palmarés.
 */
class TrophyResource extends Resource
{
    protected static ?string $model = Trophy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Trofeos';

    protected static ?string $slug = 'trofeos';

    protected static ?string $modelLabel = 'trofeo';

    protected static ?string $pluralModelLabel = 'trofeos';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('club_id', auth()->user()?->club_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('season_id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Trofeo')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Temporada')->sortable(),
            ])
            ->filters([
                SelectFilter::make('season')->label('Temporada')->relationship('season', 'name'),
            ])
            ->emptyStateHeading('Todavía sin títulos')
            ->emptyStateDescription('Cuando el club gane uno, aparecerá aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrophies::route('/'),
        ];
    }
}
