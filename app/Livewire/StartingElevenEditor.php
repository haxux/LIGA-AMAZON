<?php

namespace App\Livewire;

use App\Models\Lineup;
use App\Models\LineupSlot;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use App\Services\SeasonResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El once ideal, armado sobre la misma ficha pública donde todo el mundo lo ve.
 *
 * Vive aquí y no en el panel del técnico (decisión del propietario al cerrar la
 * Fase 13): dos pantallas escribiendo la misma alineación es lo que se separa
 * con el tiempo, y el sitio donde tiene sentido juzgar cómo queda un once es
 * donde se enseña.
 *
 * La pantalla pública sólo monta este componente para el técnico del club y en
 * la temporada vigente; a cualquier otro visitante le sirve HTML plano. Aun así
 * CADA acción vuelve a comprobar el permiso: la petición de Livewire no pasa
 * por la ruta pública, así que esconder el botón no es la cerradura, sólo la
 * cortesía.
 */
class StartingElevenEditor extends Component
{
    #[Locked]
    public int $teamId;

    public string $formation = '4-4-2';

    /**
     * Jugador elegido para cada hueco, indexado por número de hueco.
     *
     * `picks` y no `slots`: Livewire reserva esa segunda palabra y una
     * propiedad pública con ese nombre revienta al renderizar con "Call to a
     * member function getName() on int".
     *
     * @var array<int, int|string|null>
     */
    public array $picks = [];

    /**
     * El hueco cuyo desplegable de jugadores está abierto, si hay alguno.
     */
    public ?int $openSlot = null;

    public bool $saved = false;

    public ?string $error = null;

    public function mount(Team $team): void
    {
        $this->teamId = $team->getKey();

        $lineup = $team->lineup;

        if ($lineup === null) {
            return;
        }

        $this->formation = $lineup->formation;
        $this->picks = $lineup->slots->pluck('player_id', 'slot')->all();
    }

    public function team(): Team
    {
        return Team::query()->with('lineup.slots')->findOrFail($this->teamId);
    }

    /**
     * Manda el técnico de ESTE club, y sólo sobre la temporada vigente: una
     * alineación de un año cerrado es historia, no un borrador.
     */
    public function canEdit(): bool
    {
        $user = auth('club')->user();
        $team = Team::query()->find($this->teamId);

        if (! $user instanceof User || $team === null) {
            return false;
        }

        return $user->isCoach()
            && (int) $user->club_id === (int) $team->club_id
            && (int) $team->season_id === (int) app(SeasonResolver::class)->active()?->getKey();
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function rows(): array
    {
        return Lineup::rowsFor($this->formation);
    }

    /**
     * @return array<string, string>
     */
    public function formationOptions(): array
    {
        return array_combine(array_keys(Lineup::FORMATIONS), array_keys(Lineup::FORMATIONS));
    }

    /**
     * La plantilla de esta temporada, que es entre quienes se elige, con el
     * dorsal de esa misma plantilla pegado a cada jugador.
     *
     * @return Collection<int, Player>
     */
    public function squad(): Collection
    {
        $team = $this->team();

        $numbers = SquadMembership::query()
            ->where('team_id', $team->getKey())
            ->pluck('shirt_number', 'player_id');

        return Player::query()
            ->whereHas('memberships', fn (Builder $query) => $query->where('team_id', $team->getKey()))
            ->orderBy('name')
            ->get()
            ->each(fn (Player $player) => $player->setAttribute('shirt_number', $numbers[$player->getKey()] ?? null));
    }

    public function openPicker(int $slot): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $this->openSlot = $this->openSlot === $slot ? null : $slot;
    }

    public function closePicker(): void
    {
        $this->openSlot = null;
    }

    /**
     * Colocar a alguien que ya estaba en otro hueco lo MUEVE: un once se arma
     * arrastrando jugadores, no clonándolos.
     */
    public function place(int $slot, ?int $playerId = null): void
    {
        if (! $this->canEdit() || $slot < 1 || $slot > Lineup::SLOTS) {
            return;
        }

        $this->saved = false;
        $this->error = null;

        if ($playerId === null) {
            unset($this->picks[$slot]);
            $this->openSlot = null;

            return;
        }

        // Sólo de la plantilla de esta temporada, aunque el id llegue tecleado.
        if (! $this->squad()->contains('id', $playerId)) {
            return;
        }

        foreach ($this->picks as $other => $pick) {
            if ((int) $other !== $slot && filled($pick) && (int) $pick === $playerId) {
                unset($this->picks[$other]);
            }
        }

        $this->picks[$slot] = $playerId;
        $this->openSlot = null;
    }

    /**
     * Cambiar de formación conserva a quien sigue cabiendo y suelta a quien se
     * queda sin hueco: reordenar un 4-4-2 en 4-3-3 no debería obligar a rehacer
     * el once entero.
     */
    public function updatedFormation(): void
    {
        $this->saved = false;
        $this->openSlot = null;

        if (! array_key_exists($this->formation, Lineup::FORMATIONS)) {
            $this->formation = array_key_first(Lineup::FORMATIONS);
        }

        $this->picks = array_filter(
            $this->picks,
            fn (int $slot) => $slot <= Lineup::SLOTS,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Con botón y no al vuelo (decisión del propietario): en una página que lee
     * cualquiera, guardar cada clic dejaría el once a medio armar a la vista de
     * todos.
     */
    public function save(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $team = $this->team();
        $chosen = collect($this->picks)->filter()->map(fn ($id) => (int) $id);
        $repeated = $chosen->duplicates();

        if ($repeated->isNotEmpty()) {
            $this->error = 'Hay un jugador repetido en el once: '
                .Player::query()->whereIn('id', $repeated->unique())->pluck('name')->implode(', ');

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

        $this->saved = true;
        $this->error = null;
    }

    public function render()
    {
        return view('livewire.starting-eleven-editor', [
            'squad' => $this->squad(),
        ]);
    }

    /**
     * La temporada vigente, para que la vista pueda decir por qué no se edita
     * una pasada sin repetir la regla.
     */
    public function currentSeason(): ?Season
    {
        return app(SeasonResolver::class)->active();
    }
}
