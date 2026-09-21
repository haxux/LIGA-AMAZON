<?php

namespace App\Filament\Resources\Cups\RelationManagers;

use App\Filament\Support\TeamOptions;
use App\Models\Cup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Quiénes juegan la copa: equipos de CUALQUIER división de esa temporada, que
 * es justamente lo que una división no sabe hacer.
 */
class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $title = 'Teams';

    public function form(Schema $schema): Schema
    {
        /** @var Cup $cup */
        $cup = $this->getOwnerRecord();

        return $schema->components([
            Select::make('team_id')
                ->label('Team')
                ->options(fn (): array => TeamOptions::for(
                    fn (Builder $query) => $query->where('season_id', $cup->season_id),
                ))
                ->required()
                ->searchable()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('cup_id', $cup->getKey()),
                ),
            Select::make('cup_group_id')
                ->label('Group')
                ->relationship('group', 'name', fn (Builder $query) => $query->where('cup_id', $cup->getKey()))
                ->visible((bool) $cup->has_group_stage)
                ->searchable()
                ->preload(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('team_id')
            ->columns([
                TextColumn::make('team.name')->label('Team')->searchable()->sortable(),
                TextColumn::make('team.division.name')->label('Division')->placeholder('—'),
                TextColumn::make('group.name')->label('Group')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Add team')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
