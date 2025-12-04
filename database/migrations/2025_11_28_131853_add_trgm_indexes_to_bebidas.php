<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS idx_nome_limpo_gin ON bebidas USING gin (nome_limpo gin_trgm_ops);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_nome_limpo_gist ON bebidas USING gist (nome_limpo gist_trgm_ops);");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_marca_normalizada_gin ON bebidas USING gin (marca_normalizada gin_trgm_ops);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_marca_normalizada_gist ON bebidas USING gist (marca_normalizada gist_trgm_ops);");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_tipo_gin ON bebidas USING gin (tipo gin_trgm_ops);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_tipo_gist ON bebidas USING gist (tipo gist_trgm_ops);");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_nome_marca_gin
            ON bebidas USING gin ((nome_limpo || ' ' || COALESCE(marca_normalizada,'')) gin_trgm_ops);
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_nome_marca_gist
            ON bebidas USING gist ((nome_limpo || ' ' || COALESCE(marca_normalizada,'')) gist_trgm_ops);
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_nome_limpo_gin;");
        DB::statement("DROP INDEX IF EXISTS idx_nome_limpo_gist;");
        DB::statement("DROP INDEX IF EXISTS idx_marca_normalizada_gin;");
        DB::statement("DROP INDEX IF EXISTS idx_marca_normalizada_gist;");
        DB::statement("DROP INDEX IF EXISTS idx_tipo_gin;");
        DB::statement("DROP INDEX IF EXISTS idx_tipo_gist;");
        DB::statement("DROP INDEX IF EXISTS idx_nome_marca_gin;");
        DB::statement("DROP INDEX IF EXISTS idx_nome_marca_gist;");
    }
};
