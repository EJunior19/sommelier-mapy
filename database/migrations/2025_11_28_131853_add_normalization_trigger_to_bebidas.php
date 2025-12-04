<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION normalizar_bebida_f()
            RETURNS trigger AS $$
            DECLARE
                nome text;
                marca text;
            BEGIN
                nome := lower(unaccent(NEW.nombre));
                nome := regexp_replace(nome, '[^a-z0-9 ]', ' ', 'g');
                nome := regexp_replace(nome, '\\s+', ' ', 'g');
                NEW.nome_limpo := trim(nome);

                IF NEW.marca IS NOT NULL THEN
                    marca := lower(unaccent(NEW.marca));
                    marca := regexp_replace(marca, '[^a-z0-9 ]', ' ', 'g');
                    marca := regexp_replace(marca, '\\s+', ' ', 'g');
                    NEW.marca_normalizada := trim(marca);
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_normalizar_bebida ON bebidas;

            CREATE TRIGGER trg_normalizar_bebida
            BEFORE INSERT OR UPDATE OF nombre, marca
            ON bebidas
            FOR EACH ROW
            EXECUTE FUNCTION normalizar_bebida_f();
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_normalizar_bebida ON bebidas;");
        DB::unprepared("DROP FUNCTION IF EXISTS normalizar_bebida_f();");
    }
};
