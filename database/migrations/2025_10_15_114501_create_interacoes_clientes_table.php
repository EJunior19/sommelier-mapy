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
        Schema::create('interacoes_clientes', function (Blueprint $table) {
        $table->id();
        $table->string('tipo')->nullable(); // Ex: "pedido", "preferencia", "humor"
        $table->text('entrada'); // fala do cliente
        $table->text('resposta'); // resposta do sommelier
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interacoes_clientes');
    }
};
