<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El capitanato lo elige el técnico, por temporada: va en la pertenencia
 * (`squad_memberships`) y no en el jugador, porque un jugador puede ser
 * capitán un año y no el siguiente, incluso en el mismo club.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squad_memberships', function (Blueprint $table) {
            $table->boolean('is_captain')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('squad_memberships', function (Blueprint $table) {
            $table->dropColumn('is_captain');
        });
    }
};
