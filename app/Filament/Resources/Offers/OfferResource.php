<?php

namespace App\Filament\Resources\Offers;

use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Models\Club;
use App\Models\Offer;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * La bandeja de ofertas del administrador (Fase 13, design D10).
 *
 * Es una pantalla de MIRAR, no de hacer: aquí se ve lo que los clubes negocian
 * entre ellos y en qué quedó cada cosa, y nada más.
 *
 * Al aceptarla los dos técnicos, la oferta deja escrita una propuesta de
 * traspaso, y es en Transfers donde el administrador la firma. Ese traspaso
 * —nunca la oferta— es el que paga, cobra y mueve al jugador: una sola puerta
 * de entrada al dinero y a las plantillas.
 *
 * Hasta el 2026-10-13 esta pantalla tenía un botón de ejecutar, y había dos
 * puertas. La de aquí construía mal el traspaso en las ofertas de venta —ver
 * `Offer::receivingClubId()`— y lo registró de un club a sí mismo sin que nada
 * avisara.
 */
class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $modelLabel = 'offer';

    public static function canCreate(): bool
    {
        // Las ofertas nacen en el chat, entre técnicos. Aquí sólo se firman.
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Offer::query()->where('status', Offer::STATUS_ACCEPTED)->count();

        return $pending === 0 ? null : (string) $pending;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->date('d/m/Y')->sortable(),
                TextColumn::make('player.name')->label('Player')->searchable(),
                // Qué operación es, que no se veía: una pantalla de mirar que no
                // dice si es compra, venta o cesión no sirve para mirar nada.
                TextColumn::make('kind')
                    ->label('Operation')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => Offer::KINDS_SHORT[$state] ?? $state),
                // La dirección, que es lo que de verdad se viene a mirar y lo
                // que se leía mal: el dueño del jugador lo suelta y el otro del
                // hilo lo recibe.
                TextColumn::make('direction')
                    ->label('Moves')
                    ->state(function (Offer $record): string {
                        $clubs = Club::query()
                            ->whereIn('id', array_filter([$record->givingClubId(), $record->receivingClubId()]))
                            ->pluck('name', 'id');

                        return ($clubs[$record->givingClubId()] ?? '—').' → '.($clubs[$record->receivingClubId()] ?? '—');
                    }),
                TextColumn::make('amount')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Offer::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Offer::STATUS_ACCEPTED => 'success',
                        Offer::STATUS_REJECTED => 'danger',
                        Offer::STATUS_EXECUTED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('mover.name')->label('Last moved by')->placeholder('—')->toggleable(),
                // En qué quedó el trato, leído del traspaso que generó y no
                // copiado aquí: si la oferta guardara su propia versión, las
                // dos podrían discrepar y nadie sabría cuál creer.
                TextColumn::make('transfer.status')
                    ->label('Transfer')
                    ->placeholder('—')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transfer::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Transfer::STATUS_EXECUTED => 'success',
                        Transfer::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Offer::STATUSES),
            ])
            // Sin acciones: aquí NO se ejecuta nada (decisión del propietario,
            // 2026-10-13). Esta pantalla es para mirar lo que los clubes
            // negocian entre ellos; lo acordado aparece solo en Transfers como
            // propuesta, y es allí donde se firma.
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
        ];
    }
}
