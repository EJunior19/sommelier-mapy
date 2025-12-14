<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Enrichment\ProcedenciaResolver;

class RegraProcedencia
{
    /**
     * --------------------------------------------------
     * 🎯 Aplica a regra
     * --------------------------------------------------
     */
    public static function aplicar(array $intencoes): ?string
    {
        if (($intencoes['perguntaEspecifica'] ?? null) !== 'procedencia') {
            return null;
        }

        SommelierLog::info("🌎 [RegraProcedencia] Pergunta de procedência detectada");

        if (!empty($intencoes['produtoDetectado'])) {
            SommelierLog::info("🌍 [RegraProcedencia] Produto detectado", [
                'produto' => $intencoes['produtoDetectado']['nome_limpo'] ?? null
            ]);

            return self::responderProduto($intencoes['produtoDetectado']);
        }

        SommelierLog::warning("⚠️ [RegraProcedencia] Produto não identificado pelo NLP");

        return "Para qual bebida você gostaria de saber a procedência? 🍷";
    }

    /**
     * --------------------------------------------------
     * 🧾 Resposta baseada no produto
     * --------------------------------------------------
     */
    protected static function responderProduto(array $produto): string
    {
        $nome = trim($produto['nome_limpo'] ?? '');
        $pais = trim($produto['pais_origem'] ?? '');

        if ($nome === '') {
            $nome = 'Essa bebida';
        }

        $nome = mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');

        /**
         * ✅ Caso 1 — já existe procedência
         */
        if ($pais !== '') {
            return "{$nome} é de origem {$pais} 🌎🍷";
        }

        /**
         * 🌐 Caso 2 — buscar via IA
         */
        SommelierLog::info("🌐 [RegraProcedencia] Procedência não encontrada, consultando IA", [
            'produto' => $nome
        ]);

        $dados = ProcedenciaResolver::resolver($produto);

        if (
            is_array($dados)
            && !empty($dados['pais_origem'])
            && is_string($dados['pais_origem'])
            && mb_strlen($dados['pais_origem']) <= 40
        ) {
            return "{$nome} é de origem {$dados['pais_origem']} 🌎🍷";
        }

        /**
         * ❌ Caso 3 — falhou
         */
        SommelierLog::warning("❌ [RegraProcedencia] Não foi possível confirmar procedência", [
            'produto' => $nome
        ]);

        return "Ainda não consegui confirmar a procedência de {$nome} 😕";
    }
}
