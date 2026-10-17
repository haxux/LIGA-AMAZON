<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los comentarios de los técnicos en el detalle de un partido.
 *
 * Los escribe un técnico con sesión y los lee cualquiera. Si el partido o el
 * usuario se borran, el comentario se va con ellos: no tiene sentido suelto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();

            $table->index(['game_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_comments');
    }
};
