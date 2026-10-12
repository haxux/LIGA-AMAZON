<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de jugadores de EA FC, para rellenar una ficha sin teclearla.
 *
 * Es un catálogo de CONSULTA, no parte del dominio: aquí no juega nadie. Un
 * jugador de la liga nace en `players` copiando lo que haga falta de aquí, y a
 * partir de ese momento son dos cosas distintas — si el administrador corrige
 * una media, corrige las dos, pero la liga no depende de este catálogo para
 * funcionar. Vaciarlo no rompe nada.
 *
 * Los datos NO viajan en el repositorio, que es público: se cargan en el
 * servidor con `php artisan fc:importar-catalogo`. Sólo el comando se versiona.
 *
 * Qué se guarda y qué no lo decidió el propietario campo a campo: entran la
 * identidad, la cabecera, las seis de portada y el perfil. Quedan fuera los 29
 * atributos detallados (centros, compostura, reflejos…), los PlayStyles, el
 * potencial y el valor de mercado — este último porque en esta liga lo fija la
 * dirección, no EA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fc_players', function (Blueprint $table) {
            $table->id();

            // ── Identidad ───────────────────────────────────────────────────
            // El id de EA, que es lo que permite reimportar con la edición
            // siguiente sin duplicar a nadie.
            $table->unsignedBigInteger('fc_id')->unique();
            $table->string('common_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            // Nombre normalizado —minúsculas y sin tildes— para buscar.
            // Una columna y no la colación de la base: `utf8mb4_unicode_ci`
            // ignora tildes en MariaDB, pero SQLite, que es donde corren las
            // pruebas, no. Sin esto, buscar «Ekitike» encuentra a Ekitiké en
            // producción y no en los tests, que es la peor de las situaciones.
            $table->string('search_name')->index();
            $table->string('nationality')->nullable();
            $table->string('club')->nullable();
            $table->string('league')->nullable();
            $table->string('gender', 16)->nullable();
            $table->date('birthdate')->nullable();

            // ── Cabecera ────────────────────────────────────────────────────
            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->string('position', 8)->nullable();
            $table->string('alternate_positions')->nullable();

            // ── Las seis de portada ─────────────────────────────────────────
            // En los porteros EA reutiliza estas casillas para estirada,
            // parada y demás, así que NO significan lo mismo. La ficha tendrá
            // que distinguirlo al mostrarlas.
            $table->unsignedTinyInteger('pace')->nullable();
            $table->unsignedTinyInteger('shooting')->nullable();
            $table->unsignedTinyInteger('passing')->nullable();
            $table->unsignedTinyInteger('dribbling')->nullable();
            $table->unsignedTinyInteger('defending')->nullable();
            $table->unsignedTinyInteger('physicality')->nullable();

            // ── Perfil ──────────────────────────────────────────────────────
            $table->string('preferred_foot', 8)->nullable();
            $table->unsignedTinyInteger('weak_foot')->nullable();
            $table->unsignedTinyInteger('skill_moves')->nullable();

            // ── Procedencia ─────────────────────────────────────────────────
            // De qué edición salió cada fila. Sin esto, dentro de un año nadie
            // sabrá si una media es de FC27 o de la siguiente.
            $table->string('edition', 16)->nullable();
            $table->date('snapshot_date')->nullable();

            // Alguien arregló esta ficha a mano, así que una reimportación la
            // deja en paz. Una marca explícita y no comparar `updated_at` con
            // `created_at`: dos corrcciones en el mismo segundo que la carga
            // son indistinguibles por fecha, y la corrección se perdía.
            $table->boolean('hand_edited')->default(false)->index();

            $table->timestamps();

            $table->index('overall_rating');
        });

        // La foto del jugador, que el administrador sube a mano: ninguna de las
        // fuentes de datos la trae, y las que publican caras lo hacen desde un
        // CDN ajeno que no tenemos permiso para usar.
        Schema::table('players', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('birth_date');
            // Deja dicho de qué ficha del catálogo salió, si salió de alguna.
            // Sin clave foránea a propósito: el catálogo se puede vaciar y
            // reimportar entero, y eso no debe arrastrarse a los jugadores.
            $table->unsignedBigInteger('fc_id')->nullable()->after('photo_path')->index();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'fc_id']);
        });

        Schema::dropIfExists('fc_players');
    }
};
