<?php

namespace App\Filament\Resources\CupTies;

use App\Filament\Resources\CupTies\Pages\ListCupTies;
use App\Filament\Resources\CupTies\RelationManagers\TieGamesRelationManager;
use App\Filament\Support\TeamOptions;
use App\Models\CupRound;
use App\Models\CupTie;
use App\Models\Team;
use App\Services\CupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Los cruces del cuadro: dos equipos, sus partidos y quién pasa.
 *
 * Tienen pantalla propia y no viven dentro de la copa porque son lo que se toca
 * una y otra vez según avanza el torneo. El cuadro lo arma el administrador
 * ronda a ronda (decisión del propietario): la aplicación le dice quién ganó
 * cada cruce, pero no empareja sola.
 */
class CupTieResource extends Resource
{
    protected static ?string $model = CupTie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBarsArrowDown;

    protected static ?string $navigationLabel = 'Cup ties';

    protected static ?string $modelLabel = 'tie';

    protected static string|\UnitEnum|null $navigationGroup = 'Competition';

    protected static ?string $slug = 'cup-ties';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cup_round_id')
                ->label('Round')
                ->options(fn (): array => CupRound::query()
                    ->with('cup')
                    ->orderBy('cup_id')
                    ->orderBy('position')
                    ->get()
                    ->mapWithKeys(fn (CupRound $round) => [$round->id => "{$round->cup?->name} · {$round->name}"])
                    ->all())
                ->required()
                ->searchable()
                ->live(),

            // Sólo los equipos apuntados a esa copa: un cruce entre alguien que
            // no la juega es un error de dedo, no una posibilidad.
            Select::make('home_team_id')
                ->label('Home team')
                ->options(fn (Get $get): array => static::participants($get))
                ->required()
                ->searchable()
                ->live(),
            Select::make('away_team_id')
                ->label('Away team')
                ->options(fn (Get $get): array => static::participants($get))
                ->required()
                ->searchable()
                ->different('home_team_id'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function participants(Get $get): array
    {
        $round = CupRound::query()->with('cup.participants')->find($get('cup_round_id'));

        return TeamOptions::for(fn (Builder $query) => $query->whereIn(
            'id',
            $round?->cup?->participants->pluck('team_id') ?? [],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('round.cup.name')->label('Cup')->sortable(),
                TextColumn::make('round.name')->label('Round')->sortable(),
                TextColumn::make('homeTeam.name')->label('Home')->searchable(),
                TextColumn::make('awayTeam.name')->label('Away')->searchable(),
                TextColumn::make('aggregate')
                    ->label('Aggregate')
                    ->state(function (CupTie $record): string {
                        $result = app(CupService::class)->result($record);

                        return $result->played === 0 ? '—' : "{$result->homeGoals} – {$result->awayGoals}";
                    }),
                TextColumn::make('through')
                    ->label('Through')
                    ->badge()
                    ->state(function (CupTie $record): string {
                        $result = app(CupService::class)->result($record);

                        return match (true) {
                            $result->needsDecision() => 'Level — decide',
                            $result->isDecided() => $result->winner->name,
                            default => 'Playing',
                        };
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Level — decide' => 'warning',
                        'Playing' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('decision_note')->label('Because')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('round')->relationship('round', 'name')->label('Round'),
            ])
            ->recordActions([
                // Resolver una eliminatoria empatada: quién pasa y por qué. El
                // motivo es obligatorio porque es lo que se consulta después.
                Action::make('decide')
                    ->label('Who goes through')
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->visible(fn (CupTie $record): bool => app(CupService::class)->result($record)->needsDecision())
                    ->form(fn (CupTie $record): array => [
                        Select::make('winner_team_id')
                            ->label('Goes through')
                            ->options([
                                $record->home_team_id => $record->homeTeam?->name,
                                $record->away_team_id => $record->awayTeam?->name,
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('decision_note')
                            ->label('Because')
                            ->required()
                            ->helperText('Penalties, away goals, a coin toss — whatever settled it.'),
                    ])
                    ->action(function (CupTie $record, array $data): void {
                        try {
                            app(CupService::class)->decide(
                                $record,
                                auth()->user(),
                                Team::query()->findOrFail($data['winner_team_id']),
                                $data['decision_note'],
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title($exception->validator->errors()->first())->send();

                            return;
                        }

                        Notification::make()->success()->title('Settled')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TieGamesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCupTies::route('/'),
            'create' => Pages\CreateCupTie::route('/create'),
            'edit' => Pages\EditCupTie::route('/{record}/edit'),
        ];
    }
}
