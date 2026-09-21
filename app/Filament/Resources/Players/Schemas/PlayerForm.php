<?php

namespace App\Filament\Resources\Players\Schemas;

use App\Models\Player;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Desde la Fase 9 este formulario edita la IDENTIDAD del jugador. El dorsal y
 * la plantilla a la que pertenece cada temporada se gestionan desde el equipo
 * de esa temporada (`SquadRelationManager`), porque ambos cambian de año en
 * año y el jugador no.
 */
class PlayerForm
{
    /** @var array<int, string> */
    public const POSITIONS = Player::POSITIONS;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->label('Club')
                    ->required()
                    ->searchable()
                    ->preload(),
                ...static::clubAgnosticFields(),
            ]);
    }

    /**
     * Shared with PlayersRelationManager on ClubResource, which reuses
     * everything except the club_id Select (the relationship sets it there).
     *
     * @return array<int, Component>
     */
    public static function clubAgnosticFields(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            Select::make('position')
                ->options(array_combine(self::POSITIONS, self::POSITIONS))
                ->required()
                ->native(false)
                ->live()
                // Cambiar de posición general invalida la específica anterior:
                // un central no puede seguir siendo extremo. Mismo gesto que en
                // SquadResource, porque el campo es el mismo visto desde el
                // otro panel.
                ->afterStateUpdated(fn (Set $set) => $set('specific_position', null)),
            Select::make('specific_position')
                ->options(fn (Get $get): array => array_combine(
                    Player::specificPositionsFor($get('position')),
                    Player::specificPositionsFor($get('position')),
                ))
                ->native(false)
                ->helperText('Pick the general position first.'),
            TextInput::make('market_value')
                ->label('Value')
                ->numeric()
                ->minValue(0)
                // Entero con separador de miles y sin símbolo de moneda
                // (decisión del propietario): la liga no fija una divisa.
                ->helperText('Plain figure, no currency symbol.'),
            DatePicker::make('birth_date'),
        ];
    }
}
