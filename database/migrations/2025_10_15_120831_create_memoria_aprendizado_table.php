<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memoria_aprendizado', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 100); // ex: preferencia, bebida_popular
            $table->string('dado', 255); // ex: "bebidas doces", "vinho branco"
            $table->integer('contador')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memoria_aprendizado');
    }
};
