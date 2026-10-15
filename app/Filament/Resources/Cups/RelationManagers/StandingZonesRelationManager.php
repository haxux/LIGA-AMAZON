<?php

namespace App\Filament\Resources\Cups\RelationManagers;

use App\Filament\Resources\Divisions\RelationManagers\StandingZonesRelationManager as DivisionZones;
use Illuminate\Database\Eloquent\Model;

/**
 * Las zonas de la tabla de cada grupo: las mismas que las de una división, pero
 * colgando de la copa. Se definen una vez y valen para todos los grupos.
 */
class StandingZonesRelationManager extends DivisionZones
{
    protected static string $relationship = 'standingZones';

    protected static string $ownerColumn = 'cup_id';

    protected static string $stage = 'groups';

    protected static ?string $title = 'Group standings zones';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->has_group_stage;
    }
}
