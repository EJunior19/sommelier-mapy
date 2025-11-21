<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            // Si no existen aún, las agregamos
            if (!Schema::hasColumn('bebidas', 'codigo_barras')) {
                $table->string('codigo_barras')->nullable()->unique();
            }
            if (!Schema::hasColumn('bebidas', 'stock')) {
                $table->integer('stock')->default(0);
            }
            if (!Schema::hasColumn('bebidas', 'precio')) {
                $table->decimal('precio', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('bebidas', 'tipo')) {
                $table->string('tipo')->nullable();
            }
            if (!Schema::hasColumn('bebidas', 'alcohol')) {
                $table->boolean('alcohol')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            $table->dropColumn(['codigo_barras', 'stock', 'precio', 'tipo', 'alcohol']);
        });
    }
};
