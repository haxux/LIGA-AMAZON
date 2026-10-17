<?php

namespace App\Filament\Resources\Cups;

use App\Filament\Resources\Cups\Pages\CreateCup;
use App\Filament\Resources\Cups\Pages\EditCup;
use App\Filament\Resources\Cups\Pages\ListCups;
use App\Filament\Resources\Cups\RelationManagers\GroupsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\RoundsRelationManager;
use App\Filament\Resources\Cups\RelationManagers\StandingZonesRelationManager;
use App\Filament\Resources\Cups\RelationManagers\ThirdsStandingZonesRelationManager;
use App\Enums\CupDesign;
use App\Enums\CupScope;
use App\Models\Cup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Una copa de la temporada (Fase 15).
 *
 * Se arma en este orden: la copa, sus grupos si los lleva, los equipos que la
 * juegan y sus rondas. Los cruces de cada ronda viven en su propia pantalla,
 * porque son los que se tocan una y otra vez a medida que avanza el cuadro.
 */
class CupResource extends Resource
{
    protected static ?string $model = Cup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Cups';

    protected static string|\UnitEnum|null $navigationGroup = 'Competition';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->helperText('As it will read on the public site, e.g. Copa Amazonas.'),
                Select::make('scope')
                    ->label('Type')
                    ->options(collect(CupScope::cases())->mapWithKeys(fn (CupScope $s) => [$s->value => $s->label()]))
                    ->default(CupScope::National->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('National cups keep the house look. International ones get their own design.'),
                Toggle::make('has_group_stage')
                    ->label('Group stage first')
                    ->helperText('Off means a straight bracket from the first round.'),
                Section::make('International cup design')
                    ->visible(fn (Get $get) => $get('scope') === CupScope::International->value)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('design')
                            ->label('International cup design')
                            ->options(collect(CupDesign::cases())->mapWithKeys(fn (CupDesign $d) => [$d->value => $d->label()]))
                            ->required()
                            ->native(false)
                            ->helperText('Sets the colours of the cup page, its matches and its listing.'),
                        self::imageField('logo_path', 'Logo', 'cups/logos', 600),
                        self::imageField('background_path', 'Header background', 'cups/backgrounds', 1920),
                    ]),
            ]);
    }

    /**
     * Las imágenes oficiales las sube el administrador. Sólo raster: un SVG
     * subido puede llevar script y se serviría desde el mismo origen (misma
     * razón que la portada de las noticias).
     *
     * Se encogen en el navegador antes de subirse, a `$maxWidth`: un banner
     * oficial suele llegar a 4000 px, y el móvil tenía que decodificar eso
     * entero para pintarlo a 400. Nunca se agrandan.
     */
    private static function imageField(string $name, string $label, string $directory, int $maxWidth): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth((string) $maxWidth)
            ->automaticallyResizeImagesToHeight((string) $maxWidth)
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->maxSize(2048)
            ->disk(config('filesystems.uploads'))
            ->directory($directory)
            ->visibility('public');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                TextColumn::make('scope')->label('Type')->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state === CupScope::International ? 'warning' : 'gray'),
                TextColumn::make('design')->label('Design')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->placeholder('—'),
                IconColumn::make('has_group_stage')->label('Groups')->boolean(),
                TextColumn::make('participants_count')->counts('participants')->label('Teams'),
                TextColumn::make('rounds_count')->counts('rounds')->label('Rounds'),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GroupsRelationManager::class,
            StandingZonesRelationManager::class,
            ThirdsStandingZonesRelationManager::class,
            ParticipantsRelationManager::class,
            RoundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCups::route('/'),
            'create' => CreateCup::route('/create'),
            'edit' => EditCup::route('/{record}/edit'),
        ];
    }
}
