<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarStock extends Command
{
    protected $signature = 'sommelier:importar-estoque 
        {arquivo=database/updates/estoque_bebidas_2025-10-28.txt}';

    protected $description = 'Importa e atualiza o estoque de bebidas a partir de arquivo TXT';

    public function handle(): int
    {
        $arquivo = base_path($this->argument('arquivo'));

        if (!file_exists($arquivo)) {
            $this->error("❌ Arquivo não encontrado: {$arquivo}");
            return self::FAILURE;
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $atualizados = 0;
        $novos       = 0;
        $ignorados   = 0;

        $this->info("📄 Lendo arquivo: " . basename($arquivo));
        $this->info("🔍 Processando " . count($linhas) . " linhas...\n");

        foreach ($linhas as $linha) {

            // ✔️ Solo líneas válidas del reporte
            if (!str_starts_with($linha, 'Sucursal')) {
                $ignorados++;
                continue;
            }

            // Elimina rodapé do relatório
            $linha = preg_replace('/Total General:.*/', '', $linha);
            $cols  = preg_split("/\t+/", trim($linha));

            if (count($cols) < 6) {
                $ignorados++;
                continue;
            }

            [
                $sucursal,
                $codigoInterno,
                $nomeCompleto,
                $quantidadeRaw,
                $precoRaw,
                $codigoBarras
            ] = array_map('trim', $cols);

            // 🔤 Corrige encoding
            $nomeCompleto = iconv('Windows-1252', 'UTF-8//IGNORE', $nomeCompleto);

            // ❌ Ignorar vapeadores y derivados
            if (stripos($nomeCompleto, 'VAPE') !== false) {
                $ignorados++;
                continue;
            }

            // ❌ Ignorar IGNITE
            if (stripos($nomeCompleto, 'IGNITE') !== false) {
                $ignorados++;
                continue;
            }

            // 🔢 Cantidad (segura)
            $quantidadeRaw = str_replace(['.', ','], ['', '.'], $quantidadeRaw);
            $quantidade = (int) floatval($quantidadeRaw);

            // 💰 Precio — NORMALIZACIÓN SEGURA
            $precoRaw = str_replace(' ', '', $precoRaw);

            // Formato latino
            if (str_contains($precoRaw, ',')) {
                $precoRaw = str_replace('.', '', $precoRaw);
                $precoRaw = str_replace(',', '.', $precoRaw);
            }

            $preco = floatval($precoRaw);

            // ❌ Reglas de descarte de precio
            if ($preco < 5 || $preco > 1000) {
                $ignorados++;
                continue;
            }

            // ❌ Nombre vacío
            if (empty($nomeCompleto)) {
                $ignorados++;
                continue;
            }

            // 🔍 Normalización simple para matching
            $nomeNormalizado = mb_strtolower(trim($nomeCompleto), 'UTF-8');

            $existe = DB::table('bebidas')
                ->whereRaw('LOWER(nombre) = ?', [$nomeNormalizado])
                ->first();

            if ($existe) {

                DB::table('bebidas')
                    ->where('id', $existe->id)
                    ->update([
                        'precio' => $preco,
                        'stock'  => $quantidade,
                    ]);

                $atualizados++;

            } else {

                DB::table('bebidas')->insert([
                    'nombre'     => $nomeCompleto,
                    'precio'     => $preco,
                    'stock'      => $quantidade,
                    'tipo'       => null,
                    'volume_ml'  => null,
                    'nome_limpo' => null,
                ]);

                $novos++;
            }
        }

        $this->newLine();
        $this->info("✅ Importação concluída com sucesso!");
        $this->line("🟢 {$novos} novos registros inseridos");
        $this->line("🟡 {$atualizados} registros atualizados");
        $this->line("⚪ {$ignorados} linhas ignoradas");

        return self::SUCCESS;
    }
}
