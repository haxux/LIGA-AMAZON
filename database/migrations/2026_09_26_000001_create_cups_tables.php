<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 15: las copas.
 *
 * Una copa es una competición de la temporada que NO es una división: cruza
 * equipos de divisiones distintas, se juega por rondas y no por jornadas
 * numeradas, y termina en un cuadro en vez de en una tabla. Por eso entra al
 * lado de `divisions` y no estirándola hasta que sirva para las dos cosas.
 *
 * El formato lo elige el administrador por copa (decisión del propietario): las
 * hay de cuadro directo y las hay con fase de grupos antes. Los partidos por
 * eliminatoria se eligen por RONDA, que es como se juegan de verdad — una final
 * a partido único con semifinales a ida y vuelta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('has_group_stage')->default(false);
            $table->timestamps();

            $table->unique(['season_id', 'name']);
        });

        Schema::create('cup_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->timestamps();

            $table->unique(['cup_id', 'name']);
        });

        // Los participantes. Un equipo entra una vez en una copa, con su grupo
        // si la copa lo tiene: `unique` lo impide por descuido.
        Schema::create('cup_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cup_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['cup_id', 'team_id']);
        });

        Schema::create('cup_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // El orden lo pone el administrador: octavos antes que cuartos, y
            // la final la última. No se deduce del nombre.
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('legs')->default(1);
            $table->timestamps();

            $table->unique(['cup_id', 'position']);
        });

        Schema::create('cup_ties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cup_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            // Quién pasó. Se rellena solo cuando el global no deja dudas, y a
            // mano —con su motivo— cuando la eliminatoria acaba empatada.
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cup_ties');
        Schema::dropIfExists('cup_rounds');
        Schema::dropIfExists('cup_teams');
        Schema::dropIfExists('cup_groups');
        Schema::dropIfExists('cups');
    }
};
