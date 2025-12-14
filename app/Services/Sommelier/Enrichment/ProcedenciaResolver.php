<?php

namespace App\Services\Sommelier\Enrichment;

use Illuminate\Support\Facades\DB;
use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAIClient;

class ProcedenciaResolver
{
    /**
     * --------------------------------------------------
     * 🌎 Resolve procedência de um produto
     * --------------------------------------------------
     * Retorna:
     * [
     *   'pais_origem' => 'Brasil',
     *   'procedencia' => 'Vinho brasileiro produzido na Serra Gaúcha.'
     * ]
     */
    public static function resolver(array $produto): ?array
    {
        if (empty($produto['id']) || empty($produto['nome_limpo'])) {
            return null;
        }

        SommelierLog::info("🌐 [ProcedenciaResolver] Buscando procedência via OpenAI", [
            'produto' => $produto['nome_limpo']
        ]);

        $prompt = self::montarPrompt($produto['nome_limpo']);

        try {
            // ✅ Usa o client REAL do projeto
            $openai = new OpenAIClient();

            $texto = $openai->chat($prompt);

            if (!$texto) {
                return null;
            }

            $dados = self::extrairDados($texto);

            if (!$dados) {
                return null;
            }

            // 💾 Salva no banco (cache definitivo)
            DB::table('bebidas')
                ->where('id', $produto['id'])
                ->update([
                    'pais_origem' => $dados['pais_origem'],
                    'procedencia' => $dados['procedencia'],
                ]);

            SommelierLog::info("💾 [ProcedenciaResolver] Procedência salva no banco", $dados);

            return $dados;

        } catch (\Throwable $e) {
            SommelierLog::error("❌ [ProcedenciaResolver] Erro ao buscar procedência", [
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * --------------------------------------------------
     * 🧠 Prompt controlado (anti-alucinação)
     * --------------------------------------------------
     */
    protected static function montarPrompt(string $nomeProduto): string
    {
        return <<<PROMPT
Você é um especialista em vinhos e bebidas alcoólicas.

Informe a procedência do produto abaixo.

Produto: "{$nomeProduto}"

Responda APENAS no formato abaixo (não escreva mais nada):

PAIS: <nome do país>
RESUMO: <resumo curto da procedência em uma frase>

Se não tiver certeza absoluta, responda exatamente:
PAIS: desconhecido
RESUMO: procedência não confirmada
PROMPT;
    }

    /**
     * --------------------------------------------------
     * 🔍 Extrai país e resumo do texto
     * --------------------------------------------------
     */
    protected static function extrairDados(string $texto): ?array
    {
        if (
            !preg_match('/PAIS:\s*(.+)/i', $texto, $mPais) ||
            !preg_match('/RESUMO:\s*(.+)/i', $texto, $mResumo)
        ) {
            SommelierLog::warning("⚠️ [ProcedenciaResolver] Resposta OpenAI fora do padrão", [
                'texto' => $texto
            ]);
            return null;
        }

        $pais = trim($mPais[1]);
        $resumo = trim($mResumo[1]);

        if ($pais === '' || mb_strtolower($pais) === 'desconhecido') {
            return null;
        }

        return [
            'pais_origem' => $pais,
            'procedencia' => $resumo,
        ];
    }
}
