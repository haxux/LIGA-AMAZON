<?php

use App\Models\Transfer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un traspaso puede ser ahora una PROPUESTA del técnico.
 *
 * Hasta aquí guardar un traspaso lo ejecutaba siempre, porque sólo lo escribía
 * el administrador. El técnico propone desde su panel la misma operación —mismo
 * jugador, mismo importe, mismo ámbito— y el administrador la firma o la
 * rechaza; hasta entonces no mueve ni plantilla ni dinero.
 *
 * Se resuelve con una columna en `transfers` y no con una tabla de propuestas
 * aparte, por lo mismo que un traspaso entre clubes es UNA fila: dos tablas con
 * las mismas columnas se separan, y el historial del club tendría que leerlas
 * las dos para contar una sola historia.
 *
 * Lo que ya existe queda como ejecutado, que es lo que es.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('status', 16)->default(Transfer::STATUS_EXECUTED)->after('type');
            $table->foreignId('proposed_by')->nullable()->after('loan_term')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropColumn('status');
        });
    }
};
