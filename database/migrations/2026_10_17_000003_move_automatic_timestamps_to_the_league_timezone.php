<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La aplicación pasa de UTC a la hora de la liga (`config/app.php`), y lo que
 * ella misma escribió con `now()` estando en UTC se corre a la hora nueva.
 *
 * Sin esto, cada marca automática anterior al cambio se leería como si ya
 * fuera hora de Bogotá, cinco horas adelantada: el chat vería «leído» un hilo
 * hasta cinco horas después de lo que de verdad se leyó, y daría por vistos
 * mensajes que nadie abrió.
 *
 * Se corren SÓLO las marcas que pone la aplicación: `created_at`/`updated_at`
 * de cualquier tabla y las que se escriben con `now()` (lista de abajo). Las
 * que teclea una persona —`games.kickoff_at`, `news.published_at`, fechas de
 * nacimiento y de temporada— ya se escribieron con la hora de Bogotá en la
 * cabeza: el cambio de zona es justo lo que hace que por fin se lean bien, y
 * correrlas las estropearía.
 *
 * El desfase sale de la zona configurada, así que con `APP_TIMEZONE=UTC` esto
 * no hace nada. Una base recién creada está vacía y tampoco.
 */
return new class extends Migration
{
    /**
     * Las marcas automáticas que no se llaman `created_at` ni `updated_at`.
     *
     * @var array<string, list<string>>
     */
    private const AUTOMATIC = [
        'conversation_user' => ['last_read_at'],
        'games' => ['voided_at'],
        'news' => ['notified_at'],
        'game_reminders' => ['sent_at'],
        'squad_memberships' => ['left_at'],
        'users' => ['email_verified_at'],
        'failed_jobs' => ['failed_at'],
    ];

    public function up(): void
    {
        $this->shift($this->offsetHours());
    }

    public function down(): void
    {
        $this->shift(-$this->offsetHours());
    }

    /**
     * Las horas que separan la zona de la liga de UTC: -5 para Bogotá, que no
     * tiene horario de verano.
     */
    private function offsetHours(): int
    {
        return intdiv(now('UTC')->setTimezone(config('app.timezone'))->utcOffset(), 60);
    }

    private function shift(int $hours): void
    {
        if ($hours === 0) {
            return;
        }

        // La base de la aplicación y ninguna otra: sin nombrarla, en MariaDB
        // `getTables()` devuelve las de todas las bases que el usuario ve, y en
        // un alojamiento compartido puede haber más de una.
        $schema = DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName();

        foreach (Schema::getTables($schema) as $table) {
            $name = $table['name'];
            $wanted = ['created_at', 'updated_at', ...(self::AUTOMATIC[$name] ?? [])];

            // Por tipo y no sólo por nombre: en `jobs` y `job_batches`,
            // `created_at` es un entero con la marca Unix, que no tiene zona.
            $columns = collect(Schema::getColumns($name))
                ->filter(fn (array $column): bool => in_array($column['name'], $wanted, true)
                    && in_array(strtolower($column['type_name']), ['timestamp', 'datetime'], true))
                ->pluck('name');

            if ($columns->isEmpty()) {
                continue;
            }

            DB::table($name)->update($columns->mapWithKeys(
                fn (string $column): array => [$column => DB::raw($this->plus($column, $hours))],
            )->all());
        }
    }

    private function plus(string $column, int $hours): string
    {
        $wrapped = DB::getQueryGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'sqlite' => "datetime({$wrapped}, '{$hours} hours')",
            default => "{$wrapped} + interval {$hours} hour",
        };
    }
};
