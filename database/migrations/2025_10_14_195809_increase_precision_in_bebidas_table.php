<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            // Aumentamos la precisión (permite hasta 9999999999.99)
            $table->decimal('precio', 14, 2)->change();
            $table->decimal('stock', 14, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            $table->decimal('precio', 8, 2)->change();
            $table->decimal('stock', 10, 2)->change();
        });
    }
};
