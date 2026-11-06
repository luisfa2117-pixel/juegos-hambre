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
    Schema::create('districts', function (Blueprint $table) {
        $table->id();
        $table->string('slug')->unique();      // 'costa_este'
        $table->string('name');                // 'Ruinas de la Costa Este'
        $table->integer('strength_bonus')->default(0);
        $table->integer('energy_bonus')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
