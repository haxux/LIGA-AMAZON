<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Models\Transfer;
use App\Services\TransferService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Recorded')->date('d/m/Y')->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transfer::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Transfer::STATUS_EXECUTED => 'success',
                        Transfer::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('player.name')->label('Player')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transfer::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Transfer::TYPE_SIGNING => 'success',
                        Transfer::TYPE_SALE => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('fromClub.name')->label('From')->placeholder(fn (Transfer $record): string => $record->external_club ?? '—'),
                TextColumn::make('toClub.name')->label('To')->placeholder(fn (Transfer $record): string => $record->external_club ?? '—'),
                TextColumn::make('fee')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('proposer.name')->label('Proposed by')->placeholder('—')->toggleable(),
                TextColumn::make('loan_term')
                    ->label('Term')
                    ->formatStateUsing(fn (?string $state): string => Transfer::LOAN_TERMS[$state] ?? '—')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
                SelectFilter::make('status')->options(Transfer::STATUSES),
                SelectFilter::make('type')->options(Transfer::TYPES),
                SelectFilter::make('scope')->options(Transfer::SCOPES),
                SelectFilter::make('fromClub')->relationship('fromClub', 'name')->label('Selling club'),
                SelectFilter::make('toClub')->relationship('toClub', 'name')->label('Buying club'),
            ])
            ->recordActions([
                // Firmar lo que propuso un técnico es un acto, no la edición de
                // un campo: al aprobarlo se ejecuta el traspaso entero.
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Transfer $record): bool => $record->isProposal())
                    ->requiresConfirmation()
                    ->modalDescription('This executes the transfer: the buyer is charged, the seller credited and the player moves squad.')
                    ->action(function (Transfer $record): void {
                        try {
                            app(TransferService::class)->approve($record, auth()->user());
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title($exception->validator->errors()->first())->send();

                            return;
                        }

                        Notification::make()->success()->title('Transfer executed')->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Transfer $record): bool => $record->isProposal())
                    ->requiresConfirmation()
                    ->action(fn (Transfer $record) => app(TransferService::class)->reject($record, auth()->user())),
                // Sin edición a propósito: un traspaso se ejecuta al crearse, y
                // editarlo después movería el dinero por segunda vez o no lo
                // movería en absoluto. Corregir uno es borrarlo y registrarlo
                // otra vez.
                DeleteAction::make()
                    ->modalDescription('Deleting it removes the two budget movements it created. It does NOT move the player back: that is done from the squad.'),
            ]);
    }
}
