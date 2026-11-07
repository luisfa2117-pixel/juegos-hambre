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
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->string('text');
            $table->unsignedTinyInteger('participants');

            // Cambios de stats: negativo = pierde, positivo = gana
            $table->integer('actor_hp')->default(0);       // {A}
            $table->integer('actor_energy')->default(0);
            $table->integer('target_hp')->default(0);      // {B}, {C}, {D}
            $table->integer('target_energy')->default(0);

            $table->foreignId('gives_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('requires_item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->timestamps();

            $table->unique(['category', 'text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
