<?php

namespace App\Filament\Resources\Games\Tables;

use App\Filament\Support\TeamName;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('matchday_id')
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('matchday.number')->label('Matchday')->sortable(),
                TextColumn::make('homeTeam.name')
                    ->label('Home')
                    // Por el club: `teams` no tiene nombre desde la Fase 9.
                    ->searchable(query: TeamName::search('homeTeam'))
                    ->sortable(query: TeamName::sort('home_team_id')),
                TextColumn::make('home_score')->label('H')->alignCenter(),
                TextColumn::make('away_score')->label('A')->alignCenter(),
                TextColumn::make('awayTeam.name')
                    ->label('Away')
                    ->searchable(query: TeamName::search('awayTeam'))
                    ->sortable(query: TeamName::sort('away_team_id')),
                TextColumn::make('kickoff_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('matchday')->relationship('matchday', 'number'),
                SelectFilter::make('season')->relationship('matchday.season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
