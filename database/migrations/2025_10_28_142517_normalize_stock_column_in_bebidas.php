<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 🧹 1. Arredonda valores para inteiros (evita erro de conversão)
        DB::statement("UPDATE public.bebidas SET stock = ROUND(stock);");

        // ⚙️ 2. Altera o tipo da coluna para INTEGER
        Schema::table('bebidas', function (Blueprint $table) {
            $table->integer('stock')->nullable()->change();
        });
    }

    public function down(): void
    {
        // 🔙 Reverte o tipo para decimal (caso precise desfazer)
        Schema::table('bebidas', function (Blueprint $table) {
            $table->decimal('stock', 14, 2)->nullable()->change();
        });
    }
};
