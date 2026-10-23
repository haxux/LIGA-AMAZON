<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «A Segunda»: el foro interno de técnicos y presidente (decisión del
 * propietario, 2026-10-23), dentro de Noticias y sólo para quien tiene sesión.
 *
 * Una respuesta es una publicación con `parent_id`: el mismo texto, el mismo
 * autor y las mismas reacciones, sin tabla aparte. Las reacciones son «me
 * gusta» (+1) y «no me gusta» (-1), una por persona y publicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wall_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wall_posts')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'created_at']);
        });

        Schema::create('wall_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wall_post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('wall_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wall_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['wall_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_reactions');
        Schema::dropIfExists('wall_images');
        Schema::dropIfExists('wall_posts');
    }
};
