<?php

namespace App\Filament\Club\Resources\Transfers\Schemas;

use App\Models\Player;
use App\Models\Transfer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * Lo que un técnico propone a la dirección, y siempre hacia FUERA de la liga:
 * lo que se mueve entre clubes de la liga se negocia por el chat, con el otro
 * técnico, y de ahí que aquí no haya que preguntar por el ámbito.
 *
 * Cuatro operaciones, y cada una pregunta lo suyo:
 *
 * - **Fichaje**: traer a alguien de fuera. Su nombre, su club y el importe.
 * - **Venta**: poner a uno de los suyos en el escaparate, con precio.
 * - **Cesión**: traer a alguien de fuera sin coste, con plazo.
 * - **Ceder**: ofrecer a uno de los suyos en cesión, con plazo.
 *
 * Lo que entra nombra al jugador en texto libre porque todavía no tiene ficha:
 * la crea el administrador al aceptar y completar la operación.
 */
class TransferProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Operación')
                    ->options(Transfer::TYPES)
                    ->default(Transfer::TYPE_SIGNING)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('player_id', null);
                        $set('external_player', null);
                        $set('external_club', null);
                        $set('fee', null);
                        $set('loan_term', null);
                    })
                    ->helperText(fn (Get $get): string => self::explain((string) $get('type'))),

                // ── Lo que entra: un jugador de fuera, por su nombre ────────
                TextInput::make('external_player')
                    ->label('Jugador')
                    ->required(fn (Get $get): bool => self::incoming($get))
                    ->visible(fn (Get $get): bool => self::incoming($get))
                    ->maxLength(255)
                    ->helperText('Todavía no tiene ficha en la liga: la crea el administrador si la operación sale.'),

                // ── Lo que sale: uno de los suyos ──────────────────────────
                Select::make('player_id')
                    ->label('Jugador')
                    ->options(fn (): array => self::ownPlayers())
                    ->required(fn (Get $get): bool => ! self::incoming($get))
                    ->visible(fn (Get $get): bool => ! self::incoming($get))
                    ->searchable()
                    ->helperText('De tu plantilla.'),

                TextInput::make('external_club')
                    ->label(fn (Get $get): string => self::incoming($get) ? 'Club del que viene' : 'Club al que iría')
                    ->required(fn (Get $get): bool => self::incoming($get))
                    ->maxLength(255)
                    ->helperText(fn (Get $get): string => self::incoming($get)
                        ? 'Texto libre: la liga no lleva registro de los clubes de fuera.'
                        : 'Si ya sabes cuál; si no, déjalo vacío y lo busca la dirección.'),

                TextInput::make('fee')
                    ->label(fn (Get $get): string => $get('type') === Transfer::TYPE_SALE ? 'Precio que pides' : 'Importe que ofreces')
                    ->numeric()
                    ->minValue(1)
                    // Obligatorio en fichaje y venta: una operación con dinero
                    // sin cifra no es una propuesta, es una pregunta.
                    ->required(fn (Get $get): bool => in_array($get('type'), [Transfer::TYPE_SIGNING, Transfer::TYPE_SALE], true))
                    ->visible(fn (Get $get): bool => in_array($get('type'), [Transfer::TYPE_SIGNING, Transfer::TYPE_SALE], true))
                    ->helperText('Cifra entera, sin símbolo de moneda.'),

                Select::make('loan_term')
                    ->label('Plazo de la cesión')
                    ->options(Transfer::LOAN_TERMS)
                    ->required(fn (Get $get): bool => in_array($get('type'), Transfer::FREE, true))
                    ->visible(fn (Get $get): bool => in_array($get('type'), Transfer::FREE, true))
                    ->native(false)
                    ->helperText('Una cesión no tiene coste; lo que se pacta es el plazo.'),
            ]);
    }

    /**
     * Si la operación elegida trae a alguien al club.
     */
    public static function incoming(Get $get): bool
    {
        return in_array($get('type'), Transfer::INCOMING, true);
    }

    public static function explain(string $type): string
    {
        return match ($type) {
            Transfer::TYPE_SIGNING => 'Pides a la dirección que compre a alguien de fuera de la liga. Entre clubes de la liga se negocia por el chat.',
            Transfer::TYPE_SALE => 'Pones a uno de los tuyos en venta. La dirección busca comprador y te cuenta lo que le llegue.',
            Transfer::TYPE_LOAN_IN => 'Pides traer cedido a alguien de fuera, sin coste y con un plazo.',
            Transfer::TYPE_LOAN_OUT => 'Ofreces a uno de los tuyos en cesión, sin coste y con un plazo.',
            default => '',
        };
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
            ->sortBy(fn (Player $player) => $player->name)
            ->mapWithKeys(fn (Player $player) => [$player->id => $player->name])
            ->all();
    }
}
