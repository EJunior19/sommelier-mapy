<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAIService
{
    /** @var \OpenAI\Client|null */
    private ?\OpenAI\Client $client = null;
    private ?string $apiKey = null;
    private ?string $projectId = null;

    private static ?self $instanciaUnica = null;

    public function __construct()
    {
        // 🧠 Singleton simples para evitar recriar client a cada request
        if (self::$instanciaUnica instanceof self) {
            $this->client    = self::$instanciaUnica->client;
            $this->apiKey    = self::$instanciaUnica->apiKey;
            $this->projectId = self::$instanciaUnica->projectId;
            return;
        }

        try {
            $this->apiKey    = config('services.openai.key');
            $this->projectId = config('services.openai.project');

            if (empty($this->apiKey)) {
                Log::error('❌ OpenAIService: API key não configurada em services.openai.key');
                return;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ];

            // Para chaves de projeto (sk-proj-...), adiciona o cabeçalho de projeto
            if (str_starts_with($this->apiKey, 'sk-proj-') && !empty($this->projectId)) {
                $headers['OpenAI-Project'] = $this->projectId;
            }

            $this->client = OpenAI::factory()
                ->withApiKey($this->apiKey)
                ->withHttpClient(new \GuzzleHttp\Client([
                    'headers' => $headers,
                ]))
                ->make();

            self::$instanciaUnica = $this;
            Log::info('🔥 OpenAIService inicializado (instância única).');
        } catch (Throwable $e) {
            Log::error('❌ Erro ao inicializar OpenAIService: ' . $e->getMessage());
            $this->client = null;
        }
    }

    /**
     * 🧠 IA — Resposta textual genérica (fallback de conversa)
     *
     * → NÃO pode inventar produtos, marcas, preços ou volumes.
     * → NÃO pode repetir a saudação longa do Shopping Mapy.
     */
    public function responder(string $mensagem, ?string $contexto = null): ?string
    {
        if (!$this->client) {
            return null;
        }

        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            return null;
        }

        try {
            // ---------------------------------
            // 🔎 Histórico recente da sessão
            // ---------------------------------
            $historico = session('historico_mapy', []);

            // Máx. 8 interações curtas pra economizar tokens
            $historicoTexto = collect($historico)
                ->take(-8)
                ->filter(function ($m) {
                    // remove saudações longas do assistente
                    return !preg_match('/Bem-vindo ao Shopping Mapy/i', $m['assistente'])
                        && !preg_match('/Ótima tarde|Ótimo dia|Ótima noite/i', $m['assistente']);
                })
                ->map(function ($m) {
                    return "Cliente: {$m['cliente']}\nSommelier: {$m['assistente']}";
                })
                ->join("\n\n");

            if ($contexto) {
                $historicoTexto .= "\n\nContexto adicional:\n" . $contexto;
            }

            $response = $this->client->chat()->create([
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.55,
                'max_tokens'  => 450,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => <<<SYS
Você é a **Sommelier Virtual do Shopping Mapy**, especialista em bebidas alcoólicas e não alcoólicas.

REGRAS CRÍTICAS (NÃO QUEBRAR):
- Nunca invente produtos, marcas, rótulos, volumes ou preços.
- Se não souber o nome exato de uma bebida, peça para o cliente repetir ou descrevê-la melhor.
- Se precisar citar uma bebida, faça isso de forma genérica (ex.: "um vinho tinto suave", "um espumante doce"), sem inventar rótulos.
- Não recomende remédios, suplementos, cigarros, aparelhos eletrônicos, roupas ou qualquer coisa fora de bebidas.
- Se a pergunta não for sobre bebidas, responda gentilmente que seu foco é apenas bebidas.

SAUDAÇÕES:
- Você **NUNCA** deve gerar a saudação padrão do Shopping Mapy
  (por exemplo: "Ótimo dia ☀️! Bem-vindo ao Shopping Mapy..." ou variações).
- Quando o cliente disser "bom dia / boa tarde / boa noite / oi / tudo bem", responda apenas com algo curto, natural:
  - Ex: "Tudo ótimo! Como posso te ajudar com as bebidas?"
  - Ex: "Oi! Me conta o que você está procurando para beber."
- Não escreva "Bem-vindo ao Shopping Mapy" em nenhuma resposta (isso já é feito pelo sistema externo).

ESTILO:
- Tom próximo, simpático, educado, como um atendente humano.
- Respostas curtas e diretas (geralmente 1–2 parágrafos).
- Pode usar no máximo 2 emojis, e apenas se fizer sentido.
- Ajude o cliente a decidir, fazendo perguntas simples quando necessário (doce, seco, forte, ocasião, faixa de preço).

IDIOMA:
- Se o cliente escrever em português, responda em português.
- Se escrever em espanhol, responda em espanhol.
- Nunca misture muitos idiomas na mesma frase.

HISTÓRICO (apenas contexto, NÃO responder sobre isso diretamente):
{$historicoTexto}
SYS
                    ],
                    [
                        'role'    => 'user',
                        'content' => $mensagem,
                    ],
                ],
            ]);

            $texto = trim($response->choices[0]->message->content ?? '');

            if ($texto === '') {
                return null;
            }

            return $texto;
        } catch (Throwable $e) {
            Log::error('❌ Erro em responder(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🧠 IA — Resposta usando SOMENTE bebidas vindas do banco
     *
     * @param string $mensagemCliente  Texto original do cliente
     * @param array  $opcoes           Lista de strings já formatadas: "Nome — 750 ML — 30,00 dólares"
     */
    public function responderComOpcoes(string $mensagemCliente, array $opcoes): ?string
    {
        if (!$this->client) {
            return null;
        }

        if (empty($opcoes)) {
            return null;
        }

        // Limita para não gastar tokens demais
        $opcoes = array_slice($opcoes, 0, 8);

        $listaOpcoes = collect($opcoes)
            ->values()
            ->map(fn($txt, $i) => ($i + 1) . ') ' . $txt)
            ->join("\n");

        $promptUsuario = <<<USER
O cliente perguntou:
"{$mensagemCliente}"

Estas são as bebidas disponíveis no estoque (NÃO invente outras):

{$listaOpcoes}

Com base nisso, ajude o cliente a escolher.
USER;

        try {
            $response = $this->client->chat()->create([
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens'  => 320,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => <<<SYS
Você é a **Sommelier Virtual do Shopping Mapy**.

REGRAS IMPORTANTES:
- Só pode recomendar bebidas que apareçam na lista enviada.
- NÃO invente produtos, marcas, volumes, sabores ou preços.
- Se a lista não combinar com o pedido, explique isso e sugira o que chega mais perto, sem criar itens novos.
- Use de 1 a 3 recomendações no máximo.
- Use justificativas simples: momento (churrasco, presente, família, festa, frio, calor), perfil (doce, seco, leve, forte) e preço.
- Não fique repetindo a lista inteira se não for necessário.
- Se o cliente pedir "a mais barata", "a mais cara", "algo em torno de X dólares", baseie-se apenas na lista enviada.
- Não repita a saudação longa do Shopping Mapy, nem "Bem-vindo ao Shopping Mapy".

ESTILO:
- Linguagem simples, humana e próxima, como conversa de loja.
- No máximo 2 parágrafos curtos, sem texto muito longo.
- Pode usar 1 ou 2 emojis, no máximo.
- Termine, se fizer sentido, com uma pergunta de continuação (ex.: "Prefere algo mais doce ou mais seco?").

IDIOMA:
- Se o cliente escreveu em português, responda em português.
- Se escreveu em espanhol, responda em espanhol.
SYS
                    ],
                    [
                        'role'    => 'user',
                        'content' => $promptUsuario,
                    ],
                ],
            ]);

            $texto = trim($response->choices[0]->message->content ?? '');

            if ($texto === '') {
                return null;
            }

            return $texto;
        } catch (Throwable $e) {
            Log::error('❌ Erro em responderComOpcoes(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🎤 SPEECH-TO-TEXT — Áudio → Texto
     */
    public function audioParaTexto(string $caminhoAudio): ?string
    {
        if (!$this->client) {
            return null;
        }

        try {
            Log::info("🎧 Iniciando transcrição do áudio: {$caminhoAudio}");

            $response = $this->client->audio()->transcribe([
                'model' => 'gpt-4o-mini-transcribe',
                'file'  => fopen($caminhoAudio, 'r'),
            ]);

            $texto = trim($response->text ?? '');

            return $texto !== '' ? $texto : null;
        } catch (Throwable $e) {
            Log::error('❌ Erro em audioParaTexto(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔊 TEXTO → Áudio (TTS)
     */
    public function gerarAudio(string $texto): ?string
    {
        if (!$this->client) {
            return null;
        }

        try {
            Log::info('🔊 Gerando áudio para texto (orig): ' . mb_substr($texto, 0, 180) . '...');

            // 1) Limpa para TTS (remove emojis, ajusta pontuação, ml, etc.)
            $textoLimpo = $this->limparParaTTS($texto);

            // 2) Deixa o texto mais natural para leitura em voz alta
            $textoLimpo = $this->naturalizarParaTTS($textoLimpo);

            // 3) Normaliza espaços
            $textoLimpo = preg_replace('/\s+/', ' ', $textoLimpo);
            $textoLimpo = trim($textoLimpo);

            if ($textoLimpo === '') {
                return null;
            }

            // Força o TTS a falar exclusivamente em português brasileiro
            $textoPT = "[pt-BR] " . $textoLimpo;

            $result = $this->client->audio()->speech([
                'model'  => 'gpt-4o-mini-tts',
                'voice'  => 'nova',
                'input'  => $textoPT,
                'format' => 'mp3',
            ]);


            $fileName = 'voz_' . time() . '.mp3';
            $path     = storage_path("app/public/{$fileName}");

            file_put_contents($path, $result);

            return asset("storage/{$fileName}");
        } catch (Throwable $e) {
            Log::error('❌ Erro ao gerar áudio: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Deixa o texto mais “humano” para o TTS (pausas, termos, tamanho)
     */
    private function naturalizarParaTTS(string $texto): string
    {
        // Substitui expressões que soam robóticas
        $texto = str_ireplace(
            ['significa que', 'significa', 'versátil', 'versatilidade'],
            [
                'quer dizer que',
                'quer dizer',
                'que dá para usar de vários jeitos',
                'que dá para usar em várias situações',
            ],
            $texto
        );

        // Quebra frases muito longas em pedaços menores
        $partes = preg_split('/(\.|\?|!)/u', $texto);
        $partes = array_map('trim', $partes);
        $partes = array_filter($partes);

        $texto = implode('. ', $partes);

        // Evita texto gigante em uma frase só
        if (strlen($texto) > 260) {
            $texto = wordwrap($texto, 200, '. ', true);
        }

        return $texto;
    }

    /**
     * Limpa emojis, melhora pontuação e converte unidades para TTS
     */
    private function limparParaTTS(string $texto): string
    {
        // 1. Remover emojis
        $texto = $this->removerEmojis($texto);

        // 2. Normalizar espaços
        $texto = preg_replace('/\s+/', ' ', $texto);

        // 3. Converter marcadores de lista para algo que soe bem
        $texto = str_replace(['•', '- '], ' - ', $texto);

        // 4. Ajustar pontuação para pausas melhores
        $texto = preg_replace('/\.\s*/', '. ', $texto);
        $texto = preg_replace('/,\s*/', ', ', $texto);
        $texto = preg_replace('/\?/', '? ... ', $texto);
        $texto = str_replace(['...', '…'], '... ', $texto);

        // 5. Converter "750 ml" para "setecentos e cinquenta mililitros"
        $texto = preg_replace_callback('/(\d+)\s*ml/i', function ($m) {
            $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
            return $fmt->format((int)$m[1]) . ' mililitros';
        }, $texto);

        // 6. Converter abreviações de moeda
        $texto = str_ireplace(['U$', 'USD'], 'dólares', $texto);

        return trim($texto);
    }

    /**
     * Remove emojis de uma string
     */
    private function removerEmojis(string $texto): string
    {
        return preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{200D}\x{FE0F}]/u',
            ' ',
            $texto
        );
    }
}
