<?php

namespace App\Filament\Club\Resources\Transfers;

use App\Filament\Club\Resources\Transfers\Pages\ListTransferHistory;
use App\Filament\Club\Resources\Transfers\Pages\ProposeTransfer;
use App\Filament\Club\Resources\Transfers\Schemas\TransferProposalForm;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los fichajes del club: lo que ha entrado y salido, y lo que su técnico ha
 * propuesto.
 *
 * El técnico PROPONE la operación entera —el mismo jugador, el mismo importe y
 * el mismo ámbito que registraría el administrador— y hasta que la firmen no
 * mueve ni plantilla ni dinero. Ejecutar sigue siendo del administrador, porque
 * ejecutar es lo que paga, cobra y cambia de plantilla a un jugador.
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
        // Proponer sí; ejecutar no. Lo que se crea aquí nace en estado
        // propuesto (ver ProposeTransfer).
        return auth()->user()?->isCoach() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return TransferProposalForm::configure($schema);
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
            ->emptyStateDescription('Propón un fichaje y el administrador lo firmará.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferHistory::route('/'),
            'create' => ProposeTransfer::route('/proponer'),
        ];
    }
}
