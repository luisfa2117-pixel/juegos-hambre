<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // nullable para no romper los personajes que ya existen (ver el comando user:claim)
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            // Antes el nombre era único en toda la tabla; ahora, solo entre los personajes de cada usuario
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('name');
        });
    }
};
