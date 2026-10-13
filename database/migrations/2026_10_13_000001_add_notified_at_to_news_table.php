<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo se avisó por push de esta noticia.
 *
 * Hace falta una marca porque hay DOS caminos que pueden anunciarla: guardarla
 * ya publicada, y el barrido del cron para las programadas, que se publican
 * solas al llegar su fecha sin que nadie escriba nada. Sin la marca, editar el
 * título de una noticia vieja volvería a avisar a todo el mundo.
 *
 * Las que ya están publicadas al correr esto quedan marcadas como avisadas: son
 * historia, y estrenar la función mandando un push por cada noticia pasada
 * sería la peor manera de estrenarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('published_at');
        });

        DB::table('news')->whereNotNull('published_at')->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });
    }
};
