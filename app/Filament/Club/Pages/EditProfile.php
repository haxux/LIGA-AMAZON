<?php

namespace App\Filament\Club\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * «Mi perfil», sólo para la foto.
 *
 * El nombre y el correo los fija el administrador —son la identidad de la
 * cuenta, y el nombre además es lo que se publica en la ficha del club (ver
 * `UserForm`)—; la contraseña se cambia desde ahí también. Lo único que un
 * técnico ajusta de sí mismo es su foto, así que esta página reemplaza el
 * formulario entero de `EditProfile` por ese único campo en vez de esconder
 * los demás con `->visible(false)`, que los dejaría en el `$data` enviado.
 */
class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Mi perfil';
    }

    public function getTitle(): string
    {
        return 'Mi perfil';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPhotoFormComponent(),
        ]);
    }

    protected function getPhotoFormComponent(): Component
    {
        return FileUpload::make('photo_path')
            ->label('Foto')
            ->image()
            // Mismo riesgo y misma defensa que `ClubForm::crest_path` y
            // `UserForm::photo_path`: raster-only, explícito después de
            // ->image().
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->maxSize(2048)
            ->disk(config('filesystems.uploads'))
            ->directory('coaches')
            ->visibility('public')
            ->avatar()
            ->helperText('Se publica en la ficha de tu club, pestaña «Técnico».');
    }
}
