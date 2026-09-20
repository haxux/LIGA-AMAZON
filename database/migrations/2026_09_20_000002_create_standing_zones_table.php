<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion / relegation / European bands on the league table, defined per
 * division because that is where they differ: Primera relegates and hands out
 * European places, Segunda promotes and relegates.
 *
 * Positions are stored, not team ids: a band is a rule about the table's
 * shape, and the table is derived live (StandingsService persists nothing).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('standing_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('color', 20);
            $table->unsignedTinyInteger('from_position');
            $table->unsignedTinyInteger('to_position');
            $table->timestamps();

            $table->unique(['division_id', 'label']);
            $table->index(['division_id', 'from_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standing_zones');
    }
};
