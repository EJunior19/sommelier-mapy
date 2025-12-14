<?php

namespace App\Services\Sommelier\Search;

use Illuminate\Support\Facades\DB;
use App\Services\Sommelier\NLP\Intencoes;
use App\Services\Sommelier\Support\Normalizador;
use App\Helpers\SommelierLog;

class Buscador
{
    /**
     * ==================================================
     * 🔎 BUSCA DIRETA (TRGM)
     * ==================================================
     */
    public static function buscar(string $texto): array
    {
        SommelierLog::info("🔎 [Search] Busca direta", ['texto' => $texto]);

        $texto = Normalizador::textoLimpo($texto);

        if ($texto === '') {
            return [];
        }

        $res = DB::table('bebidas')
            ->where('stock', '>', 0)
            ->whereRaw("busca_composta % ?", [$texto])
            ->orderByRaw("similarity(busca_composta, ?) DESC", [$texto])
            ->limit(6)
            ->get();

        if ($res->isEmpty()) {
            SommelierLog::info("📦 [Search] Nenhum resultado direto");
            return [];
        }

        return self::formatarResultado($res);
    }

    /**
     * ==================================================
     * 🎯 BUSCA POR INTENÇÕES (INTELIGENTE)
     * ==================================================
     */
    public static function buscarPorIntencoes(
        Intencoes $i,
        string $textoOriginal
    ): array {
        SommelierLog::info("🎯 [Search] Busca por intenções", [
            'categoria' => $i->categoria,
            'precoMin'  => $i->precoMin,
            'precoMax'  => $i->precoMax,
            'sensorial' => $i->sensorial,
        ]);

        $q = DB::table('bebidas')
            ->where('stock', '>', 0);

        // ===============================
        // 🎯 FILTROS
        // ===============================
        if ($i->categoria) {
            $q->where('tipo', $i->categoria);
        }

        if ($i->precoMin !== null) {
            $q->where('precio', '>=', $i->precoMin);
        }

        if ($i->precoMax !== null) {
            $q->where('precio', '<=', $i->precoMax);
        }

        if ($i->sensorial) {
            $q->where('busca_composta', 'ILIKE', '%' . $i->sensorial . '%');
        }

        // ===============================
        // 📊 ORDENAR COM BOM SENSO
        // ===============================
        // 1️⃣ Relevância por texto
        // 2️⃣ Preço crescente (UX)
        $q->orderByRaw("
            CASE 
                WHEN busca_composta ILIKE ? THEN 1
                ELSE 2
            END
        ", ['%' . $textoOriginal . '%']);

        $q->orderBy('precio', 'asc');

        // ===============================
        // 🔍 BUSCAR MAIS DO QUE MOSTRAR
        // ===============================
        $res = $q->limit(30)->get();

        if ($res->isEmpty()) {
            SommelierLog::info("📦 [Search] Nenhum resultado por intenções");
            return [];
        }

        // ===============================
        // 🔄 ROTAÇÃO INTELIGENTE POR CONTEXTO
        // ===============================
        $chaveSessao = self::chaveRotacao($i);
        $jaMostrados = session($chaveSessao, []);

        $items = $res
            ->reject(fn ($b) => in_array($b->id, $jaMostrados))
            ->take(6);

        // ===============================
        // ♻️ FALLBACK SE ESGOTOU VARIEDADE
        // ===============================
        if ($items->count() < 3) {
            SommelierLog::info("♻️ [Search] Resetando rotação", [
                'chave' => $chaveSessao
            ]);

            session()->forget($chaveSessao);

            $items = $res->take(6);
        }

        // ===============================
        // 💾 SALVAR MEMÓRIA
        // ===============================
        session([
            $chaveSessao => array_merge(
                $jaMostrados,
                $items->pluck('id')->toArray()
            )
        ]);

        SommelierLog::info("🧠 [Search] Bebidas selecionadas", [
            'ids' => $items->pluck('id')->toArray()
        ]);

        return self::formatarResultado($items);
    }

    /**
     * ==================================================
     * 🧠 BUSCA DE PRODUTO ÚNICO
     * ==================================================
     */
    public static function buscarProdutoPorTexto(string $textoOriginal): ?array
    {
        $texto = Normalizador::normalizarTextoProduto($textoOriginal);

        if ($texto === '') {
            return null;
        }

        SommelierLog::info("🔍 [Search] Identificando produto", [
            'texto' => $texto
        ]);

        $b = DB::table('bebidas')
            ->where('stock', '>', 0)
            ->whereRaw("busca_composta % ?", [$texto])
            ->orderByRaw("similarity(busca_composta, ?) DESC", [$texto])
            ->limit(1)
            ->first();

        if (!$b) {
            return null;
        }

        return [
            'id'          => $b->id,
            'nome_limpo'  => $b->nome_limpo,
            'tipo'        => $b->tipo,
            'precio'      => $b->precio,
            'pais_origem' => $b->pais_origem ?? null,
        ];
    }

    /**
     * ==================================================
     * 🔑 CHAVE DE ROTAÇÃO POR CONTEXTO
     * ==================================================
     */
    protected static function chaveRotacao(Intencoes $i): string
    {
        return 'rotacao_' . md5(json_encode([
            $i->categoria,
            $i->sensorial,
            $i->precoMin,
            $i->precoMax,
        ]));
    }

    /**
     * ==================================================
     * 📦 PADRONIZA RESULTADO
     * ==================================================
     */
    protected static function formatarResultado($res): array
    {
        return $res->map(fn ($b) => [
            'id'         => $b->id,
            'nome_limpo' => $b->nome_limpo,
            'tipo'       => $b->tipo,
            'precio'     => $b->precio,
        ])->values()->toArray();
    }
}
