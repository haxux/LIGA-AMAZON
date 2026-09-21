<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Models\Player;
use App\Models\Transfer;
use App\Services\SeasonResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                    ->required(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_INTERNAL || $get('type') === Transfer::TYPE_SALE)
                    ->visible(fn (Get $get): bool => $get('scope') === Transfer::SCOPE_INTERNAL || $get('type') === Transfer::TYPE_SALE),
                Select::make('to_club_id')
                    ->label('Buying club')
                    ->relationship('toClub', 'name')
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
                    ->visible(fn (Get $get): bool => $get('type') !== Transfer::TYPE_LOAN)
                    ->helperText('Plain figure. It becomes one movement in each budget, already approved.'),
                Select::make('loan_term')
                    ->label('Loan term')
                    ->options(Transfer::LOAN_TERMS)
                    ->visible(fn (Get $get): bool => $get('type') === Transfer::TYPE_LOAN)
                    ->native(false)
                    // Se anota como dato y no vence solo: este despliegue no
                    // tiene tareas programadas (decisión cerrada).
                    ->helperText('Recorded as data; the return is made by hand.'),
            ]);
    }
}
