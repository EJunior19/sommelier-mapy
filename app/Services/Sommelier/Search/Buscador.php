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

        return $res->isEmpty()
            ? []
            : self::formatarResultado($res);
    }

    /**
     * ==================================================
     * 🎯 BUSCA POR INTENÇÕES (CONTROLADA)
     * ==================================================
     */
    public static function buscarPorIntencoes(
        Intencoes $i,
        string $textoOriginal
    ): array {

        if (!self::intencaoMinimaValida($i)) {
            SommelierLog::info("⛔ [Search] Intenção insuficiente para busca", [
                'categoria' => $i->categoria,
                'sensorial' => $i->sensorial,
                'precoMin'  => $i->precoMin,
                'precoMax'  => $i->precoMax,
            ]);
            return [];
        }

        SommelierLog::info("🎯 [Search] Busca por intenções", [
            'categoria' => $i->categoria,
            'sensorial' => $i->sensorial,
            'precoMin'  => $i->precoMin,
            'precoMax'  => $i->precoMax,
        ]);

        $q = DB::table('bebidas')
            ->where('stock', '>', 0);

        // ===============================
        // 🎯 FILTROS DUROS
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

        // ===============================
        // 👅 SENSORIAL (PESO LEVE)
        // ===============================
        if ($i->sensorial) {
            $q->orderByRaw(
                "CASE WHEN busca_composta ILIKE ? THEN 0 ELSE 1 END",
                ['%' . $i->sensorial . '%']
            );
        }

        // ===============================
        // 📘 TEXTO RELEVANTE (SÓ SE NÃO FOR VAGO)
        // ===============================
        $textoRelevante = Normalizador::textoLimpo($textoOriginal);

        if ($textoRelevante !== '' && mb_strlen($textoRelevante) >= 4) {
            $q->orderByRaw(
                "CASE WHEN busca_composta ILIKE ? THEN 0 ELSE 1 END",
                ['%' . $textoRelevante . '%']
            );
        }

        // UX: preço crescente
        $q->orderBy('precio', 'asc');

        // ===============================
        // 🔍 BUSCA AMPLA
        // ===============================
        $res = $q->limit(30)->get();

        if ($res->isEmpty()) {
            SommelierLog::info("📦 [Search] Nenhum resultado por intenções");
            return [];
        }

        // ===============================
        // 🔄 ROTAÇÃO DE RESULTADOS
        // ===============================
        $chaveSessao = self::chaveRotacao($i);
        $jaMostrados = session($chaveSessao, []);

        $items = $res
            ->reject(fn ($b) => in_array($b->id, $jaMostrados))
            ->take(6);

        if ($items->count() < 3) {
            SommelierLog::info("♻️ [Search] Resetando rotação", [
                'chave' => $chaveSessao
            ]);

            session()->forget($chaveSessao);
            $items = $res->take(6);
        }

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
     * 🧠 VALIDA SE A INTENÇÃO É BUSCÁVEL
     * ==================================================
     */
    protected static function intencaoMinimaValida(Intencoes $i): bool
    {
        // Categoria + refinamento humano
        if ($i->categoria && ($i->sensorial || $i->precoMin !== null || $i->precoMax !== null)) {
            return true;
        }

        return false;
    }

    /**
     * ==================================================
     * 🔑 CHAVE DE ROTAÇÃO
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
