<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Models\Player;
use App\Models\SquadMembership;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
