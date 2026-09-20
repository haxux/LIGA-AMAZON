<?php

namespace App\Filament\Resources\Divisions;

use App\Filament\Resources\Divisions\Pages\CreateDivision;
use App\Filament\Resources\Divisions\Pages\EditDivision;
use App\Filament\Resources\Divisions\Pages\ListDivisions;
use App\Filament\Resources\Divisions\RelationManagers\StandingZonesRelationManager;
use App\Filament\Resources\Divisions\Schemas\DivisionForm;
use App\Filament\Resources\Divisions\Tables\DivisionsTable;
use App\Models\Division;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use UnitEnum;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'League';

    /**
     * Shared by the table row DeleteAction and the Edit-page header
     * DeleteAction, mirroring TeamResource::deleteAction(). No pre-check
     * guard — the DB's restrictOnDelete() on teams.division_id still does
     * the rejecting; this only translates the resulting QueryException into
     * a friendly notification instead of letting it escape to Livewire raw.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->action(function (Division $record, DeleteAction $action): void {
                try {
                    $record->delete();
                } catch (QueryException) {
                    Notification::make()
                        ->danger()
                        ->title('Division cannot be deleted')
                        ->body('This division still has teams. Reassign them first.')
                        ->send();

                    $action->halt();
                }

                $action->success();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return DivisionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DivisionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StandingZonesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDivisions::route('/'),
            'create' => CreateDivision::route('/create'),
            'edit' => EditDivision::route('/{record}/edit'),
        ];
    }
}
