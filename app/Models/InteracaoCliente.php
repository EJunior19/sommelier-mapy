<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteracaoCliente extends Model
{
    use HasFactory;

    // 🔹 Corrige o nome da tabela
    protected $table = 'interacoes_clientes';

    // 🔹 Define os campos permitidos
    protected $fillable = ['entrada', 'resposta', 'tipo'];

    public $timestamps = true;
}
