<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 13: las ofertas por un jugador, dentro del chat (design D10).
 *
 * Aceptar una oferta NO ejecuta nada: la marca como acordada y la hace aparecer
 * en la bandeja del administrador, que es quien registra el traspaso. Así sigue
 * habiendo una sola puerta de entrada al dinero y a las plantillas; si aceptar
 * ejecutara, habría dos caminos escribiendo lo mismo y uno de ellos sin nadie
 * mirando.
 *
 * `from_club_id` es el club que COMPRA, y se mantiene a lo largo de una
 * negociación aunque cada contraoferta la mueva uno distinto: quien quiere al
 * jugador no cambia porque el otro responda con otro precio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 16);
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            // El traspaso que la ejecutó, cuando el administrador la firma.
            $table->foreignId('transfer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
