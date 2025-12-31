<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SommelierProcedencia extends Model
{
    protected $table = 'sommelier_procedencias';

    protected $fillable = [
        'bebida_id',
        'nome_bebida',
        'pais_origem',
        'fonte',
        'confianca',
    ];

    public $timestamps = true;
}
