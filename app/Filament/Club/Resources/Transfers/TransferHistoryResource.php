<?php

namespace App\Filament\Club\Resources\Transfers;

use App\Filament\Club\Resources\Transfers\Pages\ListTransferHistory;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * El historial de fichajes del club: lo que ha entrado y salido.
 *
 * Es sólo de lectura. Todo se negocia por el chat —con otro técnico o con el
 * presidente— y lo acordado llega aquí como traspaso cuando el administrador lo
 * firma; desde el panel del técnico ya no se propone nada.
 *
 * El mismo traspaso se lee distinto según el lado: fichaje para quien recibe,
 * venta para quien cede. Es una fila leída desde los dos lados (design D8).
 */
class TransferHistoryResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Fichajes';

    protected static ?string $slug = 'fichajes';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'fichajes';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $clubId = auth()->user()?->club_id;

        return parent::getEloquentQuery()
            ->with(['player', 'season', 'fromClub', 'toClub'])
            ->where(fn (Builder $query) => $query
                ->where('from_club_id', $clubId)
                ->orWhere('to_club_id', $clubId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transfer::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Transfer::STATUS_EXECUTED => 'success',
                        Transfer::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('season.name')->label('Temporada')->sortable(),
                TextColumn::make('player.name')->label('Jugador')->searchable(),
                TextColumn::make('operacion')
                    ->label('Operación')
                    ->badge()
                    ->state(fn (Transfer $record): string => $record->labelFor(auth()->user()?->club))
                    ->color(fn (string $state): string => match ($state) {
                        'Fichaje' => 'success',
                        'Venta' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('contraparte')
                    ->label('Con')
                    ->state(fn (Transfer $record): ?string => $record->counterpartFor(auth()->user()?->club))
                    ->placeholder('—'),
                TextColumn::make('fee')->label('Importe')->numeric(thousandsSeparator: '.'),
                TextColumn::make('loan_term')
                    ->label('Plazo')
                    ->formatStateUsing(fn (?string $state): string => Transfer::LOAN_TERMS[$state] ?? '—')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('season')->label('Temporada')->relationship('season', 'name'),
                SelectFilter::make('type')->label('Tipo')->options(Transfer::TYPES),
                SelectFilter::make('status')->label('Estado')->options(Transfer::STATUSES),
            ])
            ->emptyStateHeading('Todavía sin movimientos')
            ->emptyStateDescription('Los fichajes se negocian por el chat; aquí quedan registrados cuando se cierran.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferHistory::route('/'),
        ];
    }
}
