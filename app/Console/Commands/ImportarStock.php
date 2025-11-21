<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarStock extends Command
{
    protected $signature = 'sommelier:importar-estoque {arquivo=database/updates/estoque_bebidas_2025-10-28.txt}';
    protected $description = 'Importa e atualiza o estoque de bebidas a partir do relatório TXT exportado do sistema.';

    public function handle(): int
    {
        $arquivo = base_path($this->argument('arquivo'));

        if (!file_exists($arquivo)) {
            $this->error("❌ Arquivo não encontrado: {$arquivo}");
            return self::FAILURE;
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $atualizados = 0;
        $novos = 0;
        $ignorados = 0;

        $this->info("📄 Lendo arquivo: " . basename($arquivo));
        $this->info("🔍 Processando " . count($linhas) . " linhas...\n");

        foreach ($linhas as $linha) {
    if (!str_starts_with($linha, 'Sucursal')) continue;

    $linha = trim(preg_replace('/Total General:.*/', '', $linha));
    $cols = preg_split("/\t+/", trim($linha));

    if (count($cols) < 6) {
        $ignorados++;
        continue;
    }

    $sucursal       = trim($cols[0]);
    $codigoInterno  = trim($cols[1]);
    $nomeCompleto   = trim($cols[2]);
    $quantidadeRaw  = trim($cols[3]);
    $precoRaw       = trim($cols[4]);
    $codigoBarras   = trim($cols[5]);

    // Corrige acentuação
    $nomeCompleto = iconv('Windows-1252', 'UTF-8//IGNORE', $nomeCompleto);

    // Corrige separadores de milhar e decimais
    $quantidadeClean = str_replace('.', '', $quantidadeRaw); // remove pontos de milhar
    $quantidadeClean = str_replace(',', '.', $quantidadeClean);
    $quantidade = (int) floatval($quantidadeClean);

    // Corrige preço (remove milhar e converte)
    $precoClean = str_replace('.', '', $precoRaw);
    $precoClean = str_replace(',', '.', $precoClean);
    $preco = floatval($precoClean);

    // Garante que o código de barras seja sempre numérico e válido
    if (!preg_match('/^\d{6,15}$/', $codigoBarras)) {
        $codigoBarras = null;
    }

    // Ignora linhas inválidas
    if (empty($nomeCompleto) || $preco <= 0) {
        $ignorados++;
        continue;
    }

    $nomeNormalizado = mb_strtolower(trim($nomeCompleto), 'UTF-8');

    $existe = DB::table('bebidas')
        ->whereRaw('LOWER(TRIM(nombre)) = ?', [$nomeNormalizado])
        ->first();

    if ($existe) {
        DB::table('bebidas')->where('id', $existe->id)->update([
            'precio'        => $preco,
            'stock'         => $quantidade,
            'codigo_barras' => $codigoBarras,
            'updated_at'    => now(),
        ]);
        $atualizados++;
    } else {
        DB::table('bebidas')->insert([
            'nombre'        => $nomeCompleto,
            'tipo'          => null,
            'precio'        => $preco,
            'stock'         => $quantidade,
            'alcohol'       => true,
            'codigo_barras' => $codigoBarras,
            'marca'         => null,
            'volume_ml'     => null,
            'nome_limpo'    => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $novos++;
    }
}


        $this->newLine();
        $this->info("✅ Importação concluída com sucesso!");
        $this->line("🟢 {$novos} novos registros inseridos.");
        $this->line("🟡 {$atualizados} registros atualizados.");
        $this->line("⚪ {$ignorados} linhas ignoradas (inválidas ou incompletas).");

        return self::SUCCESS;
    }
}
