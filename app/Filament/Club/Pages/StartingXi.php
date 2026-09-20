<?php

namespace App\Filament\Club\Pages;

use App\Models\Lineup;
use App\Models\LineupSlot;
use App\Models\Player;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Services\SeasonResolver;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * El once ideal, dibujado sobre un campo.
 *
 * Es una página propia y no un recurso de Filament (design D5): un CRUD no
 * sabe dibujar once huecos repartidos por líneas, y esto es exactamente eso.
 *
 * Lo que se guarda son huecos numerados 1..11 recorriendo las líneas de la
 * formación de atrás hacia delante, nunca coordenadas.
 */
class StartingXi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Once ideal';

    protected static ?string $slug = 'once-ideal';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.club.pages.starting-xi';

    public string $formation = '4-4-2';

    /**
     * Jugador elegido para cada hueco, indexado por número de hueco.
     *
     * Se llama `picks` y no `slots` porque Livewire reserva esa segunda
     * palabra: una propiedad pública `$slots` choca con su mecanismo de slots
     * y revienta al renderizar con "Call to a member function getName() on int".
     *
     * @var array<int, int|string|null>
     */
    public array $picks = [];

    public function mount(): void
    {
        $team = $this->team();

        if ($team?->lineup === null) {
            return;
        }

        $this->formation = $team->lineup->formation;
        $this->picks = $team->lineup->slots->pluck('player_id', 'slot')->all();
    }

    public function getTitle(): string
    {
        return 'Once ideal';
    }

    /**
     * El equipo del club en la temporada vigente. Sin temporada activa o sin
     * inscripción no hay once que armar, y la vista lo dice en lugar de fallar.
     */
    public function team(): ?Team
    {
        $season = app(SeasonResolver::class)->active();

        if ($season === null) {
            return null;
        }

        return Team::query()
            ->with('lineup.slots')
            ->where('club_id', auth()->user()?->club_id)
            ->where('season_id', $season->getKey())
            ->first();
    }

    /**
     * Las líneas de la formación elegida, con el portero primero, y el número
     * de hueco que le corresponde a cada posición.
     *
     * @return array<int, array<int, int>>
     */
    public function rows(): array
    {
        $rows = [];
        $slot = 1;

        foreach ([1, ...Lineup::FORMATIONS[$this->formation]] as $count) {
            $row = [];

            for ($i = 0; $i < $count; $i++) {
                $row[] = $slot++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * La plantilla de esta temporada, que es entre quienes se elige.
     *
     * @return Collection<int, Player>
     */
    public function squad(): Collection
    {
        $team = $this->team();

        if ($team === null) {
            return collect();
        }

        return Player::query()
            ->whereHas('memberships', fn (Builder $query) => $query->where('team_id', $team->getKey()))
            ->orderBy('name')
            ->get()
            ->map(function (Player $player) use ($team) {
                $player->setAttribute('shirt_number', SquadMembership::query()
                    ->where('team_id', $team->getKey())
                    ->where('player_id', $player->getKey())
                    ->value('shirt_number'));

                return $player;
            });
    }

    /**
     * Cambiar de formación conserva a los jugadores que siguen cabiendo y
     * suelta a los que se quedan sin hueco: reordenar un 4-4-2 en 4-3-3 no
     * debería obligar a rehacer el once entero.
     */
    public function updatedFormation(): void
    {
        $this->picks = array_filter(
            $this->picks,
            fn (int $slot) => $slot <= Lineup::SLOTS,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function save(): void
    {
        $team = $this->team();

        if ($team === null) {
            Notification::make()->danger()->title('Este club no está inscrito en la temporada vigente')->send();

            return;
        }

        $chosen = collect($this->picks)->filter()->map(fn ($id) => (int) $id);

        if ($chosen->duplicates()->isNotEmpty()) {
            Notification::make()->danger()->title('Hay un jugador repetido en el once')->send();

            return;
        }

        $lineup = Lineup::updateOrCreate(
            ['team_id' => $team->getKey()],
            ['formation' => $this->formation],
        );

        $lineup->slots()->delete();

        $chosen->each(fn (int $playerId, int $slot) => LineupSlot::create([
            'lineup_id' => $lineup->getKey(),
            'player_id' => $playerId,
            'slot' => $slot,
        ]));

        Notification::make()->success()->title('Once ideal guardado')->send();
    }

    /**
     * @return array<string, string>
     */
    public function formationOptions(): array
    {
        return array_combine(array_keys(Lineup::FORMATIONS), array_keys(Lineup::FORMATIONS));
    }
}
