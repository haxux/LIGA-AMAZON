<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Filament\Support\TeamOptions;
use App\Models\CupGroup;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Team;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class GameForm
{
    /**
     * Dónde se juega este partido. Desde la Fase 15 hay tres sitios posibles —la
     * jornada de una liga, el cruce de una copa o el grupo de una copa— y un
     * partido pertenece exactamente a uno: el guard de `Game` lo exige, así que
     * elegir aquí limpia los otros dos.
     *
     * `competition` no es una columna: es la pregunta que hace falta para saber
     * cuál de los tres campos enseñar.
     *
     * @var array<string, string>
     */
    public const COMPETITIONS = [
        'league' => 'League matchday',
        'tie' => 'Cup tie',
        'group' => 'Cup group',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('competition')
                    ->label('Competition')
                    ->options(self::COMPETITIONS)
                    ->default('league')
                    ->required()
                    ->native(false)
                    ->live()
                    ->dehydrated(false)
                    // Al abrir un partido ya guardado, la competición se deduce
                    // de él: no es una columna, así que nadie la trae puesta.
                    ->afterStateHydrated(function (Select $component, mixed $state, ?Game $record): void {
                        if (filled($state)) {
                            return;
                        }

                        $component->state(match (true) {
                            $record?->cup_tie_id !== null => 'tie',
                            $record?->cup_group_id !== null => 'group',
                            default => 'league',
                        });
                    })
                    ->afterStateUpdated(function (Set $set): void {
                        $set('matchday_id', null);
                        $set('cup_tie_id', null);
                        $set('cup_group_id', null);
                        $set('group_matchday', null);
                        $set('home_team_id', null);
                        $set('away_team_id', null);
                    }),

                Select::make('cup_tie_id')
                    ->label('Tie')
                    ->options(fn (): array => CupTie::query()
                        ->with(['round.cup', 'homeTeam.club', 'awayTeam.club'])
                        ->get()
                        ->mapWithKeys(fn (CupTie $tie) => [$tie->id => sprintf(
                            '%s · %s · %s vs %s',
                            $tie->round?->cup?->name,
                            $tie->round?->name,
                            $tie->homeTeam?->name,
                            $tie->awayTeam?->name,
                        )])
                        ->all())
                    ->visible(fn (Get $get): bool => $get('competition') === 'tie')
                    ->required(fn (Get $get): bool => $get('competition') === 'tie')
                    ->searchable()
                    ->live()
                    // Los dos equipos del cruce vienen puestos: en una ida y
                    // vuelta se le da la vuelta a mano, que es el único caso.
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $tie = CupTie::query()->find($state);
                        $set('home_team_id', $tie?->home_team_id);
                        $set('away_team_id', $tie?->away_team_id);
                    }),

                Select::make('cup_group_id')
                    ->label('Group')
                    ->options(fn (): array => CupGroup::query()
                        ->with('cup')
                        ->get()
                        ->mapWithKeys(fn (CupGroup $group) => [$group->id => "{$group->cup?->name} · {$group->name}"])
                        ->all())
                    ->visible(fn (Get $get): bool => $get('competition') === 'group')
                    ->required(fn (Get $get): bool => $get('competition') === 'group')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('home_team_id', null);
                        $set('away_team_id', null);
                    }),

                TextInput::make('group_matchday')
                    ->label('Group matchday')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('competition') === 'group'),

                Select::make('matchday_id')
                    ->visible(fn (Get $get): bool => ($get('competition') ?? 'league') === 'league')
                    ->required(fn (Get $get): bool => ($get('competition') ?? 'league') === 'league')
                    ->relationship('matchday', 'number')
                    ->getOptionLabelFromRecordUsing(fn (Matchday $record): string => "{$record->season->name} · {$record->division->name} · MD {$record->number}")
                    ->searchable()
                    ->preload()
                    // The team Selects below are scoped to this matchday's
                    // division, so the matchday has to be live; clearing both
                    // teams on change stops a now-foreign team from surviving.
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('home_team_id', null);
                        $set('away_team_id', null);
                    }),
                ...static::teamAndScoreFields(),
            ]);
    }

    /**
     * Shared with GamesRelationManager, which reuses everything except the
     * matchday_id Select (the relationship sets it there instead). The
     * away_team_id closure rule() below travels along unchanged — Get
     * resolves relatively to the schema it is evaluated within, so the
     * comparison against home_team_id still works inside the RM's modal.
     *
     * @return array<int, Component>
     */
    public static function teamAndScoreFields(): array
    {
        return [
            Select::make('home_team_id')
                ->label('Home team')
                ->options(fn (Get $get, $livewire): array => static::teamOptions($get, $livewire))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->rule(static::divisionRule(...)),
            Select::make('away_team_id')
                ->label('Away team')
                ->options(fn (Get $get, $livewire): array => static::teamOptions($get, $livewire))
                ->required()
                ->searchable()
                ->preload()
                ->rule(static::divisionRule(...))
                ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (filled($value) && (int) $value === (int) $get('home_team_id')) {
                        $fail('A team cannot play against itself.');
                    }
                }),
            DateTimePicker::make('kickoff_at'),
            TextInput::make('home_score')
                ->numeric()
                ->minValue(0),
            TextInput::make('away_score')
                ->numeric()
                ->minValue(0),
        ];
    }

    /**
     * Both team Selects offer only the clubs of the matchday's own division:
     * a jornada belongs to one division, so anything else is a data-entry
     * slip. With no matchday picked yet the division is null and the query
     * matches nothing — the admin picks the matchday first.
     */
    private static function teamOptions(Get $get, mixed $livewire): array
    {
        // En un cruce juegan los dos de siempre; en un grupo, los apuntados a
        // él; en una jornada, los de su división.
        if (filled($get('cup_tie_id'))) {
            $tie = CupTie::query()->with(['homeTeam.club', 'awayTeam.club'])->find($get('cup_tie_id'));

            return TeamOptions::for(fn (Builder $query) => $query->whereIn('id', array_filter([
                $tie?->home_team_id,
                $tie?->away_team_id,
            ])));
        }

        if (filled($get('cup_group_id'))) {
            $group = CupGroup::query()->with('participants')->find($get('cup_group_id'));

            return TeamOptions::for(fn (Builder $query) => $query->whereIn('id', $group?->participants->pluck('team_id') ?? []));
        }

        $divisionId = static::divisionId($get, $livewire);

        return TeamOptions::for(fn (Builder $query) => $query->where('division_id', $divisionId));
    }

    /**
     * The options above are only the UI half: a stale form (or a hand-rolled
     * Livewire payload) can still submit a team from another division, so the
     * same scope is re-checked as a validation rule. Deliberately form-level
     * and not a Model guard — StandingsService is specified to handle games
     * whose teams sit in different divisions, and a guard would make that
     * state unrepresentable.
     */
    private static function divisionRule(Get $get, mixed $livewire): Closure
    {
        // Un partido de copa no tiene división que respetar: es de otra
        // competición, y cruzar divisiones es justamente lo que hace.
        if (filled($get('cup_tie_id')) || filled($get('cup_group_id'))) {
            return static function (): void {};
        }

        $divisionId = static::divisionId($get, $livewire);

        return static function (string $attribute, mixed $value, Closure $fail) use ($divisionId): void {
            if (blank($value) || $divisionId === null) {
                return;
            }

            if ((int) Team::query()->whereKey($value)->value('division_id') !== (int) $divisionId) {
                $fail('The team must belong to the same division as the matchday.');
            }
        };
    }

    /**
     * GameResource holds the matchday in form state; the RelationManager has
     * no such field, because the matchday IS its owner record.
     */
    private static function divisionId(Get $get, mixed $livewire): ?int
    {
        $matchdayId = $get('matchday_id');

        if (blank($matchdayId) && $livewire instanceof RelationManager) {
            $ownerRecord = $livewire->getOwnerRecord();

            return $ownerRecord instanceof Matchday ? $ownerRecord->division_id : null;
        }

        return Matchday::query()->whereKey($matchdayId)->value('division_id');
    }
}
