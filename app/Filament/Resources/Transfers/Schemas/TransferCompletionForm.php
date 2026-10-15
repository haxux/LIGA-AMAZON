<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Filament\Support\FcLookup;
use App\Models\FcPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\Transfer;
use App\Services\TransferService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Lo que el administrador completa al aceptar una propuesta.
 *
 * Aceptar no es decir «sí»: es el momento de poner lo que la propuesta no podía
 * saber —a qué club se fue el jugador, por cuánto se cerró, y quién es el que
 * llega, que hasta ahora era sólo un nombre—. Al guardar, se mueve todo de una
 * vez: ficha, plantilla y los dos presupuestos.
 *
 * @return array<int, Component>
 */
class TransferCompletionForm
{
    /**
     * @return array<int, mixed>
     */
    public static function for(Transfer $transfer): array
    {
        return array_merge(
            $transfer->isIncoming() && $transfer->player_id === null ? self::newPlayerFields($transfer) : [],
            // El dorsal se pide SIEMPRE que el jugador aterrice en una
            // plantilla, no sólo cuando además hay que crearle la ficha. Un
            // traspaso entre dos clubes de la liga mueve a alguien que ya
            // existe, y hasta hoy ahí no se preguntaba: el jugador llegaba con
            // el primer número libre sin que nadie lo decidiera.
            $transfer->isIncoming() ? self::shirtField($transfer) : [],
            self::termsFields($transfer),
        );
    }

    /**
     * La ficha del que llega. La posición es obligatoria porque la página
     * pública agrupa la plantilla por ella; el resto puede completarse después.
     *
     * @return array<int, mixed>
     */
    private static function newPlayerFields(Transfer $transfer): array
    {
        $ficha = $transfer->fc_id === null ? null : FcPlayer::where('fc_id', $transfer->fc_id)->first();

        return [
            // El que llega de fuera de la liga se daba de alta tecleándolo
            // entero, y mientras tanto el catálogo de EA FC ya rellenaba fichas
            // en Jugadores. Es el mismo buscador, literalmente la misma clase:
            // si el fichaje y el alta a mano crearan al jugador de dos maneras
            // distintas, el mismo futbolista entraría distinto según por dónde
            // se le diera de alta.
            //
            // El nombre que traía la propuesta queda de respaldo: si nadie toca
            // el buscador, el alta funciona exactamente como antes.
            // No toca el club de procedencia, aunque el catálogo lo sepa: en
            // una propuesta entrante es obligatorio —lo exige el invariante de
            // Transfer—, así que ya viene puesto, y lo pactado lo dice el
            // técnico que propuso la operación, no EA.
            FcLookup::make('player_name'),
            FcLookup::idField()->default($transfer->fc_id),

            TextInput::make('player_name')
                ->label('Player')
                ->default($transfer->external_player)
                ->required(),
            // Si el jugador viene del catálogo (lo pidió un técnico al
            // presidente), la ficha llega rellena; sigue siendo editable.
            Select::make('position')
                ->label('Position')
                ->default($ficha?->posicionEnLaLiga()[0])
                ->options(array_combine(Player::POSITIONS, Player::POSITIONS))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('specific_position', null)),
            Select::make('specific_position')
                ->label('Specific position')
                ->default($ficha?->posicionEnLaLiga()[1])
                ->options(fn (Get $get): array => array_combine(
                    Player::specificPositionsFor($get('position')),
                    Player::specificPositionsFor($get('position')),
                ))
                ->native(false),
            DatePicker::make('birth_date')->label('Born')->default($ficha?->birthdate),
            TextInput::make('market_value')->label('Value')->numeric()->minValue(0),
            FcLookup::photoField(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function shirtField(Transfer $transfer): array
    {
        return [
            TextInput::make('shirt_number')
                ->label('Shirt')
                ->numeric()
                ->minValue(1)
                ->maxValue(99)
                // Ya puesto en el primero libre: el caso corriente no necesita
                // pensarlo, y el que quiera otro lo cambia.
                ->default(fn (): ?int => self::firstFree($transfer))
                ->helperText(fn (): string => self::taken($transfer) === ''
                    ? 'Empty means the first free number.'
                    : 'Taken in that squad: '.self::taken($transfer))
                ->rules([
                    fn (): callable => function (string $attribute, $value, callable $fail) use ($transfer): void {
                        $team = app(TransferService::class)->receivingTeam($transfer);

                        if ($team !== null && filled($value) && app(TransferService::class)->shirtIsTaken($team, (int) $value)) {
                            $fail("Shirt {$value} is already taken in that squad.");
                        }
                    },
                ]),
        ];
    }

    private static function firstFree(Transfer $transfer): ?int
    {
        $team = app(TransferService::class)->receivingTeam($transfer);

        return $team === null ? null : app(TransferService::class)->firstFreeShirtNumber($team);
    }

    /**
     * Los dorsales ya cogidos, dichos de corrido: es más rápido leerlos que
     * probar de uno en uno.
     */
    private static function taken(Transfer $transfer): string
    {
        $team = app(TransferService::class)->receivingTeam($transfer);

        if (! $team instanceof Team) {
            return '';
        }

        return $team->memberships()->orderBy('shirt_number')->pluck('shirt_number')->implode(', ');
    }

    /**
     * Lo pactado: el club del otro lado y el precio o el plazo.
     *
     * @return array<int, mixed>
     */
    private static function termsFields(Transfer $transfer): array
    {
        $fields = [
            TextInput::make('external_club')
                ->label($transfer->isIncoming() ? 'Club they come from' : 'Club they go to')
                ->default($transfer->external_club)
                // Una salida pactada con el presidente no va a ningún club
                // concreto: el jugador sólo deja la plantilla.
                ->required($transfer->isIncoming() || $transfer->external_club !== null),
        ];

        if ($transfer->isLoan()) {
            $fields[] = Select::make('loan_term')
                ->label('Loan term')
                ->options(Transfer::LOAN_TERMS)
                ->default($transfer->loan_term)
                ->required()
                ->native(false);

            return $fields;
        }

        $fields[] = TextInput::make('fee')
            ->label('Fee agreed')
            ->numeric()
            ->minValue(0)
            ->default($transfer->fee)
            ->required()
            ->helperText('What was actually agreed, which need not be what was asked.');

        return $fields;
    }
}
