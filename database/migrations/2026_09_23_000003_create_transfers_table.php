<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 12: fichajes, ventas y préstamos (design D8).
 *
 * Un traspaso entre dos clubes de la liga es UNA fila leída desde los dos
 * lados, no dos: dos filas es cómo se llega a que un club haya vendido un
 * jugador que nadie compró. De ahí `from_club_id` y `to_club_id` en la misma
 * fila, y `external_club` como texto cuando una de las dos puntas está fuera.
 *
 * `budget_movements.transfer_id` ata el dinero al traspaso que lo generó: si el
 * traspaso se borra, sus movimientos se van con él y el saldo vuelve solo. Lo
 * que NO vuelve sola es la plantilla, y la confirmación de borrado lo dice.
 *
 * `players.left_at` y `left_to` son la salida de la liga (design D9): el
 * jugador se marca como salido y su fila se conserva, porque sus `game_events`
 * cuelgan de él con borrado en cascada y borrarlo reescribiría los goleadores
 * históricos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('scope', 16);
            $table->foreignId('from_club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->foreignId('to_club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('external_club')->nullable();
            $table->unsignedBigInteger('fee')->default(0);
            // El plazo de un préstamo se anota como dato y no vence solo: este
            // despliegue no tiene tareas programadas (decisión cerrada).
            $table->string('loan_term', 8)->nullable();
            $table->timestamps();

            $table->index(['season_id', 'type']);
        });

        Schema::table('budget_movements', function (Blueprint $table) {
            $table->foreignId('transfer_id')->nullable()->after('season_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->date('left_at')->nullable()->after('market_value');
            $table->string('left_to')->nullable()->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['left_at', 'left_to']);
        });

        Schema::table('budget_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_id');
        });

        Schema::dropIfExists('transfers');
    }
};
