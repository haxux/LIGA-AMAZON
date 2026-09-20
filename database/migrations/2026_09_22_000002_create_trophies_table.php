<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: los trofeos de un club.
 *
 * Cuelgan del CLUB y no del equipo-temporada: un título se gana en una
 * temporada concreta —de ahí `season_id`— pero se exhibe en todas, que es
 * justamente la razón de que la Fase 9 creara la entidad Club.
 *
 * El mismo club no puede ganar dos veces el mismo título en la misma
 * temporada; dos clubes sí pueden ganar el mismo título en temporadas
 * distintas, que es lo normal.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trophies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['club_id', 'season_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trophies');
    }
};
