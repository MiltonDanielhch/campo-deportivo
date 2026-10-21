<?php

/*
|--------------------------------------------------------------------------
| Catálogo geográfico oficial del departamento del Beni
|--------------------------------------------------------------------------
| ⟦GATE⟧ Las PROVINCIAS abajo son un BORRADOR de memoria del asistente, NO
| verificadas contra SERECI/INE/GAD. El PO DEBE validarlas y corregirlas
| aquí antes de producción. El alta de campo valida contra esta lista:
| si una provincia real falta, el alta la rechaza (falla ruidoso, no
| silencioso) hasta que se agregue abajo.
|
| Los MUNICIPIOS solo tienen Trinidad cargado (verificable hoy: los 3
| campos públicos están en Trinidad según el header "Trinidad, Beni").
| El PO agrega el resto aquí; el código NO hardcodea municipios.
*/

return [
    'provincias' => [
        'Mamoré',
        'Yacuma',
        'Ballivián',
        'José Ballivián',
        'Vaca',
        'Marbán',
        'Moxos',
        'Iténez',
    ],

    'municipios_por_provincia' => [
        'Mamoré' => ['Trinidad'],
        // 'Yacuma' => ['Santa Ana del Yacuma', ...],  // ⟦GATE⟧ completar PO
        // 'Vaca'   => ['Riberalta', ...],
        // 'Ballivián' => ['San Buenaventura', ...],
        // 'José Ballivián' => ['Reyes', ...],
        // 'Marbán' => ['Loreto', ...],
        // 'Moxos'  => ['San Ignacio de Moxos', ...],
        // 'Iténez' => ['Magdalena', ...],
    ],
];
