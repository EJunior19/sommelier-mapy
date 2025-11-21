<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memoria_global', function (Blueprint $table) {
            $table->id();
            $table->longText('contexto')->nullable(); // histórico geral de conversas
            $table->text('ultima_interacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memoria_global');
    }
};
