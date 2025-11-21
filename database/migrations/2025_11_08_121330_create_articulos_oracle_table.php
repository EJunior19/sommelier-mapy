<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articulos_oracle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cod_articulo')->index();
            $table->integer('cod_rubro')->nullable();
            $table->string('descripcion', 255);
            $table->string('descripcion_articulo', 255)->nullable();
            $table->integer('existencia_actual')->default(0);
            $table->decimal('precio_usd_sin_iva', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos_oracle');
    }
};
