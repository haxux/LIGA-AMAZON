<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: el panel deja de tener un solo tipo de usuario.
 *
 * `role` en lugar de un `is_admin` booleano, por lo que ya argumentaba el
 * diseño D9 de la Fase 7: `canAccessPanel()` es una puerta, no un permiso, y
 * un `is_admin` habría que concedérselo igualmente al técnico para que entrara
 * en su propio panel, con lo que el nombre de la columna sería mentira.
 *
 * `club_id` es nulo para el administrador y obligatorio para el técnico; la
 * invariante vive en `User::booted()`, no aquí, porque una restricción de base
 * de datos no puede expresar "obligatorio sólo si el rol es este".
 *
 * Los usuarios existentes quedan como administradores: es lo único que había.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(User::ROLE_ADMIN)->after('email');
            $table->foreignId('club_id')->nullable()->after('role')
                ->constrained()->restrictOnDelete();
        });

        DB::table('users')->update(['role' => User::ROLE_ADMIN]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
            $table->dropColumn('role');
        });
    }
};
