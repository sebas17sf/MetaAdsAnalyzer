<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdsData extends Model
{
    protected $table = 'MetaAdsData';

    protected $fillable = [
        'InicioInforme',
        'FinInforme',
        'NombreConjuntoAnuncios',
        'EntregaConjuntoAnuncios',
        'Puja',
        'TipoPuja',
        'PresupuestoConjuntoAnuncios',
        'TipoPresupuestoConjuntoAnuncios',
        'UltimoCambioSignificativo',
        'ConfiguracionAtribucion',
        'Resultados',
        'IndicadorResultado',
        'Alcance',
        'Impresiones',
        'CostoPorResultados',
        'ImporteGastadoUSD',
        'Finalizacion',
        'Inicio',
        'FechaCarga',
        'CargadoPor'
    ];

    public $timestamps = false;
}
