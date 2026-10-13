<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una pertenencia a una plantilla deja de borrarse al salir el jugador: se
 * CIERRA con la fecha en que salió.
 *
 * Borrarla destruía la única prueba de que ese jugador estuvo ahí, y con ella
 * la posibilidad de anotar un evento de un partido anterior a su marcha: el
 * formulario preguntaba «¿quién está hoy en la plantilla?» cuando la pregunta
 * correcta era «¿quién estuvo cuando se jugó?». Pasó con Ola Aina, vendido al
 * Barcelona, y pasaba igual con un traspaso DENTRO de la liga, donde el jugador
 * no se va a ninguna parte: simplemente deja de constar en su club anterior.
 *
 * El dorsal se suelta al cerrar (decisión del propietario): otro puede llevar
 * el 9 esa misma temporada. De ahí que la columna pase a admitir nulo, lo que
 * además deja convivir varias filas cerradas bajo el único de
 * (team_id, shirt_number) — en SQL, varios NULL no chocan entre sí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squad_memberships', function (Blueprint $table): void {
            $table->timestamp('left_at')->nullable()->after('is_captain');
            $table->unsignedTinyInteger('shirt_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('squad_memberships', function (Blueprint $table): void {
            $table->dropColumn('left_at');
        });
    }
};
