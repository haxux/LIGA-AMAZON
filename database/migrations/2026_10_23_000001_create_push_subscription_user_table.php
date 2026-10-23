<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quién avisa del chat una suscripción push: ahora puede ser a VARIAS
 * personas, las cuentas abiertas en ese navegador (decisión del propietario,
 * 2026-10-23, con el selector de cuentas). Un mismo teléfono con la cuenta de
 * técnico y la de administrador recibe los mensajes de las dos; con un solo
 * `user_id`, la última en apuntarse dejaba a la otra sin avisos.
 *
 * `push_subscriptions.user_id` se queda (lo leen aún los listados del panel y
 * se rellena con la cuenta activa), pero quién recibe el chat sale de aquí.
 * Las que ya había se copian tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscription_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['push_subscription_id', 'user_id']);
        });

        DB::table('push_subscriptions')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->each(fn ($row) => DB::table('push_subscription_user')->insert([
                'push_subscription_id' => $row->id,
                'user_id' => $row->user_id,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscription_user');
    }
};
