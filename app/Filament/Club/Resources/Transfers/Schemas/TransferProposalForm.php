<?php

namespace App\Filament\Club\Resources\Transfers\Schemas;

use App\Models\Player;
use App\Models\Transfer;
use App\Services\SeasonResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que el técnico propone: la misma operación que registra el administrador,
 * con una diferencia —la suya es una propuesta y no mueve nada hasta que la
 * firman.
 *
 * Su club es siempre uno de los dos extremos y no lo elige: proponer un
 * traspaso entre otros dos clubes no es asunto suyo. Lo que sí elige es de qué
 * lado está, y eso decide el resto del formulario.
 *
 * `direction` no es una columna: es la pregunta que hace falta para rellenar
 * `from_club_id` y `to_club_id` sin pedírselos.
 */
class TransferProposalForm
{
    public const IN = 'in';

    public const OUT = 'out';

    /** @var array<string, string> */
    public const DIRECTIONS = [
        self::IN => 'Mi club se lo queda',
        self::OUT => 'Mi club lo cede',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('direction')
                    ->label('Operación')
                    ->options(self::DIRECTIONS)
                    ->default(self::IN)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('player_id', null);
                        $set('to_club_id', null);
                        $set('external_club', null);
                    }),

                Select::make('scope')
                    ->label('Ámbito')
                    ->options(fn (Get $get): array => $get('direction') === self::IN
                        // Traer a alguien de fuera de la liga exige darle ficha
                        // antes, y las fichas las crea el administrador.
                        ? [Transfer::SCOPE_INTERNAL => Transfer::SCOPES[Transfer::SCOPE_INTERNAL]]
                        : Transfer::SCOPES)
                    ->default(Transfer::SCOPE_INTERNAL)
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText(fn (Get $get): ?string => $get('direction') === self::IN
                        ? 'Para fichar a alguien de fuera de la liga, pídeselo al administrador: hay que darle ficha primero.'
                        : null),

                Select::make('type')
                    ->label('Tipo')
                    ->options(fn (Get $get): array => self::typesFor((string) $get('direction'), (string) $get('scope')))
                    ->required()
                    ->native(false)
                    ->live(),

                Select::make('player_id')
                    ->label('Jugador')
                    ->options(fn (Get $get): array => $get('direction') === self::IN
                        ? self::playersElsewhere()
                        : self::ownPlayers())
                    ->required()
                    ->searchable()
                    ->helperText(fn (Get $get): string => $get('direction') === self::IN
                        ? 'De la plantilla de otro club en la temporada vigente.'
                        : 'De tu plantilla.'),

                Select::make('to_club_id')
                    ->label('Club que lo recibe')
                    ->relationship('toClub', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('direction') === self::OUT && $get('scope') === Transfer::SCOPE_INTERNAL)
                    ->required(fn (Get $get): bool => $get('direction') === self::OUT && $get('scope') === Transfer::SCOPE_INTERNAL),

                TextInput::make('external_club')
                    ->label('Club de fuera de la liga')
                    ->visible(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_EXTERNAL)
                    ->required(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_EXTERNAL)
                    ->helperText('Texto libre: la liga no lleva registro de los clubes de fuera.'),

                TextInput::make('fee')
                    ->label('Importe')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $get('type') !== Transfer::TYPE_LOAN)
                    ->helperText('Si se aprueba, sale de un presupuesto y entra en el otro.'),

                Select::make('loan_term')
                    ->label('Plazo del préstamo')
                    ->options(Transfer::LOAN_TERMS)
                    ->visible(fn (Get $get): bool => $get('type') === Transfer::TYPE_LOAN)
                    ->native(false)
                    ->helperText('Se anota como dato; el regreso lo registra el administrador.'),
            ]);
    }

    /**
     * Los tipos que tienen sentido según de qué lado esté el club del técnico.
     * Una venta entre clubes de la liga no está: esa operación es el fichaje
     * del comprador, una sola fila leída desde los dos lados (design D8).
     *
     * @return array<string, string>
     */
    public static function typesFor(string $direction, string $scope): array
    {
        $allowed = match (true) {
            $direction === self::IN => [Transfer::TYPE_SIGNING, Transfer::TYPE_LOAN],
            $scope === Transfer::SCOPE_INTERNAL => [Transfer::TYPE_LOAN],
            default => [Transfer::TYPE_SALE, Transfer::TYPE_LOAN],
        };

        return array_intersect_key(Transfer::TYPES, array_flip($allowed));
    }

    /**
     * @return array<int, string>
     */
    public static function playersElsewhere(): array
    {
        $season = app(SeasonResolver::class)->active();

        return self::label(
            Player::query()
                ->where('club_id', '!=', auth()->user()?->club_id)
                ->whereNull('left_at')
                ->when($season, fn (Builder $query) => $query->whereHas(
                    'memberships',
                    fn (Builder $memberships) => $memberships->whereHas(
                        'team',
                        fn (Builder $team) => $team->where('season_id', $season->getKey()),
                    ),
                ))
                ->with('club')
                ->get()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function ownPlayers(): array
    {
        return self::label(
            Player::query()
                ->where('club_id', auth()->user()?->club_id)
                ->whereNull('left_at')
                ->with('club')
                ->get()
        );
    }

    /**
     * @param  Collection<int, Player>  $players
     * @return array<int, string>
     */
    private static function label(Collection $players): array
    {
        return $players
            ->sortBy(fn (Player $player) => [$player->club?->name, $player->name])
            ->mapWithKeys(fn (Player $player) => [$player->id => "{$player->club?->short_name} · {$player->name}"])
            ->all();
    }
}
