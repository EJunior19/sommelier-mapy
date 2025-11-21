<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\OracleConsultaController;

/*
|--------------------------------------------------------------------------
| Rotas Web do Sommelier Virtual Mapy
|--------------------------------------------------------------------------
| Estas rotas controlam o fluxo principal da aplicação.
| A interface do chat acessa as rotas abaixo:
|   - "/" → carrega a interface do sommelier
|   - "/responder" → envia a mensagem para o controlador
|
| Todas as respostas são processadas pelo OpenAIService.
| Linguagem padrão: português brasileiro 🇧🇷
*/

Route::get('/', function () {
    return view('asistente');
});

// 🔹 Rota principal de comunicação com o Sommelier
Route::post('/responder', [AsistenteController::class, 'responder'])->name('asistente.responder');

// 🔹 Alias opcional (compatibilidade)
Route::post('/hablar', [AsistenteController::class, 'responder'])->name('asistente.hablar');

// 🔹 Rota de teste rápido (verificar backend sem front-end)
Route::get('/debug-sommelier', function () {
    $service = app(\App\Services\OpenAIService::class);
    $texto = $service->responder('Teste rápido do Sommelier Virtual', 'Fale curto, educado e natural.');
    return response()->json([
        'ok' => $texto !== null,
        'resposta' => $texto ?? 'Erro ao obter resposta do OpenAI.'
    ]);
   
});
