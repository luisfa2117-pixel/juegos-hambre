<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En qué arena se juega. Las partidas de antes, en la ciudad, que es donde se jugaban
        Schema::table('games', function (Blueprint $table) {
            $table->string('terrain', 20)->default('city');
        });

        // En qué arenas puede pasar cada evento y cada catástrofe, como ['city', 'desert'] (null = en todas)
        Schema::table('game_events', function (Blueprint $table) {
            $table->jsonb('terrains')->nullable();
        });

        Schema::table('arena_events', function (Blueprint $table) {
            $table->jsonb('terrains')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('terrain'));
        Schema::table('game_events', fn (Blueprint $table) => $table->dropColumn('terrains'));
        Schema::table('arena_events', fn (Blueprint $table) => $table->dropColumn('terrains'));
    }
};
