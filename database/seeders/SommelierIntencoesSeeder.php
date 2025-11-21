<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SommelierIntencoesSeeder extends Seeder
{
    public function run(): void
    {
        $dados = [

            // 🍖 CHURRASCO
            ['chave' => 'churrasco', 'resposta' =>
                'Para churrasco, ótimas opções são vinhos Malbec, cervejas encorpadas e espumantes brut. Quer que eu liste?'
            ],
            ['chave' => 'churrasco', 'resposta' =>
                'Se for churrasco, Malbec e cervejas bem geladas são perfeitos. Posso mostrar algumas opções do estoque.'
            ],
            ['chave' => 'churrasco', 'resposta' =>
                'Carne assada combina muito com Malbec ou cervejas fortes. Quer sugestões específicas?'
            ],

            // 🥩 CARNE VERMELHA
            ['chave' => 'carne', 'resposta' =>
                'Carne vermelha combina com vinhos encorpados como Malbec e Cabernet Sauvignon. Quer ver opções?'
            ],
            ['chave' => 'carne', 'resposta' =>
                'Para carne vermelha, vinhos fortes são ideais. Malbec cai perfeitamente! Deseja sugestões?'
            ],
            ['chave' => 'carne', 'resposta' =>
                'Se você vai comer carne, tintos encorpados são recomendados. Posso listar alguns.'
            ],

            // 🍗 FRANGO / CARNE BRANCA
            ['chave' => 'frango', 'resposta' =>
                'Carne branca combina muito bem com espumantes brut e vinhos brancos leves. Quer sugestões?'
            ],
            ['chave' => 'frango', 'resposta' =>
                'Para frango, recomendo vinhos brancos, rosés ou espumantes suaves. Quer ver algumas opções?'
            ],
            ['chave' => 'frango', 'resposta' =>
                'Pratos com frango vão muito bem com Sauvignon Blanc e Espumante Brut. Posso listar alguns.'
            ],

            // 🐟 PEIXE
            ['chave' => 'peixe', 'resposta' =>
                'Para peixe, vinhos brancos como Sauvignon Blanc e Chardonnay combinam muito. Deseja sugestões?'
            ],
            ['chave' => 'peixe', 'resposta' =>
                'Peixes e frutos do mar vão bem com vinhos brancos leves ou espumantes brut. Quer ajudar a escolher?'
            ],
            ['chave' => 'peixe', 'resposta' =>
                'Para pratos com peixe, sugiro bebidas leves: Chardonnay, rosés ou espumantes. Posso mostrar?'
            ],

            // 🍕 PIZZA
            ['chave' => 'pizza', 'resposta' =>
                'Pizza combina muito bem com vinhos tintos leves ou cervejas artesanais. Quer sugestões?'
            ],
            ['chave' => 'pizza', 'resposta' =>
                'Para pizza, vinhos como Merlot ou cervejas artesanais são ótimas opções. Listo algumas?'
            ],

            // 🍔 HAMBÚRGUER
            ['chave' => 'hamburguer', 'resposta' =>
                'Hambúrguer combina com cervejas encorpadas e vinhos tintos médios. Quer ver opções?'
            ],
            ['chave' => 'hamburguer', 'resposta' =>
                'Para hambúrguer, vinhos como Cabernet e cervejas fortes são ideais. Posso mostrar algumas opções.'
            ],

            // 🍫 DOCE
            ['chave' => 'doce', 'resposta' =>
                'Bebidas doces? Temos moscatel, licores e vinhos suaves ótimos. Quer ver sugestões?'
            ],
            ['chave' => 'doce', 'resposta' =>
                'Se você gosta de doce, posso indicar licores, vinhos suaves ou espumantes moscatel.'
            ],
            ['chave' => 'doce', 'resposta' =>
                'Para quem prefere doce, espumante moscatel é excelente. Mostro algumas opções?'
            ],

            // 🧀 QUEIJOS
            ['chave' => 'queijo', 'resposta' =>
                'Queijos combinam muito bem com vinhos brancos e espumantes. Quer sugestões?'
            ],
            ['chave' => 'queijo', 'resposta' =>
                'Para tábuas de queijo, vinhos brancos aromáticos e rosés são ótimos. Listo algumas opções?'
            ],

            // 🍝 MASSA / PASTA
            ['chave' => 'massa', 'resposta' =>
                'Massas combinam com vinhos tintos suaves e rosés. Quer sugestões?'
            ],
            ['chave' => 'massa', 'resposta' =>
                'Macarrão e lasanha vão muito bem com Merlot ou Carménère. Posso mostrar opções.'
            ],

            // 🎉 FESTA / ENCONTRO
            ['chave' => 'festa', 'resposta' =>
                'Para festa, espumantes brut, cervejas e drinks sempre funcionam bem. Quer ver sugestões?'
            ],
            ['chave' => 'festa', 'resposta' =>
                'Vai rolar festa? Posso te sugerir espumantes, vinhos suaves e bebidas práticas. Quer opções?'
            ],
            ['chave' => 'festa', 'resposta' =>
                'Para eventos e festas, espumantes e cervejas são os mais procurados. Listo opções?'
            ],

            // 🧊 CALOR
            ['chave' => 'calor', 'resposta' =>
                'Para o calor, espumantes brut, cervejas leves e drinks refrescantes são ótimas escolhas.'
            ],
            ['chave' => 'calor', 'resposta' =>
                'No calor, bebidas geladas como espumantes e cervejas leves vão muito bem. Quer sugestões?'
            ],

            // ❄ FRIO
            ['chave' => 'frio', 'resposta' =>
                'No frio, vinhos tintos encorpados como Malbec e Carménère são perfeitos. Quer ver opções?'
            ],
            ['chave' => 'frio', 'resposta' =>
                'Clima frio combina muito com vinhos fortes. Posso listar alguns?'
            ],

            // 🥗 COMIDAS LEVES
            ['chave' => 'leve', 'resposta' =>
                'Para comidas leves, escolha vinhos brancos, rosés ou espumantes. Posso te mostrar opções.'
            ],
            ['chave' => 'leve', 'resposta' =>
                'Pratos leves combinam com bebidas refrescantes: espumantes e vinhos brancos. Quer sugestões?'
            ],

            // ❓ PEDIDOS GENÉRICOS
            ['chave' => 'bebidas', 'resposta' =>
                'Temos diversas bebidas! Se quiser, posso listar por tipo: vinhos, cervejas, espumantes ou licores.'
            ],
            ['chave' => 'bebidas', 'resposta' =>
                'Claro! Temos várias bebidas disponíveis. Prefere vinho, cerveja, espumante ou licor?'
            ],
            ['chave' => 'bebidas', 'resposta' =>
                'Sim, temos muitas opções de bebidas. Quer que eu liste por categoria?'
            ],
        ];

        DB::table('sommelier_intencoes')->insert($dados);
    }
}
