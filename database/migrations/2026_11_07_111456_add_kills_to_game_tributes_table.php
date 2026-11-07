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
    Schema::table('game_tributes', function (Blueprint $table) {
        $table->unsignedSmallInteger('kills')->default(0);
        $table->string('killed_by')->nullable();   // "Dean" o "Avery y Avril"; null = accidente o sobrevivió
    });
}

public function down(): void
{
    Schema::table('game_tributes', function (Blueprint $table) {
        $table->dropColumn(['kills', 'killed_by']);
    });
}
};
