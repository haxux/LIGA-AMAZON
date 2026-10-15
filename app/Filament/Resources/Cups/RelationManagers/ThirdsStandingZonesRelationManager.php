<?php

namespace App\Filament\Resources\Cups\RelationManagers;

use Illuminate\Database\Eloquent\Model;

/**
 * Las zonas de la tabla de los mejores terceros: «pasan los 4 mejores», «el
 * resto, eliminado». Sus posiciones cuentan sobre esa tabla, no sobre la de un
 * grupo, y por eso son otro gestor y no el mismo.
 */
class ThirdsStandingZonesRelationManager extends StandingZonesRelationManager
{
    protected static string $relationship = 'thirdsStandingZones';

    protected static string $stage = 'thirds';

    protected static ?string $title = 'Best thirds standings zones';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->hasBestThirds();
    }
}
