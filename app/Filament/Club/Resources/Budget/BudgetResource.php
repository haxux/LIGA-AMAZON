<?php

namespace App\Filament\Club\Resources\Budget;

use App\Filament\Club\Resources\Budget\Pages\CreateBudgetProposal;
use App\Filament\Club\Resources\Budget\Pages\ListBudget;
use App\Models\BudgetMovement;
use App\Services\SeasonResolver;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La contabilidad del club en el panel del técnico (Fase 12).
 *
 * El técnico PROPONE y el administrador aprueba (decisión cerrada): lo que se
 * crea aquí nace en estado propuesto y no toca el saldo hasta que alguien al
 * otro lado dice que sí. Por eso tampoco hay edición ni borrado: una propuesta
 * ya respondida es un hecho del libro, no un borrador.
 */
class BudgetResource extends Resource
{
    protected static ?string $model = BudgetMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Contabilidad';

    protected static ?string $slug = 'contabilidad';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'movimientos';

    protected static ?int $navigationSort = 5;

    /**
     * Segunda cerradura, además de las policies: el libro del club propio y de
     * ningún otro, aunque se teclee la URL.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('club_id', auth()->user()?->club_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options(BudgetMovement::TYPES)
                    ->required()
                    ->native(false),
                TextInput::make('amount')
                    ->label('Importe')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Cifra entera, sin símbolo de moneda.'),
                TextInput::make('reason')
                    ->label('Razón')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Lo que el administrador va a leer para decidir.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('season.name')->label('Temporada')->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === BudgetMovement::TYPE_INCOME ? 'success' : 'danger'),
                TextColumn::make('amount')->label('Importe')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('reason')->label('Razón')->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BudgetMovement::STATUS_APPROVED => 'success',
                        BudgetMovement::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('season')->label('Temporada')->relationship('season', 'name'),
                SelectFilter::make('status')->label('Estado')->options(BudgetMovement::STATUSES),
            ])
            ->emptyStateHeading('Todavía sin movimientos')
            ->emptyStateDescription('Propón un ingreso o un egreso y el administrador lo aprobará.');
    }

    /**
     * Una propuesta sin temporada no se puede filtrar después, así que se le
     * pone la vigente al crearla — como pidió la propuesta de la fase.
     */
    public static function currentSeasonId(): ?int
    {
        return app(SeasonResolver::class)->active()?->getKey();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudget::route('/'),
            'create' => CreateBudgetProposal::route('/create'),
        ];
    }
}
