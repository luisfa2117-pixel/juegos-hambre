<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_tributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            // Copia de cómo era el personaje en esta partida
            $table->string('name');
            $table->string('gender');
            $table->foreignId('district_id')->constrained()->restrictOnDelete();

            $table->unsignedSmallInteger('died_in_round')->nullable();   // null = sobrevivió
            $table->unsignedTinyInteger('placement');                     // 1 = ganador
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_tributes');
    }
};
