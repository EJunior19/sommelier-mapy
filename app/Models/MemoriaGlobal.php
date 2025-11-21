<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoriaGlobal extends Model
{
    protected $table = 'memoria_global';
    protected $fillable = ['contexto', 'ultima_interacao'];
}
