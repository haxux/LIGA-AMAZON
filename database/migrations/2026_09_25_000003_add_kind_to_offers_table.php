<?php

use App\Models\Offer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una oferta del chat deja de ser sólo «te compro a éste».
 *
 * Entre técnicos se negocian las mismas cuatro operaciones que un técnico
 * propone a la dirección —comprar, vender, pedir cedido y ceder—, con la
 * diferencia de que aquí las dos puntas son clubes de la liga. Las cesiones no
 * tienen coste: lo que se pacta es el plazo.
 *
 * `kind` y no `type` para no confundirla con el tipo de un traspaso, que es
 * otra cosa: una oferta es una conversación, un traspaso es un hecho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('kind', 16)->default(Offer::KIND_BUY)->after('player_id');
            $table->string('loan_term', 8)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['kind', 'loan_term']);
        });
    }
};
