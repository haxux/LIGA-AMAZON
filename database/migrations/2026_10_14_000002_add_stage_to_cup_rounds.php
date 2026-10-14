<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las fases preliminares (o de clasificación): rondas eliminatorias que se
 * juegan ANTES de la fase de grupos, como las tres de la Libertadores. Hasta
 * ahora toda ronda era del cuadro final, que se juega después de los grupos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cup_rounds', function (Blueprint $table) {
            $table->string('stage', 16)->default('final')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('cup_rounds', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
