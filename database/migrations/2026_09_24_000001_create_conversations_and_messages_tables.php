<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 13: el chat.
 *
 * Una conversación es de dos personas, y hay UNA por pareja: dos hilos entre
 * los mismos dos partirían la historia en dos y el contador de no leídos
 * dejaría de significar nada. El unique de la tabla pivote impide además que
 * alguien entre dos veces en el mismo hilo.
 *
 * `last_read_at` vive en la pertenencia y no en el mensaje: lo leído es
 * distinto para cada uno de los dos, y guardarlo por mensaje serían tantas
 * filas como mensajes por participante para responder a una pregunta —cuántos
 * me faltan— que se contesta con una fecha.
 *
 * Sin adjuntos y sin websockets (decisiones cerradas): el hilo se refresca por
 * sondeo, que para una liga de doce clubes sobra y no añade infraestructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
