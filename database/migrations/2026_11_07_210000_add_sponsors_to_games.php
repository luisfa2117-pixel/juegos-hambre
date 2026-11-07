<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hasta qué ronda la has visto: lo visto ya ha pasado y no se puede cambiar
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('revealed_round')->default(1);
        });

        // Los regalos de patrocinadores: qué, a quién y al final de qué ronda
        Schema::create('game_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('after_round');   // llega al empezar la ronda siguiente
            $table->string('tribute_name');
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_gifts');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('revealed_round');
        });
    }
};
