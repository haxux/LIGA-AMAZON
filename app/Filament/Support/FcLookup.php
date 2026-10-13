<?php

namespace App\Filament\Support;

use App\Models\FcPlayer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;

/**
 * El buscador del catálogo de EA FC, que rellena una ficha sin teclearla.
 *
 * Lo usan los DOS sitios donde nace un jugador: el alta a mano desde Jugadores
 * y el cierre de un fichaje de fuera de la liga, donde hasta ahora el que
 * llegaba era sólo un nombre escrito a mano. Vive aquí y no duplicado porque
 * el formato de los resultados y los campos que rellena tienen que ser los
 * mismos: dos copias se separan a la primera y entonces el mismo jugador entra
 * distinto según por dónde se le dé de alta.
 *
 * Lo que rellena queda EDITABLE. Es un atajo para no teclear quince campos, no
 * una fuente de verdad, y el alta a mano de siempre funciona sin tocarlo.
 */
final class FcLookup
{
    /**
     * @param  string  $nameField  cómo se llama el campo del nombre en ESE formulario
     */
    public static function make(string $nameField = 'name'): Select
    {
        return Select::make('fc_lookup')
            ->label('Rellenar desde EA FC')
            ->placeholder('Escribe un nombre: Mbappe, Ekitike, Kvaratskhelia…')
            ->searchable()
            ->dehydrated(false)
            ->live()
            ->getSearchResultsUsing(fn (string $search): array => FcPlayer::buscar($search)
                ->limit(25)
                ->get()
                ->mapWithKeys(fn (FcPlayer $f): array => [$f->fc_id => sprintf(
                    '%s · %s %s · %s',
                    $f->nombre(),
                    $f->overall_rating ?? '—',
                    $f->position ?? '',
                    $f->club ?? 'sin club',
                )])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => FcPlayer::where('fc_id', $value)->first()?->nombre())
            ->afterStateUpdated(function ($state, Set $set) use ($nameField): void {
                $ficha = FcPlayer::where('fc_id', $state)->first();

                if ($ficha === null) {
                    return;
                }

                // La posición general es exacta; la específica es aproximada
                // —EA no distingue un central diestro de uno zurdo— y por eso
                // el formulario la deja cambiar.
                [$general, $especifica] = $ficha->posicionEnLaLiga();

                $set($nameField, $ficha->nombre());
                $set('position', $general);
                $set('specific_position', $especifica);
                $set('birth_date', $ficha->birthdate?->toDateString());
                $set('fc_id', $ficha->fc_id);
            })
            ->helperText('Opcional. Rellena los campos de abajo y luego los corriges a mano si hace falta.');
    }

    /**
     * El id del catálogo: se guarda, pero no se teclea. Es lo que ata al
     * jugador con su ficha de EA, de donde la página pública saca las medias.
     */
    public static function idField(): TextInput
    {
        return TextInput::make('fc_id')
            ->label('Id en EA FC')
            ->numeric()
            ->helperText('Lo rellena el buscador. Vacíalo para desligar al jugador del catálogo.');
    }

    /**
     * La foto, que se sube a mano: ninguna fuente de datos libre trae caras, y
     * las que las publican lo hacen desde un CDN ajeno sin permiso para usarlo.
     */
    public static function photoField(): FileUpload
    {
        return FileUpload::make('photo_path')
            ->label('Foto')
            ->image()
            ->disk(config('filesystems.uploads'))
            ->directory('players')
            ->maxSize(2048)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->helperText('jpeg, png o webp. Máximo 2 MB.');
    }
}
