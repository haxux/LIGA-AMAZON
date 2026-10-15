<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El buzón de sugerencias: ideas de los técnicos para mejorar el proyecto.
 *
 * Una sugerencia es de quien la escribió y se conserva aunque el administrador
 * la lea o la aplique: es la memoria de lo que los técnicos han pedido. Si el
 * usuario se borra, la sugerencia se va con él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->string('status', 12)->default('nueva');
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
