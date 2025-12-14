<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bebida extends Model
{
    // Tabla
    protected $table = 'bebidas';

    // NO hay timestamps en la tabla
    public $timestamps = false;

    // Campos reales de la BD
    protected $fillable = [
        'nombre',
        'nome_limpo',
        'tipo',
        'precio',
        'stock',
        'marca',
        'marca_normalizada',
        'volume_ml',
        'pais_origem',
        'procedencia',
        'busca_composta',
    ];

    // Casts correctos
    protected $casts = [
        'precio'   => 'float',
        'stock'    => 'int',
        'volume_ml'=> 'int',
    ];

    /**
     * -----------------------------------------
     * 🔎 BUSCA RÁPIDA (usa índice GIN)
     * -----------------------------------------
     */
    public function scopeSearch($query, string $texto)
    {
        return $query->where('busca_composta', 'ILIKE', "%{$texto}%");
    }
}
