<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sommelier_intencoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 100); // churrasco, carne, calor, doce, suave, festa etc.
            $table->text('resposta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sommelier_intencoes');
    }
};
