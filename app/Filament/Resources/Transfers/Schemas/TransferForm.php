<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Models\Player;
use App\Models\Transfer;
use App\Services\SeasonResolver;
use App\Services\TransferService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registrar un traspaso lo EJECUTA: mueve al jugador y cuadra los dos
 * presupuestos. De ahí que el formulario insista tanto en qué club está a cada
 * lado — no es papeleo, es lo que decide adónde va el dinero.
 */
class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('player_id')
                    ->label('Player')
                    ->required()
                    ->searchable()
                    ->live()
                    // Al elegir jugador, el club que vende se pone solo: es el
                    // suyo, y tecleándolo a mano es donde se equivoca uno.
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $player = Player::query()->find($state);

                        $set('from_club_id', $player?->club_id);

                        // El importe arranca en el valor de mercado del jugador;
                        // se puede cambiar, es sólo el punto de partida.
                        if ($player?->market_value !== null) {
                            $set('fee', $player->market_value);
                        }
                    })
                    ->options(fn (): array => Player::query()
                        ->with('club')
                        ->get()
                        ->sortBy(fn (Player $player) => [$player->club?->name, $player->name])
                        ->mapWithKeys(fn (Player $player) => [$player->id => "{$player->club?->short_name} · {$player->name}"])
                        ->all()),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->label('Season')
                    ->required()
                    // La temporada vigente por omisión: un traspaso se registra
                    // cuando ocurre, y la propuesta pedía poder filtrarlos por ella.
                    ->default(fn (): ?int => app(SeasonResolver::class)->active()?->getKey())
                    ->searchable()
                    ->preload(),
                Select::make('scope')
                    ->label('Scope')
                    ->options(Transfer::SCOPES)
                    ->default(Transfer::SCOPE_INTERNAL)
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('type')
                    ->label('Type')
                    ->options(fn (Get $get): array => $get('scope') === Transfer::SCOPE_INTERNAL
                        // Una venta entre clubes de la liga es el fichaje del
                        // comprador: una sola fila, leída desde los dos lados.
                        ? array_diff_key(Transfer::TYPES, [Transfer::TYPE_SALE => null])
                        : Transfer::TYPES)
                    ->default(Transfer::TYPE_SIGNING)
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('from_club_id')
                    ->label('Selling club')
                    ->relationship('fromClub', 'name')
                    ->searchable()
                    ->preload()
                    // En vivo para que el club comprador se vuelva a filtrar al
                    // cambiarlo, y no se quede ofreciendo el que acaba de salir.
                    ->live()
                    ->required(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_INTERNAL || $get('type') === Transfer::TYPE_SALE)
                    ->visible(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_INTERNAL || $get('type') === Transfer::TYPE_SALE),
                Select::make('to_club_id')
                    ->label('Buying club')
                    // Sin el club que vende entre las opciones: un club no se
                    // ficha a sí mismo, y elegirlo por error registraba una
                    // operación que no movía al jugador a ninguna parte y le
                    // cambiaba el dorsal por el camino.
                    ->relationship(
                        'toClub',
                        'name',
                        fn (Builder $query, Get $get) => $query->when(
                            $get('from_club_id'),
                            fn (Builder $clubs, $sells) => $clubs->whereKeyNot($sells),
                        ),
                    )
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('type') !== Transfer::TYPE_SALE)
                    ->visible(fn (Get $get): bool => $get('type') !== Transfer::TYPE_SALE),
                TextInput::make('external_club')
                    ->label('Club outside the league')
                    ->required(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_EXTERNAL)
                    ->visible(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_EXTERNAL)
                    // Texto libre por decisión del propietario: la liga no lleva
                    // registro de los clubes de fuera.
                    ->helperText('Free text; clubs outside the league are not records here.'),
                TextInput::make('fee')
                    ->label('Fee')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => ! in_array($get('type'), Transfer::FREE, true))
                    ->helperText('Plain figure. It becomes one movement in each budget, already approved.'),
                // El dorsal con el que llega. No existía, y el jugador aterrizaba
                // con el primer número libre sin que nadie lo eligiera ni se
                // enterara (lo reportó el propietario el 2026-10-13). Vacío
                // sigue valiendo: entonces se asigna el primero libre, como
                // antes, pero porque se ha decidido y no por omisión.
                TextInput::make('shirt_number')
                    ->label('Shirt at the new club')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->visible(fn (Get $get): bool => in_array($get('type'), Transfer::INCOMING, true))
                    ->helperText(fn (Get $get): string => self::shirtHelp($get))
                    ->rules([
                        fn (Get $get): callable => function (string $attribute, $value, callable $fail) use ($get): void {
                            $team = app(TransferService::class)->teamFor($get('to_club_id'), $get('season_id'));

                            if ($team !== null && filled($value) && app(TransferService::class)->shirtIsTaken($team, (int) $value)) {
                                $fail("Shirt {$value} is already taken in that squad.");
                            }
                        },
                    ]),
                Select::make('loan_term')
                    ->label('Loan term')
                    ->options(Transfer::LOAN_TERMS)
                    ->visible(fn (Get $get): bool => in_array($get('type'), Transfer::FREE, true))
                    ->native(false)
                    // Se anota como dato y no vence solo: este despliegue no
                    // tiene tareas programadas (decisión cerrada).
                    ->helperText('Recorded as data; the return is made by hand.'),
            ]);
    }

    /**
     * Los dorsales ya cogidos en la plantilla de destino, dichos de corrido: es
     * más rápido leerlos que probar de uno en uno.
     */
    private static function shirtHelp(Get $get): string
    {
        $team = app(TransferService::class)->teamFor($get('to_club_id'), $get('season_id'));

        if ($team === null) {
            return 'Empty means the first free number.';
        }

        $taken = $team->memberships()->orderBy('shirt_number')->pluck('shirt_number')->implode(', ');

        return $taken === ''
            ? 'Empty means the first free number.'
            : 'Taken in that squad: '.$taken;
    }
}
