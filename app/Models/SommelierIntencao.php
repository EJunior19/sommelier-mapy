<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SommelierIntencao extends Model
{
    protected $table = 'sommelier_intencoes';
    protected $fillable = ['chave', 'resposta'];
}
