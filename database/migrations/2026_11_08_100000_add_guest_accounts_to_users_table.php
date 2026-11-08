<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Las cuentas de invitado no tienen correo ni contraseña: no guardamos datos personales
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->boolean('is_guest')->default(false);
            $table->timestamp('last_seen_at')->nullable();   // para borrar los invitados que no vuelven
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_guest', 'last_seen_at']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
