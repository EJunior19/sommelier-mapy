<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\OracleConsultaController;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Brain\SommelierBrain;
use App\Services\Sommelier\AI\OpenAISommelier;

/*
|--------------------------------------------------------------------------
| 🌐 Rotas Web — Sommelier Virtual Mapy
|--------------------------------------------------------------------------
| Fluxo principal do assistente:
|
|  GET  /                → Interface do Sommelier
|  POST /responder       → Envio de texto ou áudio (chat principal)
|
| Rotas auxiliares:
|  POST /reset-memoria   → Limpa memória contextual do cliente
|  GET  /debug-sommelier → Teste rápido do backend
|  GET  /debug-memoria   → Visualiza memória atual da sessão
|
| ⚠️ O Sommelier é STATEFUL:
| - Usa sessão Laravel
| - Mantém memória curta de contexto
| - Rotação de bebidas por sessão
|
| Idioma padrão: PT-BR 🇧🇷
|--------------------------------------------------------------------------
*/


// ======================================================
// 🟢 INTERFACE PRINCIPAL
// ======================================================
Route::get('/', function () {
    return view('asistente');
});


// ======================================================
// 🔹 ROTA PRINCIPAL DO SOMMELIER (CHAT + VOZ)
// ======================================================
Route::post(
    '/responder',
    [AsistenteController::class, 'responder']
)->name('asistente.responder');


// ======================================================
// 🔄 RESET DE MEMÓRIA CONTEXTUAL (UX / DEBUG)
// ======================================================
Route::post('/reset-memoria', function () {

    MemoriaContextualCurta::resetar();

    return response()->json([
        'ok'      => true,
        'mensagem'=> 'Memória do Sommelier resetada com sucesso.'
    ]);

})->name('sommelier.reset.memoria');


// ======================================================
// 🧠 DEBUG — VISUALIZAR MEMÓRIA ATUAL
// ======================================================
Route::get('/debug-memoria', function () {

    return response()->json([
        'memoria_contextual' => MemoriaContextualCurta::dump(),
        'bebidas_mostradas'  => session('bebidas_mostradas', []),
    ]);

})->name('sommelier.debug.memoria');


// ======================================================
// 🧪 DEBUG — TESTE RÁPIDO DO BACKEND (SEM FRONT)
// ======================================================
Route::get('/debug-memoria', function () {

    $ai = app(OpenAISommelier::class);
    $brain = new SommelierBrain($ai);

    $texto = $brain->responder(
        'Teste rápido do Sommelier Virtual'
    );

    return response()->json([
        'ok'       => !empty($texto),
        'resposta' => $texto
    ]);
})->name('sommelier.debug.memoria');
