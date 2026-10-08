<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué avisos de calendario ya se mandaron de un partido — «faltan 3 días»,
 * «faltan 2», «faltan 1» y «ya se juega hoy»—, para no repetirlos cada vez que
 * el cron corre (una vez al día, pero nada impide correrlo a mano otra vez el
 * mismo día). Una fila por partido y tipo de aviso; su sola existencia es la
 * marca de "ya mandado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->timestamp('sent_at');

            $table->unique(['game_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_reminders');
    }
};
