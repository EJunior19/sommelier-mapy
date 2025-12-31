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
        /**
         * 🔒 Só entra se a pergunta for EXPLICITAMENTE de procedência
         */
        if (($intencoes['perguntaEspecifica'] ?? null) !== 'procedencia') {
            return null;
        }

        /**
         * 🛑 Se for pergunta conceitual, não resolver procedência
         * (ex: "o que é um vinho pinot noir?")
         */
        if (!empty($intencoes['bloquearEnriquecimento'])) {
            SommelierLog::info("⛔ [RegraProcedencia] Bloqueada por pergunta conceitual");
            return null;
        }

        SommelierLog::info("🌎 [RegraProcedencia] Pergunta de procedência detectada");

        if (empty($intencoes['produtoDetectado'])) {
            SommelierLog::warning("⚠️ [RegraProcedencia] Produto não identificado pelo NLP");

            return "Para qual bebida você gostaria de saber a procedência? 🍷";
        }

        return self::responderProduto($intencoes['produtoDetectado']);
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
         * ✅ Caso 1 — procedência já conhecida (base local / banco)
         */
        if ($pais !== '') {
            SommelierLog::info("✅ [RegraProcedencia] Procedência encontrada localmente", [
                'produto' => $nome,
                'pais'    => $pais,
            ]);

            return "{$nome} é de origem {$pais} 🌍🍷";
        }

        /**
         * 🌐 Caso 2 — buscar externamente (OpenAI / fonte confiável)
         */
        SommelierLog::info("🌐 [RegraProcedencia] Procedência não encontrada localmente, consultando fonte externa", [
            'produto' => $nome
        ]);

        $dados = ProcedenciaResolver::resolver($produto);

        /**
         * 🧠 Validação defensiva da resposta
         */
        if (
            is_array($dados)
            && !empty($dados['pais_origem'])
            && is_string($dados['pais_origem'])
            && mb_strlen($dados['pais_origem']) <= 40
        ) {
            SommelierLog::info("💾 [RegraProcedencia] Procedência confirmada e validada", [
                'produto' => $nome,
                'pais'    => $dados['pais_origem'],
                'fonte'   => $dados['fonte'] ?? 'desconhecida',
            ]);

            return "{$nome} é de origem {$dados['pais_origem']} 🌍🍷";
        }

        /**
         * ❌ Caso 3 — falha honesta (sem inventar)
         */
        SommelierLog::warning("❌ [RegraProcedencia] Procedência não confirmada", [
            'produto' => $nome
        ]);

        return "Ainda não consegui confirmar a procedência de {$nome} 😕";
    }
}
