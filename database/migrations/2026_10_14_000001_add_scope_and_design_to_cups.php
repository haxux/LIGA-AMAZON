<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copas nacionales e internacionales.
 *
 * Las nacionales siguen como estaban. Una internacional lleva además un diseño
 * de entre los que la aplicación conoce (`design`: Libertadores, Sudamericana),
 * y dos imágenes oficiales que sube el administrador —el logo y el fondo—,
 * porque los emblemas reales no se dibujan aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cups', function (Blueprint $table) {
            $table->string('scope', 16)->default('national')->after('name');
            $table->string('design', 32)->nullable()->after('scope');
            $table->string('logo_path')->nullable()->after('design');
            $table->string('background_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('cups', function (Blueprint $table) {
            $table->dropColumn(['scope', 'design', 'logo_path', 'background_path']);
        });
    }
};
