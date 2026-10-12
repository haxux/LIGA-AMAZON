<?php

namespace App\Filament\Resources\Players\Schemas;

use App\Models\FcPlayer;
use App\Models\Player;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Desde la Fase 9 este formulario edita la IDENTIDAD del jugador. El dorsal y
 * la plantilla a la que pertenece cada temporada se gestionan desde el equipo
 * de esa temporada (`SquadRelationManager`), porque ambos cambian de año en
 * año y el jugador no.
 */
class PlayerForm
{
    /** @var array<int, string> */
    public const POSITIONS = Player::POSITIONS;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->label('Club')
                    ->required()
                    ->searchable()
                    ->preload(),
                ...static::clubAgnosticFields(),
            ]);
    }

    /**
     * Shared with PlayersRelationManager on ClubResource, which reuses
     * everything except the club_id Select (the relationship sets it there).
     *
     * @return array<int, Component>
     */
    public static function clubAgnosticFields(): array
    {
        return [
            // El buscador del catálogo de EA FC, arriba del todo: rellena los
            // campos de abajo y desaparece del guardado (`dehydrated(false)`),
            // porque no es una columna de `players`.
            //
            // Todo lo que rellena queda editable: esto es un atajo para no
            // teclear quince campos, no una fuente de verdad. La creación a
            // mano de siempre funciona igual sin tocar este campo.
            Select::make('fc_lookup')
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
                ->afterStateUpdated(function ($state, Set $set): void {
                    $ficha = FcPlayer::where('fc_id', $state)->first();

                    if ($ficha === null) {
                        return;
                    }

                    [$general, $especifica] = $ficha->posicionEnLaLiga();

                    $set('name', $ficha->nombre());
                    $set('position', $general);
                    $set('specific_position', $especifica);
                    $set('birth_date', $ficha->birthdate?->toDateString());
                    $set('fc_id', $ficha->fc_id);
                })
                ->helperText('Opcional. Rellena los campos de abajo y luego los corriges a mano si hace falta.'),

            // El enlace con el catálogo, de donde la ficha pública saca las
            // medias. Se guarda pero no se teclea: lo pone el buscador.
            TextInput::make('fc_id')
                ->label('Id en EA FC')
                ->numeric()
                ->helperText('Lo rellena el buscador. Vacíalo para desligar al jugador del catálogo.'),

            TextInput::make('name')
                ->required(),
            Select::make('position')
                ->options(array_combine(self::POSITIONS, self::POSITIONS))
                ->required()
                ->native(false)
                ->live()
                // Cambiar de posición general invalida la específica anterior:
                // un central no puede seguir siendo extremo. Mismo gesto que en
                // SquadResource, porque el campo es el mismo visto desde el
                // otro panel.
                ->afterStateUpdated(fn (Set $set) => $set('specific_position', null)),
            Select::make('specific_position')
                ->options(fn (Get $get): array => array_combine(
                    Player::specificPositionsFor($get('position')),
                    Player::specificPositionsFor($get('position')),
                ))
                ->native(false)
                ->helperText('Pick the general position first.'),
            TextInput::make('market_value')
                ->label('Value')
                ->numeric()
                ->minValue(0)
                // Entero con separador de miles y sin símbolo de moneda
                // (decisión del propietario): la liga no fija una divisa.
                ->helperText('Plain figure, no currency symbol.'),
            DatePicker::make('birth_date'),
            // A mano: ninguna fuente de datos libre trae caras, y las que las
            // publican lo hacen desde un CDN ajeno sin permiso para usarlo.
            FileUpload::make('photo_path')
                ->label('Foto')
                ->image()
                ->disk(config('filesystems.uploads'))
                ->directory('players')
                ->maxSize(2048)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->helperText('jpeg, png o webp. Máximo 2 MB.'),
        ];
    }
}
