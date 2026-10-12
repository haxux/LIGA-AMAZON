<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Una ficha del catálogo de EA FC, de donde se copia para crear un jugador.
 *
 * NO es un jugador de la liga: aquí no juega nadie, no tiene club ni dorsal ni
 * eventos. Es una fuente de la que `Player` se rellena. Las dos viven aparte a
 * propósito — vaciar el catálogo y reimportarlo no debe tocar a nadie que ya
 * esté jugando.
 */
#[Fillable([
    'fc_id', 'common_name', 'first_name', 'last_name', 'search_name',
    'nationality', 'club', 'league', 'gender', 'birthdate',
    'overall_rating', 'position', 'alternate_positions',
    'pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality',
    'preferred_foot', 'weak_foot', 'skill_moves',
    'edition', 'snapshot_date',
])]
class FcPlayer extends Model
{
    /**
     * Cómo se traduce cada posición de EA a las de esta liga.
     *
     * EA maneja 12 y aquí hay 4 generales con 27 específicas, así que la
     * traducción es exacta en la general y APROXIMADA en la específica: EA no
     * distingue entre un central diestro y uno zurdo, ni entre un interior y un
     * mediocentro escorado. Lo que sale de aquí es un punto de partida que el
     * administrador corrige en el formulario, no una verdad.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const POSICIONES = [
        'GK' => [Player::POSITION_GOALKEEPER, 'POR'],
        'CB' => ['Defender', 'DFC'],
        'RB' => ['Defender', 'LD'],
        'LB' => ['Defender', 'LI'],
        'CDM' => ['Midfielder', 'MCD'],
        'CM' => ['Midfielder', 'MC'],
        'CAM' => ['Midfielder', 'MCO'],
        'LM' => ['Midfielder', 'MI'],
        'RM' => ['Midfielder', 'MD'],
        'LW' => ['Forward', 'EI'],
        'RW' => ['Forward', 'ED'],
        'ST' => ['Forward', 'DC'],
    ];

    /**
     * El importador escribe con el constructor de consultas, sin modelo, así
     * que no dispara esto. Cualquier guardado que SÍ pase por aquí es una
     * persona corrigiendo, y queda marcado para que la siguiente importación
     * respete su trabajo.
     */
    protected static function booted(): void
    {
        static::updating(function (self $ficha): void {
            $ficha->hand_edited = true;
        });
    }

    protected function casts(): array
    {
        return [
            'hand_edited' => 'boolean',
            'birthdate' => 'date',
            'snapshot_date' => 'date',
            'overall_rating' => 'integer',
            'pace' => 'integer',
            'shooting' => 'integer',
            'passing' => 'integer',
            'dribbling' => 'integer',
            'defending' => 'integer',
            'physicality' => 'integer',
            'weak_foot' => 'integer',
            'skill_moves' => 'integer',
        ];
    }

    /**
     * El nombre tal como se enseña: EA deja `common_name` vacío en la mayoría
     * y entonces el nombre es la suma de los dos.
     */
    public function nombre(): string
    {
        return filled($this->common_name)
            ? $this->common_name
            : trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Minúsculas y sin tildes, que es como se guarda `search_name` y como se
     * busca. Aquí y en un solo sitio: si el normalizador de la búsqueda y el
     * de la importación discrepan, no se encuentra nada y nadie entiende por
     * qué.
     */
    public static function normalizar(string $texto): string
    {
        $sinTildes = \Normalizer::isNormalized($texto, \Normalizer::FORM_D)
            ? $texto
            : \Normalizer::normalize($texto, \Normalizer::FORM_D);

        return mb_strtolower(preg_replace('/\p{Mn}/u', '', (string) $sinTildes));
    }

    /**
     * Busca por nombre ignorando tildes y mayúsculas, de más valorado a menos:
     * quien escribe «silva» espera ver antes a los que conoce.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBuscar(Builder $query, string $texto): Builder
    {
        $normalizado = self::normalizar(trim($texto));

        if ($normalizado === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('search_name', 'like', '%'.$normalizado.'%')
            ->orderByDesc('overall_rating');
    }

    /**
     * La posición de esta liga que corresponde a la de EA, general y
     * específica. Una posición que EA añada y aquí no esté devuelve nulos en
     * vez de inventarse una: el formulario la deja vacía y la elige una
     * persona.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function posicionEnLaLiga(): array
    {
        return self::POSICIONES[$this->position] ?? [null, null];
    }
}
