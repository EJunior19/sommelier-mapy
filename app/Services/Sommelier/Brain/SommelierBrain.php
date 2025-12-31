<?php

namespace App\Services\Sommelier\Brain;

use Throwable;
use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAISommelier;
use App\Services\Sommelier\NLP\Intencoes;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;

/** 🎯 REGRAS PRINCIPAIS */
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
use App\Services\Sommelier\Memory\MemoriaPreferencias;

/** 🔐 GUARDS */
use App\Services\Sommelier\Guards\FiltroPerguntaPessoal;
use App\Services\Sommelier\Guards\PerguntaVaga;

/** 🔎 BUSCA / UX */
use App\Services\Sommelier\Search\Buscador;
use App\Services\Sommelier\UX\RespostaBuilder;
use App\Services\Sommelier\UX\NomeFormatter;

/** 🧠 REGRAS INTELIGENTES */
use App\Services\Sommelier\Rules\RegraMaridajeInteligente;
use App\Services\Sommelier\Rules\RegraOcasiaoInteligente;
use App\Services\Sommelier\Rules\RegraSensorialInteligente;
use App\Services\Sommelier\Rules\RegraPerguntaEsclarecedora;
use App\Services\Sommelier\Rules\RegraRefinamentoContextual;
use App\Services\Sommelier\Rules\RegraCategoriaSemAlcool;
use App\Services\Sommelier\Rules\RegraCategoriaMacro;
use App\Services\Sommelier\Rules\RegraSubcategoriaDestilados;
use App\Services\Sommelier\Rules\RegraEventoMacro;
use App\Services\Sommelier\Rules\RegraPerguntaCulturalEvento;
use App\Services\Sommelier\Enrichment\ProcedenciaResolver;
use App\Services\Sommelier\NLP\ProdutoDetector;



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

        // ==================================================
        // 🔎 DETECÇÃO DE PRODUTO (INDEPENDENTE DE INTENÇÃO)
        // ==================================================
        $produtoDetectado = ProdutoDetector::detectar($mensagem);

        if ($produtoDetectado) {
            SommelierLog::info(
                "🔎 [Brain] Produto detectado na mensagem",
                $produtoDetectado
            );
        }

        // ==================================================
        // 🧹 Nova conversa: se houver contexto antigo, limpa
        // ==================================================
        if ($this->ehInicioDeConversa($mensagem)) {
            MemoriaContextualCurta::limpar();
            SommelierLog::info("🧹 [Brain] Contexto limpo (saudação detectada)");
        }

        // ==================================================
        // 0️⃣ MENSAGEM VAZIA
        // ==================================================
        if ($mensagem === '') {
            return "Ótimo dia! 🍷 Pode me dizer que tipo de bebida você procura?";
        }

        // ==================================================
        // 1️⃣ SAUDAÇÃO
        // ==================================================
        if (RegraSaudacao::match($mensagem)) {
            $resp = RegraSaudacao::responder();
            RegraAtualizaContextoAposResposta::aplicar($mensagem);
            return $this->finalizar($resp, $mensagem);
        }

        // ==================================================
        // 2️⃣ MÉDIA DE PREÇO
        // ==================================================
        if (RegraMediaPreco::match($mensagem)) {
            $resp = RegraMediaPreco::responder($mensagem);
            RegraAtualizaContextoAposResposta::aplicar($mensagem);
            return $this->finalizar($resp, $mensagem);
        }

        // ==================================================
        // 3️⃣ EXTREMOS
        // ==================================================
        if (RegraExtremoPreco::match($mensagem)) {
            $resp = RegraExtremoPreco::responder($mensagem);
            RegraAtualizaContextoAposResposta::aplicar($mensagem);
            return $this->finalizar($resp, $mensagem);
        }

        // ==================================================
        // 4️⃣ PREÇO DE PRODUTO
        // ==================================================
        if (RegraPrecoProduto::match($mensagem)) {
            $resp = RegraPrecoProduto::responder($mensagem);
            RegraAtualizaContextoAposResposta::aplicar($mensagem);

            return $this->finalizar(
                $resp ?? "Não encontrei esse produto específico 😕",
                $mensagem
            );
        }

        // ==================================================
        // 5️⃣ GUARD — PERGUNTA PESSOAL
        // ==================================================
        if (FiltroPerguntaPessoal::detectar($mensagem)) {
            RegraAtualizaContextoAposResposta::aplicar($mensagem);
            return $this->finalizar(
                "Posso te ajudar apenas com bebidas do Shopping Mapy 🍷",
                $mensagem
            );
        }

        // ==================================================
        // 🧮 QUANTIDADE PARA EVENTOS
        // ==================================================
        if (class_exists(\App\Services\Sommelier\Rules\RegraQuantidadeEvento::class)) {
            $qtd = \App\Services\Sommelier\Rules\RegraQuantidadeEvento::match($mensagem);
            if ($qtd !== null) {
                return $this->finalizar(
                    \App\Services\Sommelier\Rules\RegraQuantidadeEvento::responder($qtd, $mensagem),
                    $mensagem
                );
            }
        }

        // ==================================================
        // 6️⃣ PERGUNTA VAGA
        // ==================================================
        $int = null;
        if (PerguntaVaga::match($mensagem)) {
            $herdado = PerguntaVaga::handle($mensagem);
            if (is_array($herdado)) {
                $int = new Intencoes();
                foreach ($herdado as $k => $v) {
                    if (property_exists($int, $k)) {
                        $int->$k = $v;
                    }
                }
            }
        }

        // ==================================================
        // 7️⃣ NLP
        // ==================================================
        if (!$int instanceof Intencoes) {
            $int = Intencoes::processar($mensagem);
        } else {
            $intMsg = Intencoes::processar($mensagem);
            $this->mesclarIntencoes($int, $intMsg);
        }

        // ==================================================
        // 🔗 INJETAR PRODUTO DETECTADO NO CONTEXTO NLP
        // ==================================================
        if ($produtoDetectado && empty($int->produtoDetectado)) {
            $int->produtoDetectado = $produtoDetectado;

            SommelierLog::info(
                "🧩 [Brain] Produto injetado em Intencoes",
                $produtoDetectado
            );
        }


        // ==================================================
        // 🌎 ENRIQUECIMENTO — PROCEDÊNCIA (INDEPENDENTE DE SEARCH)
        // ==================================================
        if (!empty($int->produtoDetectado) && is_array($int->produtoDetectado)) {
            SommelierLog::info("🌎 [Brain] Produto detectado para procedência", $int->produtoDetectado);

            // Enriquecimento silencioso (não afeta resposta)
            ProcedenciaResolver::resolver($int->produtoDetectado);
        }

        // ==================================================
        // 🎉 EVENTO MACRO
        // ==================================================
        if (class_exists(RegraEventoMacro::class)) {
            RegraEventoMacro::aplicar($mensagem, $int);
        }

        // ==================================================
        // ⭐ INTENÇÃO INCREMENTAL (CORRIGIDA)
        // ==================================================
        if (preg_match('/\b(mais especial|melhor|mais premium|top|especial)\b/i', $mensagem)) {
            $ctx = MemoriaContextualCurta::recuperar();

            if (is_array($ctx)) {
                SommelierLog::info("⭐ [Brain] Intenção incremental detectada", [
                    'mensagem' => $mensagem
                ]);

                $int->precoMin = max($int->precoMin ?? 0, 25);

                if (!$int->categoria && !empty($ctx['categoria'])) {
                    $int->categoria = $ctx['categoria'];
                }
            }
        }

        // ==================================================
        // 🎓 PERGUNTA CULTURAL
        // ==================================================
        if (
            class_exists(RegraPerguntaCulturalEvento::class)
            && $int->ocasiao
            && RegraPerguntaCulturalEvento::match($mensagem, $int->ocasiao)
            && !preg_match('/recomenda|indica|sugere|quero|preciso/i', $mensagem)
        ) {
            return $this->finalizar(
                RegraPerguntaCulturalEvento::responder($int->ocasiao),
                $mensagem
            );
        }

        // ==================================================
        // 🎉 EVENTO SEM CATEGORIA
        // ==================================================
        if ($int->ocasiao && !$int->categoria) {
            return $this->finalizar(
                "Perfeito 😊 Para o {$int->ocasiao}, você prefere vinho, espumante, cerveja ou algo sem álcool?",
                $mensagem
            );
        }

        // ==================================================
        // 📘 PERGUNTA CONCEITUAL (PRIORIDADE)
        // ==================================================
        if (RegraPerguntaConceitual::match($mensagem)) {
            $resp = RegraPerguntaConceitual::responder($mensagem, $this->ai);
            if ($resp) {
                return $this->finalizar($resp, $mensagem);
            }
        }

        // ==================================================
        // 🧩 CATEGORIA / MARIDAJE / OCASIÃO / SENSORIAL
        // ==================================================
        RegraCategoriaMacro::aplicar($mensagem, $int);

        if (class_exists(RegraMaridajeInteligente::class)) {
            RegraMaridajeInteligente::aplicar($mensagem, $int);
        }

        if (class_exists(RegraOcasiaoInteligente::class)) {
            RegraOcasiaoInteligente::aplicar($mensagem, $int);
        }

        if (class_exists(RegraSensorialInteligente::class)) {
            RegraSensorialInteligente::aplicar($mensagem, $int);
        }

        if (class_exists(RegraCategoriaSemAlcool::class)) {
            RegraCategoriaSemAlcool::aplicar($mensagem, $int);
        }

        if (class_exists(RegraRefinamentoContextual::class)) {
            RegraRefinamentoContextual::aplicar($mensagem, $int);
        }

        // ==================================================
        // 🔁 BUSCA
        // ==================================================
        $resultado = Buscador::buscarPorIntencoes($int, $mensagem);

        if (!empty($resultado)) {
            MemoriaContextualCurta::registrar((array) $int);
            return $this->finalizar(
                RespostaBuilder::listaBebidas($resultado, $mensagem),
                $mensagem
            );
        }

        // ==================================================
        // 🔟 FALLBACK IA
        // ==================================================
        return $this->finalizar(
            RegraFallbackIA::responder($mensagem, $this->ai),
            $mensagem
        );

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
     * Detecta "início" para limpar contexto antigo.
     * (Somente se já existir contexto salvo)
     */
    protected function ehInicioDeConversa(string $mensagem): bool
    {
        $t = mb_strtolower(trim($mensagem), 'UTF-8');

        // só considera início se for curta e genérica
        if (mb_strlen($t) > 20) {
            return false;
        }

        return (bool) preg_match(
            '/^(oi|ol[aá]|bom dia|boa tarde|boa noite|hey|hola|otimo dia|[óo]timo dia)$/i',
            $t
        );
    }

    /**
     * Decide se já dá pra buscar e listar bebidas sem “chutar”.
     * - Se sua classe Intencoes tiver temFiltroSuficiente(), usamos ela.
     * - Senão, usamos temFiltro() como fallback.
     */
    protected function temFiltroSuficienteParaBuscar(Intencoes $int): bool
    {
        if (method_exists($int, 'temFiltroSuficiente')) {
            return (bool) $int->temFiltroSuficiente();
        }

        // fallback compatível com teu Intencoes atual
        if (method_exists($int, 'temFiltro')) {
            return (bool) $int->temFiltro();
        }

        // último fallback (bem conservador)
        return (bool) ($int->categoria || $int->sensorial || $int->precoMin !== null || $int->precoMax !== null || $int->ocasiao);
    }

    /**
     * Pergunta via IA de forma “humana”, mas SEM recomendar ainda.
     */
    protected function perguntarViaIA(string $mensagem, Intencoes $int): string
    {
        $ctx = [
            'categoria' => $int->categoria,
            'sensorial' => $int->sensorial,
            'ocasiao'   => $int->ocasiao,
            'precoMin'  => $int->precoMin,
            'precoMax'  => $int->precoMax,
        ];

        $prompt = <<<PROMPT
Você é o Sommelier Mapy. Responda como um atendente humano, simpático e objetivo.

OBJETIVO:
Fazer APENAS 1 pergunta curta para entender melhor o que o cliente quer,
antes de recomendar qualquer bebida.

REGRAS OBRIGATÓRIAS:
- NÃO recomende bebidas ainda
- NÃO liste produtos
- NÃO cite preços
- NÃO cite estoque
- 1 pergunta só (curta)
- Se o cliente falou de comida (ex: peixe, carne), pergunte o detalhe mais útil (tipo de preparo / molho / intensidade)
- Se for ocasião (churrasco/janta), pode perguntar se prefere vinho, cerveja ou destilado (apenas se ainda não tiver categoria)
- Linguagem natural, como humano

Contexto já detectado (pode estar vazio):
"{$this->safeJson($ctx)}"

Mensagem do cliente:
"{$mensagem}"
PROMPT;

        // Preferir método do teu OpenAISommelier (como você já usa na RegraOcasiãoEspecial)
        if (method_exists($this->ai, 'responderSommelier')) {
            $resp = $this->ai->responderSommelier($prompt);
            if (is_string($resp) && trim($resp) !== '') {
                return trim($resp);
            }
        }

        // fallback: usa tua regra de IA existente
        $respFallback = RegraFallbackIA::responder($prompt, $this->ai);
        return is_string($respFallback) && trim($respFallback) !== ''
            ? trim($respFallback)
            : "Perfeito 😊 Só pra eu acertar: você prefere vinho, cerveja ou destilado?";
    }

    /**
     * Mescla intenções sem apagar o que já foi herdado (prioriza o novo do texto).
     */
    protected function mesclarIntencoes(Intencoes $base, Intencoes $novo): void
    {
        // se o texto trouxe algo novo, sobrescreve; senão mantém o herdado
        foreach (['categoria', 'sensorial', 'ocasiao', 'marca'] as $k) {
            if (!empty($novo->$k)) {
                $base->$k = $novo->$k;
            }
        }

        // preço e volume: se veio na msg, aplica
        foreach (['precoMin', 'precoMax', 'minMl', 'maxMl'] as $k) {
            if ($novo->$k !== null) {
                $base->$k = $novo->$k;
            }
        }

        // perguntas específicas (procedência/abstrata) têm prioridade do texto
        if (!empty($novo->perguntaEspecifica)) {
            $base->perguntaEspecifica = $novo->perguntaEspecifica;
            $base->produtoDetectado   = $novo->produtoDetectado;
        }
    }

    protected function safeJson(array $data): string
    {
        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (Throwable $e) {
            return '{}';
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
