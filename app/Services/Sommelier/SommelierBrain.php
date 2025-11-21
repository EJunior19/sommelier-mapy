<?php

namespace App\Services\Sommelier;

use App\Services\Sommelier\Buscador;
use App\Services\Sommelier\Intencoes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Services\OpenAIService;
use Throwable;

class SommelierBrain
{
    protected OpenAIService $openai;

    public function __construct(OpenAIService $openai)
    {
        $this->openai = $openai;
    }

    /**
     * ===========================================================
     * 🔥 CÉREBRO PRINCIPAL DO SOMMELIER
     * ===========================================================
     */
    public function responder(string $mensagem): string
    {
        $mensagem = trim($mensagem);

        if ($mensagem === '') {
            return 'Poderia reformular, por gentileza? Não consegui compreender sua pergunta.';
        }

        $textoOriginal = $mensagem;
        $textoLower    = mb_strtolower($mensagem, 'UTF-8');

        // ---------------------------------------
        // 🔁 RESET DA CONVERSA
        // ---------------------------------------
        if (preg_match('/\b(nova conversa|novo atendimento|reset|recomeçar|recomecar|limpar)\b/iu', $textoLower)) {
            Session::forget('historico_mapy');
            Session::forget('cumprimentou');

            return $this->saudacaoInicial(true);
        }

        // 🧠 APRENDIZADO AUTOMÁTICO (palavras + preferências)
        $this->treinarAprendizado($textoOriginal);
        $this->registrarAprendizado($textoOriginal);

        // 👋 Saudação (apenas 1x por sessão)
        $cumprimento = $this->saudacaoInicial();

        // 🧠 HISTÓRICO CURTO (para IA fallback)
        $historico = session('historico_mapy', []);
        $contexto  = collect($historico)
            ->take(-5)
            ->map(fn ($m) => "Cliente: {$m['cliente']} | Sommelier: {$m['assistente']}")
            ->join("\n");

        $resposta   = null;
        $origem     = 'conversa'; // intencao | busca_banco | ia | conversa
        $usouBanco  = false;

        // =======================================================
        // 1) ⚡ INTENÇÕES RÁPIDAS  (sem gastar IA pesada)
        // =======================================================
        try {
            $int = Intencoes::processar($textoOriginal);

            if (
                !empty($int['categoria']) ||
                !empty($int['marca'])     ||
                !empty($int['sensorial']) ||
                $int['precoMin'] !== null ||
                $int['precoMax'] !== null
            ) {
                // Gera resposta via busca combinada no banco
                $resPorIntencao = Buscador::buscarPorIntencoes($int, $textoOriginal);

                if (!empty($resPorIntencao)) {
                    $resposta  = $resPorIntencao;
                    $origem    = 'intencao';
                    $usouBanco = true;
                }
            }

        } catch (Throwable $e) {
            Log::error('⚠️ Erro ao processar intenções rápidas: ' . $e->getMessage());
        }

        // =======================================================
        // 2) 🔍 BUSCA DIRETA NO BANCO (TRGM + índices otimizados)
        // =======================================================
        if (!$resposta) {
            try {
                $resBusca = Buscador::buscar($textoOriginal);

                if (!empty($resBusca)) {
                    $resposta  = $resBusca;
                    $origem    = 'busca_banco';
                    $usouBanco = true;
                }
            } catch (Throwable $e) {
                Log::error('⚠️ Erro no Buscador (banco de dados): ' . $e->getMessage());
            }
        }

        // =======================================================
        // 3) 🤖 FALLBACK IA (quando DB + intenções não resolvem)
        //     — OpenAIService deve estar configurado para responder SEMPRE em português
        // =======================================================
        if (!$resposta) {
            try {
                $resIA = $this->openai->responder($textoOriginal, $contexto);

                // 🔒 Bloqueia respostas fora do nicho de bebidas
                if ($resIA && preg_match('/(remédio|medicamento|celular|roupa|notebook|curso)/iu', $resIA)) {
                    $resIA = null;
                }

                if (!empty($resIA)) {
                    $resposta = $resIA;
                    $origem   = 'ia';
                }
            } catch (Throwable $e) {
                Log::error('⚠️ Erro OpenAI: ' . $e->getMessage());
            }
        }

        // =======================================================
        // 4) 🧷 FALLBACK FINAL (nenhuma fonte respondeu)
        // =======================================================
        if (!$resposta) {
            $preferencias = DB::table('memoria_aprendizado')
                ->where('tipo', 'preferencia')
                ->orderByDesc('contador')
                ->limit(5)
                ->pluck('dado')
                ->toArray();

            if (!empty($preferencias)) {
                $lista    = implode(', ', $preferencias);
                $resposta = "Ainda não consegui identificar exatamente o que você procura, mas muitos clientes gostam de bebidas como: {$lista}. Posso sugerir alguma delas?";
            } else {
                $resposta = "Poderia me dizer se prefere algo doce, leve, encorpado ou mais forte? Assim consigo te indicar a bebida perfeita.";
            }

            $origem = 'conversa';
        }

        // =======================================================
        // 5) 💾 HISTÓRICO CURTO NA SESSÃO (sempre como string)
        // =======================================================
        $respString = is_string($resposta)
            ? $resposta
            : json_encode($resposta, JSON_UNESCAPED_UNICODE);

        $historico[] = [
            'cliente'    => $textoOriginal,
            'assistente' => mb_substr($respString, 0, 200),
            'momento'    => now()->toDateTimeString(),
        ];

        session(['historico_mapy' => array_slice($historico, -5)]);

        // =======================================================
        // 6) 🗄️ LOG EM BANCO (interacoes_clientes) — STRING!
        // =======================================================
        try {
            DB::table('interacoes_clientes')->insert([
                'tipo'       => $usouBanco ? 'busca_banco' : 'conversa',
                'entrada'    => $textoOriginal,
                'resposta'   => $respString,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('⚠️ Erro ao registrar interação no banco: ' . $e->getMessage());
        }

        // =======================================================
        // 7) SAUDAÇÃO x RESPOSTA
        //    - se usuário só disse “oi / bom dia”, não anexa saudação longa
        // =======================================================
        if ($this->ehCumprimentoSimples($textoOriginal)) {
            Session::put('cumprimentou', true);
            return trim($respString);
        }

        $final = $cumprimento
            ? "{$cumprimento} {$respString}"
            : $respString;

        Log::info('✅ SommelierBrain respondeu (origem=' . $origem . ')');

        return trim($final);
    }

    /**
     * Verifica se a mensagem é apenas um cumprimento simples
     */
    protected function ehCumprimentoSimples(string $texto): bool
    {
        return preg_match(
            '/^(oi|ola|olá|oie|oii+|bom dia|boa tarde|boa noite|tudo bem)$/iu',
            trim($texto)
        ) === 1;
    }

    /**
     * 👋 Saudação inicial automática (sempre em português)
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
     *
     * - Aprende novas palavras
     * - Reforça padrões
     * - Cria alias automaticamente
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

            // REFORÇO DE MEMÓRIA
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

                // ❗ PROMOÇÃO AUTOMÁTICA (vira alias global)
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

            // MEMÓRIA NOVA
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
     * 🗣️ FORMATA PREÇO PARA TTS (texto falado)
     */
    protected function formatarPrecoVoz(float $preco): string
    {
        $preco = round($preco, 2);

        $d = floor($preco);                    // parte inteira
        $c = (int) round(($preco - $d) * 100); // centavos

        $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);

        // 0.xx → apenas centavos
        if ($d == 0 && $c > 0) {
            return $fmt->format($c) . ' centavos';
        }

        // 1.00 → exatamente um dólar
        if ($d == 1 && $c == 0) {
            return 'um dólar';
        }

        // X.00 → dólares exatos
        if ($d > 1 && $c == 0) {
            return $fmt->format($d) . ' dólares';
        }

        // X.YY → dólares + centavos
        if ($d > 0 && $c > 0) {
            return $fmt->format($d) . ' dólares e ' . $fmt->format($c) . ' centavos';
        }

        // Fallback
        return $fmt->format($d) . ' dólares';
    }
}
