<?php

namespace Database\Seeders;

use App\Models\TipoCampo;
use Illuminate\Database\Seeder;

/**
 * Tipos de campo = taxonomía LOCAL de uso/deporte.
 *
 * NO se sincroniza con SIREB: el "rubro" de Paitití (alquiler_espacio, etc.)
 * es una clasificación de COBRO, distinta de esta clasificación DEPORTIVA.
 * Un mismo servicio SIREB puede cubrir canchas de futsal y de vóley; el tipo
 * local es lo que el ciudadano filtra en la web ("canchas de futsal").
 *
 * Corre en TODOS los ambientes (es dato maestro, no credencial ni UUID de test):
 * a diferencia de FuncionarioSeeder / MapeoSirebSeeder, este NO se guarda a local.
 */
class TipoCampoSeeder extends Seeder
{
    public function run(): void
    {
        // Los 4 primeros se usan en CampoDeportivoSeeder; el resto queda
        // disponible para que el admin cree campos nuevos sin tocar código.
        $tipos = [
            ['nombre' => 'Futsal',    'descripcion' => 'Cancha de fútbol de salón (sintética/techada).'],
            ['nombre' => 'Múltiple',  'descripcion' => 'Uso polideportivo (vóley, básquet, eventos).'],
            ['nombre' => 'Fútbol',    'descripcion' => 'Campo de juego de fútbol (estadio).'],
            ['nombre' => 'Natación',  'descripcion' => 'Piscina / escenario acuático (aforo, no grilla horaria exclusiva).'],
            ['nombre' => 'Vóley',     'descripcion' => 'Cancha de vóley.'],
            ['nombre' => 'Básquet',   'descripcion' => 'Cancha de básquetbol.'],
            ['nombre' => 'Tenis',     'descripcion' => 'Cancha de tenis / raquet.'],
            ['nombre' => 'Frontón',   'descripcion' => 'Cancha de frontón.'],
            ['nombre' => 'Atletismo', 'descripcion' => 'Pista / campo de atletismo.'],
        ];

        foreach ($tipos as $tipo) {
            TipoCampo::updateOrCreate(
                ['nombre' => $tipo['nombre']],
                [
                    'descripcion' => $tipo['descripcion'],
                    'estado'      => 'activo',
                ],
            );
        }
    }
}
