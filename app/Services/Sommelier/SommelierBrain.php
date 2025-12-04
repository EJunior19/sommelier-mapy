<?php

namespace App\Services\Sommelier;

use App\Services\Sommelier\Buscador;
use App\Services\Sommelier\Intencoes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Helpers\SommelierLog;
use App\Services\OpenAIService;
use Throwable;

class SommelierBrain
{
    protected OpenAIService $openai;

    public function __construct(OpenAIService $openai)
    {
        $this->openai = $openai;
        SommelierLog::info("🧠 SommelierBrain iniciado.");
    }

    /**
     * ===========================================================
     * 🔥 CÉREBRO PRINCIPAL DO SOMMELIER
     * ===========================================================
     */
    public function responder(string $mensagem): string
    {
        SommelierLog::info("📥 Entrada do cliente: {$mensagem}");

        $mensagem = trim($mensagem);

        if ($mensagem === '') {
            SommelierLog::info("⚠️ Mensagem vazia.");
            return 'Poderia reformular, por gentileza?';
        }

        // =========================================================
        // 🔧 NORMALIZAÇÃO IA
        // =========================================================
        try {
            SommelierLog::info("🔧 normalizeQuery() — entrada: {$mensagem}");
            $mensagemNormalizada = $this->openai->normalizeQuery($mensagem);

            SommelierLog::info("🔧 normalizeQuery() — saída: {$mensagemNormalizada}");

            if ($mensagemNormalizada && is_string($mensagemNormalizada)) {

                // 🚫 Se a IA devolveu uma mensagem genérica de erro/orientação,
                // NÃO vamos substituir a pergunta original do cliente.
                $saidaLower = mb_strtolower($mensagemNormalizada, 'UTF-8');

                if (
                    str_contains($saidaLower, 'não há informações suficientes') ||
                    str_contains($saidaLower, 'nao ha informacoes suficientes') ||
                    str_contains($saidaLower, 'por favor, forneça detalhes') ||
                    str_contains($saidaLower, 'por favor, forneca detalhes')
                ) {
                    SommelierLog::info("⚠️ normalizeQuery retornou mensagem genérica — mantendo texto original do cliente.");
                } else {
                    $mensagem = $mensagemNormalizada;
                }
            }

        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro normalizeQuery(): {$e->getMessage()}");
        }

        $textoOriginal = $mensagem;
        $textoLower    = mb_strtolower($mensagem, 'UTF-8');

        // =========================================================
        // 🔁 RESET
        // =========================================================
        if (preg_match('/\b(nova conversa|reset|recomeçar|limpar)/iu', $textoLower)) {
            SommelierLog::info("🔄 Reset de conversa solicitado.");
            Session::forget('historico_mapy');
            Session::forget('cumprimentou');
            return $this->saudacaoInicial(true);
        }

        // =========================================================
        // 👋 CUMPRIMENTO SIMPLES
        // =========================================================
        if ($this->ehCumprimentoSimples($textoOriginal)) {
            SommelierLog::info("👋 Cumprimento simples detectado.");
            Session::put('cumprimentou', true);
            return "Claro! Como posso te ajudar com as bebidas hoje? 🍷";
        }

        $cumprimento = $this->saudacaoInicial();

        $historico = session('historico_mapy', []);
        $contexto  = collect($historico)
            ->take(-5)
            ->map(fn ($m) => "Cliente: {$m['cliente']} | Sommelier: {$m['assistente']}")
            ->join("\n");

        $origem    = 'conversa';
        $resposta  = null;
        $usouBanco = false;

        // ===========================================================
        // 1) 🧠 INTENÇÕES DETECTADAS
        // ===========================================================
        try {
            $int = Intencoes::processar($textoOriginal);
            
            // ===========================================================
            // 🆕 0) PERGUNTA ESPECÍFICA SOBRE PROCEDÊNCIA / ORIGEM
            // ===========================================================
            if (!empty($int['perguntaEspecifica']) && !empty($int['produtoDetectado'])) {

            $p = $int['produtoDetectado'];

            SommelierLog::info("🗂️ Pergunta específica detectada: {$int['perguntaEspecifica']}");

            // 1) PRIMEIRO tenta responder com dados do banco
            $pais = $p['pais_origem'] ?? null;

            if ($pais) {
                $msg = "O {$p['nome_limpo']} é produzido em {$pais}.";
                SommelierLog::info("📌 Resposta de procedência pelo banco: {$msg}");
                return $msg;
            }

            // 2) SE NÃO TIVER DADOS NO BANCO → IA INVESTIGA
            try {
                $perguntaIA = "Explique em 2 frases a origem e o país de fabricação da bebida '{$p['nome_limpo']}' (marca: {$p['marca']}). Seja direto.";

                SommelierLog::info("🔍 Chamando IA para responder sobre origem: {$perguntaIA}");

                $respIA = $this->openai->responderSimples($perguntaIA);

                if ($respIA) {
                    SommelierLog::info("🤖 IA respondeu procedência: {$respIA}");
                    return $respIA;
                }

            } catch (\Throwable $e) {
                SommelierLog::error("❌ Erro IA origem: " . $e->getMessage());
            }

            // 3) FALLBACK FINAL
            return "O {$p['nome_limpo']} não possui informações de origem cadastradas.";
        }


            // normalização faixa
            if (
                $int['precoMin'] !== null &&
                $int['precoMax'] !== null &&
                $int['precoMin'] > $int['precoMax']
            ) {
                SommelierLog::info("🔄 Corrigindo faixa de preço invertida.");
                [$int['precoMin'], $int['precoMax']] = [$int['precoMax'], $int['precoMin']];
            }

            // se tem intenção → usar módulo de intenções
            if (
                !empty($int['categoria']) ||
                !empty($int['marca'])     ||
                !empty($int['sensorial']) ||
                $int['precoMin'] !== null ||
                $int['precoMax'] !== null ||
                $int['minMl']   !== null  ||
                $int['maxMl']   !== null
            ) {
                SommelierLog::info("🚀 Executando busca por intenções…");

                $resPorIntencao = Buscador::buscarPorIntencoes($int, $textoOriginal);

                if (!empty($resPorIntencao)) {
                    $origem    = 'intencao';
                    $usouBanco = true;

                    SommelierLog::info("🎯 Resultado bruto intenções:\n" . json_encode($resPorIntencao, JSON_PRETTY_PRINT));

                    // IA para formatar as opções
                    try {
                        if (is_array($resPorIntencao) && !empty($resPorIntencao['opcoes'])) {
                            $respostaIA = $this->openai->responderComOpcoes($textoOriginal, $resPorIntencao['opcoes']);
                            SommelierLog::info("🤖 IA formatou opções.");

                            $resposta = $respostaIA ?: $resPorIntencao['texto_bruto'];
                        } else {
                            $resposta = $resPorIntencao;
                        }
                    } catch (\Throwable $e) {
                        SommelierLog::error("❌ Erro responderComOpcoes(): {$e->getMessage()}");
                        $resposta = is_string($resPorIntencao) ? $resPorIntencao : null;
                    }
                }
            }
        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro intenções: {$e->getMessage()}");
        }

        // ===========================================================
        // 2) 🔎 BUSCA DIRETA
        // ===========================================================
        if (!$resposta) {
            SommelierLog::info("🔎 Caixa rápida — TRGM Buscador::buscar()");
            try {
                $resultadoBanco = Buscador::buscar($textoOriginal);

                if ($resultadoBanco) {
                    SommelierLog::info("📦 Resultado TRGM encontrado.");
                    $resposta  = $resultadoBanco;
                    $origem    = 'busca_banco';
                    $usouBanco = true;
                }
            } catch (Throwable $e) {
                SommelierLog::error("❌ Erro Buscador (banco): {$e->getMessage()}");
            }
        }

        // ===========================================================
        // 3) 🤖 FALLBACK IA
        // ===========================================================
        if (!$resposta && !$this->pedidoEstritamenteDeProduto($textoLower)) {
            SommelierLog::info("🤖 Fallback IA ativado.");
            try {
                $resIA = $this->openai->responder($textoOriginal, $contexto);
                SommelierLog::info("🤖 IA respondeu (fallback).");
                $resposta = $resIA;
                $origem   = 'ia';
            } catch (Throwable $e) {
                SommelierLog::error("❌ Erro IA fallback: {$e->getMessage()}");
            }
        }

        // ===========================================================
        // 4) ⚠️ NADA ENCONTRADO
        // ===========================================================
        if (!$resposta) {
            SommelierLog::info("⚠️ Nenhum módulo identificou resposta.");
            $resposta = "Poderia me dizer se prefere algo doce, leve, encorpado ou mais forte?";
        }

        // Histórico curto
        $respString = is_string($resposta) ? $resposta : json_encode($resposta);

        // remover saudações desnecessárias
        $respString = preg_replace('/^(oi|ola|olá|bom dia|boa tarde|boa noite)[^.!?]*\s*/iu', '', $respString);

        $final = $cumprimento
            ? "{$cumprimento} {$respString}"
            : $respString;

        SommelierLog::info("✅ RESPOSTA FINAL ({$origem}):\n{$final}");

        return trim($final);
    }


    /**
     * ===================================================
     * Agora apenas reconhece CUMPRIMENTOS EXATOS
     * ===================================================
     */
    protected function ehCumprimentoSimples(string $texto): bool
    {
        $texto = trim(mb_strtolower($texto));

        $lista = [
            'oi', 'olá', 'ola', 'oie',
            'bom dia', 'boa tarde', 'boa noite',
            'tudo bem'
        ];

        return in_array($texto, $lista, true);
    }

    /**
     * 👋 Saudação inicial automática
     */
    protected function saudacaoInicial(bool $forcar = false): ?string
    {
        if (!$forcar && Session::get('cumprimentou', false)) {
            return null;
        }

        $hora = now()->hour;

        $cumprimento = match (true) {
            $hora < 12 => "Ótimo dia ☀️! Bem-vindo ao Shopping Mapy. Sou sua Sommelier Virtual 🍷.",
            $hora < 18 => "Ótima tarde 🌤️! Seja bem-vindo ao Shopping Mapy. Estou aqui para ajudá-lo a escolher a bebida ideal.",
            default    => "Ótima noite 🌙! Bem-vindo ao Shopping Mapy. Será um prazer ajudá-lo na escolha da bebida perfeita.",
        };

        Session::put('cumprimentou', true);

        return $cumprimento;
    }

    /**
     * 🧠 APRENDIZADO AUTOMÁTICO FORTE (palavras soltas)
     */
    protected function treinarAprendizado(string $textoOriginal): void
    {
        $texto = mb_strtolower($textoOriginal, 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $texto = preg_replace('/[^a-z0-9 ]/i', ' ', $texto);
        $texto = trim($texto);

        if (strlen($texto) < 3) {
            return;
        }

        // STOPWORDS
        $stop = [
            'o','a','os','as','um','uma','uns','umas',
            'para','pra','por','com','no','na','nos','nas',
            'que','qual','quais','quanto','valor','preco','preço',
            'de','do','da','dos','das','sobre',
            'gostaria','queria','algo','alguma','algum',
            'me','te','se','la','lo','las','los','yo','tu','vc','voce',
            'bom','boa','oi','ola','olá','tudo','bem','ae','eae','salve'
        ];

        $palavras = array_values(array_filter(
            array_diff(explode(' ', $texto), $stop)
        ));

        if (!$palavras) {
            return;
        }

        foreach ($palavras as $p) {
            if (strlen($p) < 3) {
                continue;
            }

            $row = DB::table('memoria_aprendizado')
                ->where('dado', $p)
                ->first();

            if ($row) {
                DB::table('memoria_aprendizado')
                    ->where('id', $row->id)
                    ->update([
                        'contador'   => $row->contador + 1,
                        'updated_at' => now(),
                    ]);

                if ($row->contador + 1 >= 3) {
                    DB::table('sommelier_alias_global')
                        ->updateOrInsert(
                            ['alias' => $p],
                            [
                                'canonical' => $p,
                                'tipo'      => 'auto',
                            ]
                        );
                }

                continue;
            }

            DB::table('memoria_aprendizado')->insert([
                'tipo'       => 'palavra',
                'dado'       => $p,
                'contador'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 🧠 MEMÓRIA DE PREFERÊNCIAS
     */
    protected function registrarAprendizado(string $mensagem): void
    {
        $texto = mb_strtolower($mensagem, 'UTF-8');

        $mapas = [
            'doce'        => 'bebidas doces',
            'forte'       => 'bebidas fortes',
            'leve'        => 'bebidas leves',
            'vinho'       => 'vinhos',
            'espumante'   => 'espumantes',
            'cerveja'     => 'cervejas',
            'whisky'      => 'whiskies',
            'whiskies'    => 'whiskies',
            'licor'       => 'licores',
            'sem alcool'  => 'sem álcool',
            'sem álcool'  => 'sem álcool',
            'relaxar'     => 'para relaxar',
            'festa'       => 'para festa',
            'presente'    => 'para presente',
            'churrasco'   => 'para churrasco',
            'jantar'      => 'para jantar',
            'almoço'      => 'para almoço',
            'almoco'      => 'para almoço',
        ];

        foreach ($mapas as $palavra => $categoria) {
            if (str_contains($texto, $palavra)) {
                $row = DB::table('memoria_aprendizado')
                    ->where('tipo', 'preferencia')
                    ->where('dado', $categoria)
                    ->first();

                if ($row) {
                    DB::table('memoria_aprendizado')
                        ->where('id', $row->id)
                        ->update([
                            'contador'   => $row->contador + 1,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('memoria_aprendizado')->insert([
                        'tipo'       => 'preferencia',
                        'dado'       => $categoria,
                        'contador'   => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * 🗣️ FORMATA PREÇO PARA TTS
     */
    protected function formatarPrecoVoz(float $preco): string
    {
        $preco = round($preco, 2);

        $d = floor($preco);
        $c = (int) round(($preco - $d) * 100);

        $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);

        if ($d == 0 && $c > 0) {
            return $fmt->format($c) . ' centavos';
        }

        if ($d == 1 && $c == 0) {
            return 'um dólar';
        }

        if ($d > 1 && $c == 0) {
            return $fmt->format($d) . ' dólares';
        }

        if ($d > 0 && $c > 0) {
            return $fmt->format($d) . ' dólares e ' . $fmt->format($c) . ' centavos';
        }

        return $fmt->format($d) . ' dólares';
    }

    /**
     * 🔒 Detecta pedidos REAIS de produto
     */
    protected function pedidoEstritamenteDeProduto(string $t): bool
    {
        $t = mb_strtolower($t, 'UTF-8');

        if (preg_match('/\d+\s*ml|\d+\s*l/i', $t)) {
            return true;
        }

        if (preg_match('/acima|maior que|menor que|ate|até|entre/i', $t)) {
            return true;
        }

        if (preg_match('/\d+(,|\.)?\d*\s*(usd|dolar|dólar)/i', $t)) {
            return true;
        }

        if (preg_match('/whisky|whiskey|vinho|vino|vodka|gin|licor|cachac|cerveja|espumante|champagne/i', $t)) {
            return true;
        }

        return false;
    }
}
