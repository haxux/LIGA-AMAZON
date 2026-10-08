<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una suscripción push del navegador (Push API), identificada por su
 * `endpoint` — lo que el Service Worker entrega al suscribirse, único por
 * navegador y no por persona.
 *
 * `topics` es a qué se suscribió ESE navegador: "partidos" no pide sesión —
 * cualquier visitante lo activa desde el sitio público—, pero "chat" sí, y
 * por eso sólo esas filas llevan `user_id`. Un mismo navegador puede estar
 * suscrito a los dos a la vez si quien lo usa es también un técnico: se
 * guarda como una fila con ambos en el JSON, no dos filas con el mismo
 * endpoint, que el unique de abajo impediría de todos modos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->json('topics');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
