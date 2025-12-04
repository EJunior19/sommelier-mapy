<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_precio_stock
            ON bebidas (precio ASC, stock DESC);
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_precio_stock;");
    }
};
