<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 12: el presupuesto de un club, como libro de movimientos.
 *
 * El saldo NO se guarda: se deriva de `clubs.initial_balance` más los ingresos
 * aprobados menos los egresos aprobados (design D7). Es la misma decisión que
 * rige la clasificación desde la Fase 4 —se deriva, no se guarda como fuente de
 * verdad (ARQUITECTURA.md §3)—, y por el mismo motivo: un saldo guardado se
 * desincroniza en cuanto un movimiento se edita o se rechaza, y nadie se entera.
 *
 * `status` existe porque el técnico PROPONE y el administrador aprueba: lo
 * propuesto no cuenta en el saldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->bigInteger('initial_balance')->default(0)->after('founded_year');
        });

        Schema::create('budget_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            // La temporada en la que ocurre, para poder filtrar el libro por
            // ella. El saldo, en cambio, es acumulado: el dinero de un club no
            // se reinicia al empezar el año.
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->string('reason');
            $table->string('status', 16);
            // Quién lo creó: un técnico proponiendo, o el administrador. Se
            // conserva aunque la cuenta desaparezca, porque el movimiento sigue
            // siendo cierto.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_movements');

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('initial_balance');
        });
    }
};
