<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Las reglas especiales, como ['armed', 'half_life']. Las partidas de antes no tienen ninguna
            $table->jsonb('rules')->default('[]');
            // Si las reglas las eligió el Vasallaje de los 25 (para mostrarlo así en la partida)
            $table->boolean('quell')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['rules', 'quell']);
        });
    }
};
