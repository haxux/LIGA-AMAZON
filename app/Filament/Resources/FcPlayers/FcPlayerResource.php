<?php

namespace App\Filament\Resources\FcPlayers;

use App\Filament\Resources\FcPlayers\Pages\EditFcPlayer;
use App\Filament\Resources\FcPlayers\Pages\ListFcPlayers;
use App\Filament\Resources\FcPlayers\Schemas\FcPlayerForm;
use App\Filament\Resources\FcPlayers\Tables\FcPlayersTable;
use App\Models\FcPlayer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * El catálogo de EA FC, para corregir lo que venga mal.
 *
 * Es una pantalla APARTE y no unos campos dentro de la ficha del jugador, y la
 * razón importa: un jugador de la liga ENLAZA con una ficha del catálogo, no la
 * copia. Editar las medias desde la ficha de un jugador daría a entender que se
 * tocan «sus» números, cuando en realidad se toca una fila compartida. Aquí
 * queda claro qué se está editando.
 *
 * No se crea ni se borra desde el panel: el catálogo entra por
 * `php artisan fc:importar-catalogo` y se renueva con cada edición del juego.
 * Lo que sí se hace aquí es arreglar lo que el fichero traiga mal, y esa
 * corrección sobrevive a la siguiente importación — ver `FcPlayer::booted()`.
 */
class FcPlayerResource extends Resource
{
    protected static ?string $model = FcPlayer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Catálogo EA FC';

    protected static ?string $modelLabel = 'ficha de EA FC';

    protected static ?string $pluralModelLabel = 'catálogo de EA FC';

    protected static string|UnitEnum|null $navigationGroup = 'League';

    public static function form(Schema $schema): Schema
    {
        return FcPlayerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FcPlayersTable::configure($table);
    }

    /**
     * Sin alta: una ficha inventada a mano no es una ficha de EA, y el catálogo
     * sólo tiene sentido como copia de lo que trae el juego.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFcPlayers::route('/'),
            'edit' => EditFcPlayer::route('/{record}/edit'),
        ];
    }
}
