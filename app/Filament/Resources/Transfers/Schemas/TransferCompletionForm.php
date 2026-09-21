<?php

namespace App\Filament\Resources\Transfers\Schemas;

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
        return [
            TextInput::make('player_name')
                ->label('Player')
                ->default($transfer->external_player)
                ->required(),
            Select::make('position')
                ->label('Position')
                ->options(array_combine(Player::POSITIONS, Player::POSITIONS))
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('specific_position', null)),
            Select::make('specific_position')
                ->label('Specific position')
                ->options(fn (Get $get): array => array_combine(
                    Player::specificPositionsFor($get('position')),
                    Player::specificPositionsFor($get('position')),
                ))
                ->native(false),
            DatePicker::make('birth_date')->label('Born'),
            TextInput::make('market_value')->label('Value')->numeric()->minValue(0),
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
                ->required(),
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
