<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            if (!Schema::hasColumn('bebidas', 'nome_limpo')) {
                $table->string('nome_limpo')->nullable();
            }
            if (!Schema::hasColumn('bebidas', 'marca_normalizada')) {
                $table->text('marca_normalizada')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bebidas', function (Blueprint $table) {
            $table->dropColumn(['nome_limpo', 'marca_normalizada']);
        });
    }
};
