<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las catástrofes de los Vigilantes: un nombre y el anuncio con el que empiezan
        Schema::create('arena_events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('intro');
            $table->timestamps();
        });

        // Cada catástrofe tiene sus propios eventos (lo que le pasa a cada tributo)
        Schema::table('game_events', function (Blueprint $table) {
            $table->foreignId('arena_event_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arena_event_id');
        });

        Schema::dropIfExists('arena_events');
    }
};
