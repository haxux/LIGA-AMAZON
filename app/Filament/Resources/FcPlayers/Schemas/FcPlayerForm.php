<?php

namespace App\Filament\Resources\FcPlayers\Schemas;

use App\Models\FcPlayer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Todo editable, que es lo que el propietario pidió: el fichero de EA trae lo
 * que trae, y cuando algo venga mal se arregla aquí. La corrección sobrevive a
 * la siguiente importación — la marca la pone `FcPlayer::booted()`.
 */
class FcPlayerForm
{
    /** Las seis casillas, con la etiqueta que les toca en cada caso. */
    private const SEIS = [
        'pace' => ['Ritmo', 'Estirada'],
        'shooting' => ['Tiro', 'Parada'],
        'passing' => ['Pase', 'Saque'],
        'dribbling' => ['Regate', 'Reflejos'],
        'defending' => ['Defensa', 'Velocidad'],
        'physicality' => ['Físico', 'Colocación'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad')
                ->columns(3)
                ->schema([
                    TextInput::make('fc_id')
                        ->label('Id en EA FC')
                        ->disabled()
                        ->helperText('No se toca: es lo que enlaza esta ficha con los jugadores y con la próxima importación.'),
                    TextInput::make('common_name')->label('Nombre corto'),
                    TextInput::make('first_name')->label('Nombre'),
                    TextInput::make('last_name')->label('Apellidos'),
                    TextInput::make('nationality')->label('Nacionalidad'),
                    DatePicker::make('birthdate')->label('Nacimiento'),
                    TextInput::make('club')->label('Club en EA'),
                    TextInput::make('league')->label('Liga en EA'),
                ]),

            Section::make('Cabecera')
                ->columns(3)
                ->schema([
                    TextInput::make('overall_rating')
                        ->label('Media')
                        ->numeric()->minValue(1)->maxValue(99),
                    Select::make('position')
                        ->label('Posición en EA')
                        ->options(array_combine(array_keys(FcPlayer::POSICIONES), array_keys(FcPlayer::POSICIONES)))
                        ->native(false)
                        ->helperText('De aquí sale la posición que se propone al crear un jugador.'),
                    TextInput::make('alternate_positions')->label('Posiciones alternativas'),
                ]),

            Section::make('Las seis')
                ->description('En los porteros EA reutiliza estas casillas y NO significan lo mismo: lo que en un jugador de campo es velocidad, en un portero es estirada. La segunda etiqueta de cada campo es la de portero.')
                ->columns(3)
                ->schema(array_map(
                    fn (string $campo) => TextInput::make($campo)
                        ->label(self::SEIS[$campo][0].' · '.self::SEIS[$campo][1])
                        ->numeric()->minValue(1)->maxValue(99),
                    array_keys(self::SEIS),
                )),

            Section::make('Perfil')
                ->columns(3)
                ->schema([
                    Select::make('preferred_foot')
                        ->label('Pie bueno')
                        ->options(['Right' => 'Diestro', 'Left' => 'Zurdo'])
                        ->native(false),
                    TextInput::make('weak_foot')
                        ->label('Pie malo (estrellas)')
                        ->numeric()->minValue(1)->maxValue(5),
                    TextInput::make('skill_moves')
                        ->label('Filigranas (estrellas)')
                        ->numeric()->minValue(1)->maxValue(5),
                ]),

            Section::make('Procedencia')
                ->columns(2)
                ->schema([
                    TextInput::make('edition')->label('Edición')->disabled(),
                    DatePicker::make('snapshot_date')->label('Fecha del fichero')->disabled(),
                ]),
        ]);
    }
}
