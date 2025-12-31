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
        // 🛑 Validação mínima
        if (
            empty($produto['id']) ||
            empty($produto['nome_limpo'])
        ) {
            return null;
        }

        // 🛑 Se já existe procedência no produto, NÃO CONSULTA IA
        if (!empty($produto['pais_origem']) && mb_strlen($produto['pais_origem']) <= 40) {
            SommelierLog::info("♻️ [ProcedenciaResolver] Procedência já existente no produto, pulando IA", [
                'produto' => $produto['nome_limpo'],
                'pais'    => $produto['pais_origem'],
            ]);

            return [
                'pais_origem' => $produto['pais_origem'],
                'procedencia' => $produto['procedencia'] ?? null,
            ];
        }

        SommelierLog::info("🌐 [ProcedenciaResolver] Buscando procedência via OpenAI", [
            'produto' => $produto['nome_limpo']
        ]);

        $prompt = self::montarPrompt($produto['nome_limpo']);

        try {
            // ✅ Client real do projeto
            $openai = new OpenAIClient();

            $texto = $openai->chat($prompt);

            if (!is_string($texto) || trim($texto) === '') {
                SommelierLog::warning("⚠️ [ProcedenciaResolver] OpenAI retornou vazio");
                return null;
            }

            $dados = self::extrairDados($texto);

            if (!$dados) {
                SommelierLog::warning("⚠️ [ProcedenciaResolver] Dados não extraídos", [
                    'texto' => $texto
                ]);
                return null;
            }

            /**
             * 🛑 Segurança final — evita lixo no banco
             */
            if (
                empty($dados['pais_origem']) ||
                mb_strlen($dados['pais_origem']) > 40 ||
                mb_strtolower($dados['pais_origem']) === 'desconhecido'
            ) {
                return null;
            }

            // 💾 Salva no banco (cache definitivo)
            DB::table('bebidas')
                ->where('id', $produto['id'])
                ->update([
                    'pais_origem' => $dados['pais_origem'],
                    'procedencia' => $dados['procedencia'],
                ]);

            SommelierLog::info("💾 [ProcedenciaResolver] Procedência salva no banco", [
                'produto' => $produto['nome_limpo'],
                'pais'    => $dados['pais_origem'],
            ]);

            return $dados;

        } catch (\Throwable $e) {
            SommelierLog::error("❌ [ProcedenciaResolver] Erro ao buscar procedência", [
                'produto' => $produto['nome_limpo'],
                'erro'    => $e->getMessage()
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

Informe a procedência REAL do produto abaixo.

Produto: "{$nomeProduto}"

Responda APENAS no formato abaixo (não escreva mais nada):

PAIS: <nome do país>
RESUMO: <resumo curto da procedência em uma frase>

REGRAS:
- Se não tiver certeza absoluta, responda exatamente:
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
