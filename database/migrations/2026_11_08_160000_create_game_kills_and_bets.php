<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quién mató a quién, una fila por asesino (si lo matan dos, son dos filas).
        // killed_by ya lo dice en texto, pero así se puede contar: némesis, víctimas favoritas...
        Schema::create('game_kills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('killer_id')->constrained('game_tributes')->cascadeOnDelete();
            $table->foreignId('victim_id')->constrained('game_tributes')->cascadeOnDelete();
            $table->unsignedSmallInteger('round');

            $table->index(['killer_id', 'victim_id']);
        });

        // A quién apostaste en la partida (como mucho un tributo por partida)
        Schema::table('game_tributes', function (Blueprint $table) {
            $table->boolean('is_bet')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_kills');

        Schema::table('game_tributes', function (Blueprint $table) {
            $table->dropColumn('is_bet');
        });
    }
};
