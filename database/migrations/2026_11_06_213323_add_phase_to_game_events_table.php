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
    Schema::table('game_events', function (Blueprint $table) {
        // null = puede pasar de día o de noche
        $table->string('phase')->nullable()->index();
    });
}

public function down(): void
{
    Schema::table('game_events', function (Blueprint $table) {
        $table->dropColumn('phase');
    });
}
};
