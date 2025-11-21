<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bebida extends Model
{
    use HasFactory;

    // Nombre de la tabla (opcional si coincide con plural del modelo)
    protected $table = 'bebidas';

    // Campos que pueden asignarse masivamente
    protected $fillable = [
        'nombre',
        'tipo',
        'precio',
        'stock',
        'alcohol',
        'codigo_barras'
    ];

    // Habilitar timestamps automáticos (ya los tienes en tu BD)
    public $timestamps = true;

    // Conversión de tipos automática (casts)
    protected $casts = [
        'precio' => 'float',
        'stock' => 'float',
        'alcohol' => 'boolean',
    ];

    // dentro de App\Models\Bebida.php
public function scopeSearch($query, $texto)
{
    return $query->whereRaw('nombre ILIKE ?', ["%{$texto}%"]);
}
}

