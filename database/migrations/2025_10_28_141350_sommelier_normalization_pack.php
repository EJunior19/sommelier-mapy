<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1️⃣ Extensão necessária
        DB::unprepared("CREATE EXTENSION IF NOT EXISTS unaccent;");

        // 2️⃣ Adiciona colunas de normalização se não existirem
        Schema::table('bebidas', function (Blueprint $t) {
            if (!Schema::hasColumn('bebidas','marca'))       $t->string('marca', 120)->nullable()->index();
            if (!Schema::hasColumn('bebidas','volume_ml'))   $t->integer('volume_ml')->nullable()->index();
            if (!Schema::hasColumn('bebidas','nome_limpo'))  $t->string('nome_limpo', 255)->nullable()->index();
        });

        // 3️⃣ Cria tabelas dicionário
        DB::unprepared("
            CREATE TABLE IF NOT EXISTS sommelier_tipo_keywords (
              tipo    varchar(50) NOT NULL,
              keyword varchar(80) NOT NULL,
              PRIMARY KEY (tipo, keyword)
            );

            CREATE TABLE IF NOT EXISTS sommelier_brand_aliases (
              marca varchar(120) NOT NULL,
              alias varchar(120) NOT NULL,
              PRIMARY KEY (marca, alias)
            );

            CREATE INDEX IF NOT EXISTS idx_brand_alias ON sommelier_brand_aliases (alias);
            CREATE INDEX IF NOT EXISTS idx_tipo_kw     ON sommelier_tipo_keywords (keyword);
        ");

        // 4️⃣ Insere seeds básicas
        DB::unprepared("
            INSERT INTO sommelier_tipo_keywords (tipo, keyword) VALUES
            ('vinho','vinho'),('vinho','vino'),('vinho','tinto'),('vinho','blanco'),
            ('vinho','rose'),('vinho','rosé'),('vinho','malbec'),('vinho','cab'),
            ('vinho','cabernet'),('vinho','carmenere'),('vinho','merlot'),('vinho','syrah'),
            ('vinho','pinot'),('vinho','tempranillo'),('vinho','chardonnay'),('vinho','sauv'),
            ('espumante','espumante'),('espumante','champ'),('espumante','cava'),
            ('espumante','prosecco'),('espumante','brut'),('espumante','moscato'),
            ('cerveja','cerveja'),('cerveja','cerveza'),('cerveja','beer'),
            ('whisky','whisky'),('whisky','scotch'),('whisky','bourbon'),
            ('vodka','vodka'),('gin','gin'),('rum','rum'),('tequila','tequila'),
            ('licor','licor'),('brandy','brandy')
            ON CONFLICT DO NOTHING;

            INSERT INTO sommelier_brand_aliases (marca, alias) VALUES
            ('Freixenet','freixenet'),('Codorníu','codorniu'),('Corona','corona'),
            ('Absolut','absolut'),('Ciroc','ciroc'),
            ('Jack Daniel''s','jack daniels'),('Johnnie Walker','johnnie walker'),
            ('Buchanan''s','buchanans'),('Santa Helena','sta.helena'),
            ('Luigi Bosca','luigi bosca'),('Concha y Toro','concha y toro'),
            ('Viu Manent','viu manent'),('Finca La Linda','finca la linda'),
            ('Woodford Reserve','woodford reserve'),('Budweiser','budweiser'),
            ('San Martin','san martin'),('Licor 43','licor 43'),
            ('Bardera','bardera'),('Carlos I','carlos i'),
            ('Yellow Rose','yellow rose'),('Bertoni','bertoni'),
            ('Sernova','sernova'),('JP Chenet','jp chenet'),
            ('Exportación','exportacion')
            ON CONFLICT DO NOTHING;
        ");

        // 5️⃣ Cria função de normalização
        DB::unprepared(<<<'SQL'
      CREATE OR REPLACE FUNCTION normalize_bebida(p_nome text)
      RETURNS TABLE(tipo text, marca text, volume_ml int, nome_limpo text)
      LANGUAGE plpgsql AS
      $$
      DECLARE
        nome_raw   text := trim(p_nome);
        nome_base  text := lower(unaccent(nome_raw));
        v_ml       int;
        v_tipo     text;
        v_marca    text;
        v_nome     text;
        v_num      text;
      BEGIN
        IF nome_raw IS NULL OR nome_raw = '' THEN RETURN; END IF;

        v_ml := NULL;

        -- caso 750ML
        IF nome_base ~ '(\d{2,4})\s*ml' THEN
          v_ml := (regexp_match(nome_base, '(\d{2,4})\s*ml'))[1]::int;
        END IF;

        -- caso 1L / 4,5L / 0.7L
        IF v_ml IS NULL AND nome_base ~ '(\d+(?:[.,]\d+)?)\s*l(?![a-z])' THEN
          v_num := (regexp_match(nome_base, '(\d+(?:[.,]\d+)?)\s*l(?![a-z])'))[1];
          v_num := replace(v_num, ',', '.');  -- ← 🔧 aqui converte vírgula em ponto
          v_ml := round((v_num::numeric * 1000))::int;
        END IF;

        SELECT tk.tipo INTO v_tipo
        FROM sommelier_tipo_keywords tk
        WHERE nome_base LIKE '%'||tk.keyword||'%'
        ORDER BY CASE WHEN tk.tipo='espumante' THEN 0 ELSE 1 END, length(tk.keyword) DESC
        LIMIT 1;

        SELECT ba.marca INTO v_marca
        FROM sommelier_brand_aliases ba
        WHERE nome_base LIKE '%'||ba.alias||'%'
        ORDER BY length(ba.alias) DESC LIMIT 1;

        IF v_marca IS NULL THEN
          v_marca := initcap(trim(regexp_replace(nome_raw,
            '(?i)\b(vinho|vino|espumante|champ|cerveja|beer|whisky|vodka|gin|rum|tequila|licor|brandy)\b.*$','')));
          IF v_marca = '' THEN v_marca := NULL; END IF;
        END IF;

        v_nome := initcap(trim(regexp_replace(nome_raw, '\s+', ' ', 'g')));
        v_nome := regexp_replace(v_nome, '(\d{2,4})\s*(ML|Ml|ml)', '\1 ml', 'g');

        RETURN QUERY SELECT v_tipo, v_marca, v_ml, v_nome;
      END;
      $$;
      SQL);


        // 6️⃣ Trigger automática
        DB::unprepared("
CREATE OR REPLACE FUNCTION trg_bebidas_normalize()
RETURNS trigger LANGUAGE plpgsql AS
$$
BEGIN
  SELECT tipo, marca, volume_ml, nome_limpo
    INTO NEW.tipo, NEW.marca, NEW.volume_ml, NEW.nome_limpo
  FROM normalize_bebida(NEW.nombre);
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS bebidas_normalize_biur ON public.bebidas;
CREATE TRIGGER bebidas_normalize_biur
BEFORE INSERT OR UPDATE OF nombre
ON public.bebidas
FOR EACH ROW
EXECUTE FUNCTION trg_bebidas_normalize();
");

        // 7️⃣ Normaliza todos existentes
        DB::unprepared("
WITH norm AS (
  SELECT id, (nb).tipo AS tipo, (nb).marca AS marca, (nb).volume_ml AS volume_ml, (nb).nome_limpo AS nome_limpo
  FROM (SELECT b.id, normalize_bebida(b.nombre) AS nb FROM public.bebidas b) s
)
UPDATE public.bebidas AS b
SET tipo=n.tipo, marca=n.marca, volume_ml=n.volume_ml, nome_limpo=n.nome_limpo
FROM norm n WHERE b.id=n.id;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS bebidas_normalize_biur ON public.bebidas;");
        DB::unprepared("DROP FUNCTION IF EXISTS trg_bebidas_normalize();");
        DB::unprepared("DROP FUNCTION IF EXISTS normalize_bebida(text);");
        DB::unprepared("DROP TABLE IF EXISTS sommelier_brand_aliases;");
        DB::unprepared("DROP TABLE IF EXISTS sommelier_tipo_keywords;");
        Schema::table('bebidas', function (Blueprint $t) {
            if (Schema::hasColumn('bebidas','marca')) $t->dropColumn('marca');
            if (Schema::hasColumn('bebidas','volume_ml')) $t->dropColumn('volume_ml');
            if (Schema::hasColumn('bebidas','nome_limpo')) $t->dropColumn('nome_limpo');
        });
    }
};
