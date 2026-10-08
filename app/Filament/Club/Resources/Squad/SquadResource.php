<?php

namespace App\Filament\Club\Resources\Squad;

use App\Filament\Club\Resources\Squad\Pages\EditSquadPlayer;
use App\Filament\Club\Resources\Squad\Pages\ListSquad;
use App\Models\Player;
use App\Models\SquadMembership;
use App\Services\SeasonResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La plantilla del club que dirige el técnico.
 *
 * El recurso es el JUGADOR, no la pertenencia: lo que el técnico ajusta aquí
 * —posición general y específica— pertenece a la ficha del jugador y le
 * acompaña de una temporada a otra. El dorsal, que sí cambia cada año, se
 * muestra leyéndolo de la plantilla de la temporada vigente.
 *
 * El nombre y el valor son de sólo lectura: los fija el administrador.
 *
 * Las altas y bajas de jugadores no están aquí a propósito: las hace el
 * administrador, y en la Fase 12 pasarán por fichajes y traspasos.
 */
class SquadResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Plantilla';

    protected static ?string $slug = 'plantilla';

    protected static ?string $modelLabel = 'jugador';

    protected static ?string $pluralModelLabel = 'plantilla';

    protected static ?int $navigationSort = 1;

    /**
     * Segunda cerradura, además de las policies: el técnico sólo ve a los suyos
     * aunque teclee una URL.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('club_id', auth()->user()?->club_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    // La identidad la lleva el administrador; el técnico decide
                    // dónde juega, no quién es.
                    ->disabled(),
                Select::make('position')
                    ->label('Posición')
                    ->options(array_combine(Player::POSITIONS, Player::POSITIONS))
                    ->required()
                    ->native(false)
                    ->live()
                    // Cambiar de posición general invalida la específica anterior:
                    // un central no puede seguir siendo extremo.
                    ->afterStateUpdated(fn (Set $set) => $set('specific_position', null)),
                Select::make('specific_position')
                    ->label('Posición específica')
                    ->options(fn (Get $get): array => array_combine(
                        Player::specificPositionsFor($get('position')),
                        Player::specificPositionsFor($get('position')),
                    ))
                    ->native(false)
                    ->helperText('Elige primero la posición general.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('shirt_number')
                    ->label('#')
                    ->state(fn (Player $record): ?int => static::currentShirtNumber($record))
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (Player $record, string $state): string => static::currentMembership($record)?->is_captain
                        ? $state.' (C)'
                        : $state),
                TextColumn::make('birth_date')
                    ->label('Edad')
                    ->state(fn (Player $record): ?int => $record->birth_date?->age)
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('position')->label('Posición')->badge()->sortable(),
                TextColumn::make('specific_position')
                    ->label('Posición específica')
                    ->badge()
                    ->color('info')
                    ->placeholder('Sin asignar'),
                // Se ve y no se edita: el valor lo fija el administrador, y por
                // eso no está en el formulario de esta pantalla.
                TextColumn::make('market_value')
                    ->label('Valor')
                    ->numeric(thousandsSeparator: '.')
                    ->placeholder('Sin valorar')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: '.')),
            ])
            ->filters([
                SelectFilter::make('position')
                    ->label('Posición')
                    ->options(array_combine(Player::POSITIONS, Player::POSITIONS)),
            ])
            ->recordActions([
                Action::make('captain')
                    ->label(fn (Player $record): string => static::currentMembership($record)?->is_captain ? 'Quitar capitanía' : 'Hacer capitán')
                    ->icon(fn (Player $record): string => static::currentMembership($record)?->is_captain ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (Player $record): bool => static::currentMembership($record) !== null)
                    ->action(function (Player $record): void {
                        $membership = static::currentMembership($record);

                        if ($membership === null) {
                            return;
                        }

                        $membership->update(['is_captain' => ! $membership->is_captain]);
                    }),
                EditAction::make()->label('Editar'),
            ]);
    }

    /**
     * El dorsal de la temporada vigente, si el jugador está inscrito en ella.
     */
    private static function currentShirtNumber(Player $player): ?int
    {
        return static::currentMembership($player)?->shirt_number;
    }

    /**
     * La pertenencia del jugador en la temporada vigente: de ahí salen el
     * dorsal y la capitanía, que cambian cada año y no pertenecen a la ficha
     * del jugador.
     */
    private static function currentMembership(Player $player): ?SquadMembership
    {
        $season = app(SeasonResolver::class)->active();

        if ($season === null) {
            return null;
        }

        return SquadMembership::query()
            ->where('player_id', $player->getKey())
            ->whereHas('team', fn (Builder $query) => $query->where('season_id', $season->getKey()))
            ->first();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSquad::route('/'),
            'edit' => EditSquadPlayer::route('/{record}/edit'),
        ];
    }
}
