<?php

namespace App\Filament\Resources\Teams;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\RelationManagers\PlayersRelationManager;
use App\Filament\Resources\Teams\RelationManagers\StadiumRelationManager;
use App\Filament\Resources\Teams\Schemas\TeamForm;
use App\Filament\Resources\Teams\Tables\TeamsTable;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use UnitEnum;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'League';

    /**
     * Shared by the table row DeleteAction and the Edit-page header
     * DeleteAction (design D4). No pre-check guard — the DB's
     * restrictOnDelete() on games.home_team_id/away_team_id still does the
     * rejecting; this only translates the resulting QueryException into a
     * friendly notification instead of letting it escape to Livewire raw.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->action(function (Team $record, DeleteAction $action): void {
                try {
                    $record->delete();
                } catch (QueryException) {
                    Notification::make()
                        ->danger()
                        ->title('Team cannot be deleted')
                        ->body('This team still has games. Delete or reassign them first.')
                        ->send();

                    $action->halt();
                }

                $action->success();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return TeamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StadiumRelationManager::class,
            PlayersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }
}
