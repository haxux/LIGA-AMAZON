<?php

namespace App\Filament\Club\Resources\Suggestions;

use App\Filament\Club\Resources\Suggestions\Pages\CreateSuggestion;
use App\Filament\Club\Resources\Suggestions\Pages\ListSuggestions;
use App\Models\Suggestion;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * El buzón de sugerencias, visto por el técnico: escribe una idea para mejorar
 * el proyecto y ve las que ya mandó con su estado. Sólo las suyas.
 */
class SuggestionResource extends Resource
{
    protected static ?string $model = Suggestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Sugerencias';

    protected static ?string $slug = 'sugerencias';

    protected static ?string $modelLabel = 'sugerencia';

    protected static ?string $pluralModelLabel = 'sugerencias';

    protected static ?int $navigationSort = 8;

    /**
     * Segunda cerradura, además de la política: las sugerencias de cada uno y
     * de nadie más, aunque se teclee la URL.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(120)
                ->helperText('En una línea: de qué va tu idea.'),
            Textarea::make('body')
                ->label('Tu idea')
                ->required()
                ->rows(8)
                ->maxLength(2000)
                ->helperText('Cuéntala con calma: qué cambiarías, por qué y cómo lo imaginas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Enviada')->date('d/m/Y')->sortable(),
                TextColumn::make('title')->label('Título')->searchable()->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Suggestion::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Suggestion::STATUS_DONE => 'success',
                        Suggestion::STATUS_READ => 'info',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Todavía no has mandado ninguna sugerencia')
            ->emptyStateDescription('¿Qué mejorarías del proyecto? Cuéntanoslo con el botón de arriba.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuggestions::route('/'),
            'create' => CreateSuggestion::route('/enviar'),
        ];
    }
}
