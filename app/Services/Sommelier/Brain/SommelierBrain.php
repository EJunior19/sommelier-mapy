<?php

namespace App\Services\Sommelier\Brain;

use Throwable;
use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAISommelier;
use App\Services\Sommelier\NLP\Intencoes;

/** 🎯 REGRAS PRINCIPAIS (ORDEM IMPORTA) */
use App\Services\Sommelier\Rules\RegraSaudacao;
use App\Services\Sommelier\Rules\RegraMediaPreco;
use App\Services\Sommelier\Rules\RegraExtremoPreco;
use App\Services\Sommelier\Rules\RegraPrecoProduto;
use App\Services\Sommelier\Rules\RegraProcedencia;
use App\Services\Sommelier\Rules\RegraPerguntaConceitual;
use App\Services\Sommelier\Rules\RegraFallbackIA;

/** 🫂 HUMANIZAÇÃO */
use App\Services\Sommelier\Rules\RegraEmpatiaContextual;
use App\Services\Sommelier\Rules\RegraConfianca;

/** 🧠 CONTEXTO */
use App\Services\Sommelier\Rules\RegraAtualizaContextoAposResposta;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Memory\MemoriaPreferencias;

/** 🔐 GUARDS */
use App\Services\Sommelier\Guards\FiltroPerguntaPessoal;
use App\Services\Sommelier\Guards\PerguntaVaga;

/** 🔎 BUSCA / UX */
use App\Services\Sommelier\Search\Buscador;
use App\Services\Sommelier\UX\RespostaBuilder;
use App\Services\Sommelier\UX\NomeFormatter;

class SommelierBrain
{
    protected OpenAISommelier $ai;

    public function __construct(OpenAISommelier $ai)
    {
        $this->ai = $ai;
        SommelierLog::info("🧠 [SommelierBrain] Inicializado");
    }

    public function responder(string $mensagem): string
    {
        try {
            $mensagem = trim((string) $mensagem);
            SommelierLog::info("📥 [Cliente] {$mensagem}");

            if ($mensagem === '') {
                return "Ótimo dia! 🍷 Pode me dizer que tipo de bebida você procura?";
            }

            // ==================================================
            // 1️⃣ SAUDAÇÃO
            // ==================================================
            if (RegraSaudacao::match($mensagem)) {
                return RegraSaudacao::responder();
            }

            // ==================================================
            // 2️⃣ MÉDIA DE PREÇO (PRIORIDADE MÁXIMA)
            // ==================================================
            if (RegraMediaPreco::match($mensagem)) {
                $resposta = RegraMediaPreco::responder($mensagem);
                RegraAtualizaContextoAposResposta::aplicar($mensagem);
                return $this->finalizar($resposta, $mensagem);
            }

            // ==================================================
            // 3️⃣ EXTREMOS (MAIS CARO / MAIS BARATO)
            // ==================================================
            if (RegraExtremoPreco::match($mensagem)) {
                $resposta = RegraExtremoPreco::responder($mensagem);
                RegraAtualizaContextoAposResposta::aplicar($mensagem);
                return $this->finalizar($resposta, $mensagem);
            }

            // ==================================================
            // 4️⃣ PREÇO DE PRODUTO ESPECÍFICO
            // ==================================================
            if (RegraPrecoProduto::match($mensagem)) {
                $resposta = RegraPrecoProduto::responder($mensagem);
                RegraAtualizaContextoAposResposta::aplicar($mensagem);
                return $this->finalizar(
                    $resposta ?? "Não encontrei esse produto específico 😕",
                    $mensagem
                );
            }

            // ==================================================
            // 5️⃣ GUARD — PERGUNTA PESSOAL
            // ==================================================
            if (FiltroPerguntaPessoal::detectar($mensagem)) {
                return "Posso te ajudar apenas com bebidas do Shopping Mapy 🍷";
            }

            // ==================================================
            // 6️⃣ GUARD — PERGUNTA VAGA
            // ==================================================
            if (PerguntaVaga::match($mensagem)) {
                $intencoesHerdadas = PerguntaVaga::handle($mensagem);

                if (is_array($intencoesHerdadas)) {
                    $int = new Intencoes();
                    foreach ($intencoesHerdadas as $k => $v) {
                        if (property_exists($int, $k)) {
                            $int->$k = $v;
                        }
                    }
                    goto BUSCA_POR_INTENCOES;
                }
            }

            // ==================================================
            // 7️⃣ PERGUNTA CONCEITUAL
            // ==================================================
            if (RegraPerguntaConceitual::match($mensagem)) {
                $resposta = RegraPerguntaConceitual::responder($mensagem, $this->ai);
                return $this->finalizar($resposta, $mensagem);
            }

            // ==================================================
            // 8️⃣ NLP NORMAL
            // ==================================================
            $int = Intencoes::processar($mensagem);

            // ==================================================
            // 9️⃣ PROCEDÊNCIA
            // ==================================================
            if ($int->perguntaEspecifica === 'procedencia') {
                $resposta = RegraProcedencia::aplicar([
                    'produtoDetectado'   => $int->produtoDetectado,
                    'perguntaEspecifica' => 'procedencia',
                ]);
                return $this->finalizar($resposta, $mensagem);
            }

            // ==================================================
            // 🔁 BUSCA POR INTENÇÕES
            // ==================================================
            BUSCA_POR_INTENCOES:

            if ($int->temFiltro()) {
                $resultado = Buscador::buscarPorIntencoes($int, $mensagem);

                if (!empty($resultado)) {
                    MemoriaPreferencias::registrar($mensagem);
                    MemoriaContextualCurta::registrar([
                        'categoria' => $int->categoria,
                        'sensorial' => $int->sensorial,
                        'precoMin'  => $int->precoMin,
                        'precoMax'  => $int->precoMax,
                        'minMl'     => $int->minMl,
                        'maxMl'     => $int->maxMl,
                        'ocasiao'   => $int->ocasiao,
                    ]);

                    return $this->finalizar(
                        RespostaBuilder::listaBebidas($resultado, $mensagem),
                        $mensagem
                    );
                }
            }

            // ==================================================
            // 🔟 FALLBACK IA
            // ==================================================
            $respostaIA = RegraFallbackIA::responder($mensagem, $this->ai);
            return $this->finalizar($respostaIA, $mensagem);

        } catch (Throwable $e) {
            SommelierLog::error("❌ [SommelierBrain] Erro crítico", [
                'erro' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return "Tive um problema interno 😕 Pode tentar novamente?";
        }
    }

    /**
     * --------------------------------------------------
     * ✨ FINALIZAÇÃO HUMANIZADA
     * --------------------------------------------------
     */
    protected function finalizar(string $resposta, string $mensagem): string
    {
        $resposta = RegraEmpatiaContextual::aplicar($mensagem, $resposta);
        $resposta = RegraConfianca::aplicar($mensagem, $resposta);
        return NomeFormatter::embelezar($resposta);
    }
}
