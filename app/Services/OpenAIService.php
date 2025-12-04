<?php

namespace App\Services;

use OpenAI;
use Throwable;
use App\Helpers\SommelierLog;

class OpenAIService
{
    private ?\OpenAI\Client $client = null;
    private ?string $apiKey = null;
    private ?string $projectId = null;

    private static ?self $instanciaUnica = null;

    public function __construct()
    {
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
                SommelierLog::error('❌ OpenAIService: API key não configurada.');
                return;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ];

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
            SommelierLog::info('🔥 OpenAIService inicializado.');
        } catch (Throwable $e) {
            SommelierLog::error('❌ Erro ao inicializar OpenAIService: ' . $e->getMessage());
            $this->client = null;
        }
    }

    # ============================================================
    #  🔥  SANITIZAÇÃO UNIVERSAL (ANTI JSON-BUG / ANTI ASPAS)
    # ============================================================
    private function sanitizarResposta(?string $txt): ?string
    {
        if (!$txt) return null;

        $txt = str_replace(["\n", "\r"], " ", $txt);
        $txt = trim($txt, "\"' ");
        $txt = str_replace(['{', '}', '[', ']'], '', $txt);
        $txt = preg_replace('/\s+/', ' ', $txt);

        return trim($txt);
    }

    # ============================================================
    #  🧠  IA — Conversa Geral
    # ============================================================
    public function responder(string $mensagem, ?string $contexto = null): ?string
    {
        if (!$this->client) return null;

        $mensagem = trim($mensagem);
        if ($mensagem === '') return null;

        SommelierLog::info('💬 IA responder() — entrada', ['texto' => $mensagem]);

        try {
            $historico = session('historico_mapy', []);

            $historicoTexto = collect($historico)
                ->take(-8)
                ->filter(fn($m) =>
                    !preg_match('/Bem-vindo ao Shopping Mapy/i', $m['assistente']) &&
                    !preg_match('/Ótima (tarde|noite|dia)/i', $m['assistente'])
                )
                ->map(fn($m) =>
                    "Cliente: {$m['cliente']}\nSommelier: {$m['assistente']}"
                )
                ->join("\n\n");

            if ($contexto) {
                $historicoTexto .= "\n\n" . $contexto;
            }

            $response = $this->client->chat()->create([
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.55,
                'max_tokens'  => 450,
                'messages'    => [
                    [
                        'role' => 'system',
                        'content' => <<<SYS
Você é a Sommelier Virtual do Shopping Mapy.

REGRAS:
- Nunca invente bebidas, marcas, volumes ou preços.
- Se não souber, peça detalhes.
- Responda curto, humano, simpático.
- Máximo 2 emojis.
- Nunca gere a saudação padrão do Shopping Mapy.
- Idioma conforme cliente (PT/ES).
SYS
                    ],
                    ['role' => 'user', 'content' => $mensagem],
                ],
            ]);

            $texto = $response->choices[0]->message->content ?? null;
            $texto = $this->sanitizarResposta($texto);

            SommelierLog::info('🤖 IA responder() — saída', ['resposta' => $texto]);

            return $texto;

        } catch (Throwable $e) {
            SommelierLog::error('❌ Erro em responder(): ' . $e->getMessage());
            return null;
        }
    }

    # ============================================================
    #  🔒  IA — Responder Somente com Opções do Banco
    # ============================================================
    public function responderComOpcoes(string $mensagemCliente, array $opcoes): ?string
    {
        if (!$this->client) return null;
        if (empty($opcoes)) return null;

        $opcoes = array_slice($opcoes, 0, 8);

        SommelierLog::info('🟦 IA responderComOpcoes — entrada', [
            'pergunta' => $mensagemCliente,
            'opcoes' => $opcoes
        ]);

        $lista = collect($opcoes)
            ->values()
            ->map(fn($t, $i) => ($i + 1) . ") " . $t)
            ->join("\n");

        $promptUser = <<<TXT
O cliente perguntou:
"{$mensagemCliente}"

Estas bebidas estão disponíveis:
{$lista}

Ajude o cliente a escolher a melhor opção.
TXT;

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 320,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<SYS
Você é a Sommelier Virtual do Shopping Mapy.

REGRAS ABSOLUTAS:
- Só cite bebidas da lista.
- Não invente marcas, preços ou volumes.
- Sempre USD.
- Máximo 2 emojis.
SYS
                    ],
                    ['role' => 'user', 'content' => $promptUser],
                ],
            ]);

            $txt = $this->sanitizarResposta($response->choices[0]->message->content ?? null);

            SommelierLog::info('🟩 IA responderComOpcoes — saída', ['resposta' => $txt]);

            return $txt;

        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro em responderComOpcoes(): " . $e->getMessage());
            return null;
        }
    }

    # ============================================================
    #  🎧  SPEECH-TO-TEXT
    # ============================================================
    public function audioParaTexto(string $arquivo): ?string
    {
        if (!$this->client) return null;

        SommelierLog::info("🎧 Iniciando transcrição", ['arquivo' => $arquivo]);

        try {
            $response = $this->client->audio()->transcribe([
                'model' => 'gpt-4o-mini-transcribe',
                'file'  => fopen($arquivo, 'r'),
            ]);

            $txt = $this->sanitizarResposta($response->text ?? null);

            SommelierLog::info("📄 Transcrição gerada", ['texto' => $txt]);

            return $txt;

        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro em audioParaTexto(): " . $e->getMessage());
            return null;
        }
    }

    # ============================================================
    #  🔧  NORMALIZADOR DE CONSULTA
    # ============================================================
    public function normalizeQuery(string $texto): ?string
    {
        SommelierLog::info("🔧 Normalizador — entrada", ['texto' => $texto]);

        $prompt = <<<PROMPT
Corrija erros do texto e normalize para busca de bebidas.

REGRAS:
- Corrigir ortografia.
- Remover gírias.
- Manter somente: categoria, marca, volume, faixa de preço.
- Não inventar nada.
- Retornar apenas a frase corrigida.

Texto:
"{$texto}"
PROMPT;

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'max_tokens' => 50,
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um normalizador preciso.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $txt = $this->sanitizarResposta($response->choices[0]->message->content ?? null);

            SommelierLog::info("🔧 Normalizador — saída", ['normalizado' => $txt]);

            return $txt;

        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro no normalizeQuery(): " . $e->getMessage());
            return null;
        }
    }

    # ============================================================
    #  🔊  TEXTO → ÁUDIO (TTS)
    # ============================================================
    public function gerarAudio(string $texto): ?string
    {
        if (!$this->client) return null;

        SommelierLog::info("🔊 TTS gerarAudio() — entrada", ['texto' => $texto]);

        try {
            $texto = $this->sanitizarResposta($texto);
            if (!$texto) return null;

            $texto = $this->naturalizarParaTTS($texto);
            $texto = "[pt-BR] " . $texto;

            $audio = $this->client->audio()->speech([
                'model'  => 'gpt-4o-mini-tts',
                'voice'  => 'nova',
                'input'  => $texto,
                'format' => 'mp3',
            ]);

            $file = "voz_" . time() . ".mp3";
            $path = storage_path("app/public/{$file}");

            file_put_contents($path, $audio);

            SommelierLog::info("🔊 TTS gerarAudio() — arquivo gerado", ['arquivo' => $file]);

            return asset("storage/{$file}");

        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro gerarAudio(): " . $e->getMessage());
            return null;
        }
    }

    # ============================================================
    #  🔉  TTS Helpers
    # ============================================================
    private function naturalizarParaTTS(string $t): string
    {
        $t = str_replace(['•', '*', '_'], ' ', $t);
        $t = str_replace(["\n", "\r"], '. ', $t);
        return preg_replace('/\s+/', ' ', $t);
    }
}
