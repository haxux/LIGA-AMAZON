<?php

namespace App\Filament\Resources\Matchdays\RelationManagers;

use App\Filament\Resources\Games\Schemas\GameForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GamesRelationManager extends RelationManager
{
    protected static string $relationship = 'games';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(GameForm::teamAndScoreFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('homeTeam.name')->label('Home'),
                TextColumn::make('home_score')->label('H')->alignCenter(),
                TextColumn::make('away_score')->label('A')->alignCenter(),
                TextColumn::make('awayTeam.name')->label('Away'),
                TextColumn::make('kickoff_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
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
