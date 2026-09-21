<?php

namespace App\Filament\Resources\Offers;

use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Models\Offer;
use App\Services\OfferService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * La bandeja de ofertas del administrador (Fase 13, design D10).
 *
 * Una oferta aceptada por los dos técnicos no ha movido nada todavía: espera
 * aquí a que el administrador la firme. Firmarla registra un traspaso de los de
 * la Fase 12, y es ese traspaso —no la oferta— el que paga, cobra y mueve al
 * jugador. Una sola puerta de entrada al dinero y a las plantillas.
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
                TextColumn::make('player.club.name')->label('Current club'),
                TextColumn::make('fromClub.name')->label('Bidding club'),
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
            ])
            ->filters([
                SelectFilter::make('status')->options(Offer::STATUSES),
            ])
            ->recordActions([
                Action::make('execute')
                    ->label('Execute')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Offer $record): bool => $record->status === Offer::STATUS_ACCEPTED)
                    ->requiresConfirmation()
                    ->modalDescription('This records the transfer: the buyer is charged, the seller credited and the player moves squad.')
                    ->action(function (Offer $record): void {
                        try {
                            app(OfferService::class)->execute($record, auth()->user());
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title($exception->validator->errors()->first())->send();

                            return;
                        }

                        Notification::make()->success()->title('Transfer recorded')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
        ];
    }
}
