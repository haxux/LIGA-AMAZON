<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Models\Player;
use App\Models\SquadMembership;
use App\Services\TransferService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

/**
 * La plantilla de ESTA temporada. La identidad de cada jugador vive en el club
 * (`ClubResource`); aquí se decide quién la compone, con qué dorsal y a qué
 * título — en propiedad o cedido.
 */
class SquadRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Squad';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('player_id')
                    ->relationship('player', 'name')
                    ->label('Player')
                    ->required()
                    ->searchable()
                    ->preload()
                    // Por defecto, los jugadores del propio club; una cesión se
                    // registra eligiendo a uno de otro club, que es justo lo que
                    // distingue al tipo `loan`.
                    ->options(fn (): array => Player::query()
                        ->with('club')
                        ->get()
                        ->sortBy(fn (Player $player) => [$player->club?->name, $player->name])
                        ->mapWithKeys(fn (Player $player) => [$player->id => "{$player->club?->short_name} · {$player->name}"])
                        ->all())
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('team_id', $this->getOwnerRecord()->getKey()),
                    ),
                TextInput::make('shirt_number')
                    ->label('Shirt')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(99)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('team_id', $this->getOwnerRecord()->getKey()),
                    ),
                Select::make('type')
                    ->label('Title')
                    ->options(SquadMembership::TYPES)
                    ->default(SquadMembership::TYPE_OWNED)
                    ->required()
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('shirt_number')
            ->columns([
                TextColumn::make('shirt_number')->label('#')->sortable(),
                TextColumn::make('player.name')->label('Player')->searchable(),
                TextColumn::make('player.position')->label('Position')->badge(),
                TextColumn::make('player.club.name')->label('Owned by')->toggleable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SquadMembership::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === SquadMembership::TYPE_LOAN ? 'warning' : 'gray'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['player.club']))
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // Fin de cesión: lo devuelve a la plantilla de su dueño de un
                // gesto. Antes había que saberse el truco de registrar la
                // cesión al revés, y quien no lo supiera tiraba de borrar.
                Action::make('endLoan')
                    ->label('Fin de cesión')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (SquadMembership $record): bool => $record->type === SquadMembership::TYPE_LOAN)
                    ->requiresConfirmation()
                    ->modalDescription('Sale de esta plantilla y vuelve a la de su club. Lo que hizo aquí se conserva.')
                    ->action(function (SquadMembership $record): void {
                        $home = app(TransferService::class)->endLoan($record);

                        Notification::make()
                            ->success()
                            ->title($home === null
                                ? 'Cesión cerrada; su club no está inscrito esta temporada'
                                : 'Vuelve a '.$home->name)
                            ->send();
                    }),
                // Dar de BAJA, no borrar. Borrar la pertenencia destruía la
                // única prueba de que ese jugador estuvo en esta plantilla, y
                // con ella la posibilidad de anotarle después un evento de un
                // partido que jugó aquí. Era la última puerta por la que se
                // perdía esa historia.
                Action::make('release')
                    ->label('Dar de baja')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Sale de la plantilla y suelta su dorsal. Sigue constando que estuvo, para poder anotarle eventos de los partidos que jugó.')
                    ->action(fn (SquadMembership $record) => $record->close()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('release')
                        ->label('Dar de baja')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Salen de la plantilla y sueltan su dorsal. Sigue constando que estuvieron.')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each(fn (SquadMembership $m) => $m->close())),
                ]),
            ]);
    }
}
