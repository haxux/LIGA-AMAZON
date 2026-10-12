<?php

namespace App\Console\Commands;

use App\Models\FcPlayer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo de jugadores de EA FC desde un CSV.
 *
 * El fichero NO vive en el repositorio, que es público: redistribuir una
 * recopilación ajena es otra cosa que usarla. Aquí se versiona el comando; el
 * CSV se baja aparte y se le pasa por parámetro.
 *
 *     php artisan fc:importar-catalogo ~/fc27.csv
 *
 * Se puede correr tantas veces como haga falta: cada fila se identifica por el
 * `player_id` de EA, así que reimportar actualiza en vez de duplicar. Eso es lo
 * que permitirá cargar la edición siguiente encima de ésta.
 *
 * Las correcciones a mano del administrador se respetan salvo que se pida
 * `--pisar`: si alguien arregló una media mal puesta, una reimportación no
 * debería deshacerlo sin avisar.
 */
class ImportarCatalogoFc extends Command
{
    protected $signature = 'fc:importar-catalogo
        {fichero : Ruta del CSV de EA FC}
        {--pisar : Sobrescribe también las fichas corregidas a mano}
        {--vaciar : Borra el catálogo antes de cargar}';

    protected $description = 'Carga el catálogo de jugadores de EA FC desde un CSV, para rellenar fichas sin teclearlas';

    /**
     * Columnas del CSV que se guardan, y con qué nombre. Lo que no está aquí
     * se descarta a propósito: los 29 atributos detallados, los PlayStyles, el
     * potencial y el valor de mercado quedaron fuera por decisión del
     * propietario.
     *
     * @var array<string, string>
     */
    private const COLUMNAS = [
        'player_id' => 'fc_id',
        'common_name' => 'common_name',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'nationality' => 'nationality',
        'club' => 'club',
        'league' => 'league',
        'gender' => 'gender',
        'overall_rating' => 'overall_rating',
        'position' => 'position',
        'alternate_positions' => 'alternate_positions',
        'pace' => 'pace',
        'shooting' => 'shooting',
        'passing' => 'passing',
        'dribbling' => 'dribbling',
        'defending' => 'defending',
        'physicality' => 'physicality',
        'preferred_foot' => 'preferred_foot',
        'weak_foot' => 'weak_foot',
        'skill_moves' => 'skill_moves',
        'edition' => 'edition',
    ];

    public function handle(): int
    {
        $ruta = (string) $this->argument('fichero');

        if (! is_file($ruta)) {
            $this->error("No existe el fichero: {$ruta}");

            return self::FAILURE;
        }

        if ($this->option('vaciar')) {
            FcPlayer::query()->delete();
            $this->warn('Catálogo vaciado.');
        }

        $manejador = fopen($ruta, 'r');
        $cabecera = fgetcsv($manejador);

        if ($cabecera === false) {
            $this->error('El fichero está vacío.');
            fclose($manejador);

            return self::FAILURE;
        }

        $cabecera = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\u{FEFF}"), $cabecera);
        $faltan = array_diff(['player_id', 'first_name', 'last_name'], $cabecera);

        if ($faltan !== []) {
            $this->error('Al CSV le faltan columnas imprescindibles: '.implode(', ', $faltan));
            fclose($manejador);

            return self::FAILURE;
        }

        // Las corregidas a mano llevan su marca puesta por el modelo. Se leen
        // de una vez para no consultar fila a fila.
        $corregidas = $this->option('pisar')
            ? []
            : FcPlayer::query()->where('hand_edited', true)->pluck('fc_id')->all();

        $corregidas = array_flip($corregidas);

        $lote = [];
        $leidas = 0;
        $respetadas = 0;
        $ahora = now();

        $barra = $this->output->createProgressBar();
        $barra->start();

        while (($fila = fgetcsv($manejador)) !== false) {
            $leidas++;
            $cruda = @array_combine($cabecera, array_pad($fila, count($cabecera), null));

            if ($cruda === false || blank($cruda['player_id'] ?? null)) {
                continue;
            }

            if (isset($corregidas[(int) $cruda['player_id']])) {
                $respetadas++;

                continue;
            }

            $lote[] = $this->traducir($cruda, $ahora);

            if (count($lote) >= 500) {
                $this->guardar($lote);
                $barra->advance(count($lote));
                $lote = [];
            }
        }

        if ($lote !== []) {
            $this->guardar($lote);
            $barra->advance(count($lote));
        }

        fclose($manejador);
        $barra->finish();
        $this->newLine(2);

        $this->info('Filas leídas:        '.number_format($leidas));
        $this->info('En el catálogo:      '.number_format(FcPlayer::query()->count()));

        if ($respetadas > 0) {
            $this->warn('Corregidas a mano, respetadas: '.number_format($respetadas).'  (--pisar las sobrescribe)');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $cruda
     * @return array<string, mixed>
     */
    private function traducir(array $cruda, Carbon $ahora): array
    {
        $fila = ['created_at' => $ahora, 'updated_at' => $ahora];

        foreach (self::COLUMNAS as $origen => $destino) {
            $valor = $cruda[$origen] ?? null;
            $fila[$destino] = blank($valor) ? null : $valor;
        }

        $fila['fc_id'] = (int) $fila['fc_id'];
        $fila['birthdate'] = $this->fecha($cruda['birthdate'] ?? null);
        $fila['snapshot_date'] = $this->fecha($cruda['snapshot_date'] ?? null);

        $fila['search_name'] = FcPlayer::normalizar(trim(implode(' ', array_filter([
            $fila['common_name'], $fila['first_name'], $fila['last_name'],
        ]))));

        return $fila;
    }

    /**
     * EA mezcla formatos entre ediciones: FC26 trae `2003-06-29` y FC27
     * `04-02-1994`. Se prueban los dos antes de rendirse, porque una fecha mal
     * leída no falla: deja a todo el mundo naciendo el mismo día.
     */
    private function fecha(mixed $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat($formato, (string) $valor);

            if ($fecha !== false && $fecha->format($formato) === (string) $valor) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lote
     */
    private function guardar(array $lote): void
    {
        DB::table('fc_players')->upsert(
            $lote,
            ['fc_id'],
            array_values(array_diff(array_keys($lote[0]), ['fc_id', 'created_at'])),
        );
    }
}
