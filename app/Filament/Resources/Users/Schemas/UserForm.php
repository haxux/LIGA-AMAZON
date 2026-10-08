<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Las cuentas las crea el administrador, no hay registro público (es la
 * precondición que `PanelAccessTest` vigila desde la Fase 7).
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->helperText('Es el nombre que verá el público en la ficha del club.'),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    // Al editar, un campo vacío significa "no la cambies", no
                    // "ponla en blanco".
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')
                    ->label('Rol')
                    ->options(User::ROLES)
                    ->default(User::ROLE_ADMIN)
                    ->required()
                    ->native(false)
                    ->live()
                    // Un administrador no dirige ningún club: el modelo lo
                    // rechaza, así que el formulario lo limpia al cambiar.
                    ->afterStateUpdated(fn (string $state, Set $set) => $state === User::ROLE_ADMIN ? $set('club_id', null) : null),
                Select::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('role') === User::ROLE_COACH)
                    ->required(fn (Get $get): bool => $get('role') === User::ROLE_COACH)
                    // Un club tiene un técnico y un técnico un club (design D8).
                    ->unique(ignoreRecord: true),
                FileUpload::make('photo_path')
                    ->label('Foto')
                    ->image()
                    // Mismo riesgo y misma defensa que `ClubForm::crest_path`:
                    // ->image() admite image/svg+xml, que en el disco 'public'
                    // se serviría same-origin y ejecutaría como script. La
                    // lista explícita, raster-only, tiene que ir después de
                    // ->image().
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->maxSize(2048)
                    ->disk(config('filesystems.uploads'))
                    ->directory('coaches')
                    ->visibility('public')
                    ->visible(fn (Get $get): bool => $get('role') === User::ROLE_COACH)
                    ->helperText('Se publica en la ficha del club, pestaña «Técnico».'),
            ]);
    }
}
