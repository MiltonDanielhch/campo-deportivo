<?php

namespace App\Services\SolicitudesReserva;

use App\DTOs\SolicitudFiltrosDTO;
use App\Enums\EstadoSolicitudReserva;
use App\Models\Auditoria;
use App\Models\Funcionario;
use App\Models\SolicitudReserva;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SolicitudReservaAdminQueryService
{
    public function esAdministrador(Funcionario $funcionario): bool
    {
        return $funcionario->tienePermiso('*')
            || in_array($funcionario->rol?->nombre, ['admin_parametricas', 'admin_reservas'], true);
    }

    public function listarAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): LengthAwarePaginator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        return $this->consultaBaseAdmin($filtros, $funcionario)
            ->paginate(
                $filtros->perPage,
                ['*'],
                'page',
                $filtros->page
            );
    }

    public function calcularStatsAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): array
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario, incluirEstado: false);

        $pendiente = EstadoSolicitudReserva::Pendiente->value;
        $confirmada = EstadoSolicitudReserva::Confirmada->value;
        $expirada = EstadoSolicitudReserva::Expirada->value;
        $cancelada = EstadoSolicitudReserva::Cancelada->value;
        $rechazada = EstadoSolicitudReserva::Rechazada->value;

        $stats = $query->getQuery()
            ->clone()
            ->reorder()
            ->selectRaw("
                count(*) as total,
                count(*) filter (where estado = '{$pendiente}') as pendientes,
                count(*) filter (where estado = '{$confirmada}') as confirmadas,
                count(*) filter (where estado = '{$expirada}') as expiradas,
                count(*) filter (where estado = '{$cancelada}') as canceladas,
                count(*) filter (where estado = '{$rechazada}') as rechazadas,
                coalesce(sum(monto_confirmado) filter (where estado = '{$confirmada}'), 0) as monto_confirmado
            ")
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'pendientes' => (int) ($stats->pendientes ?? 0),
            'confirmadas' => (int) ($stats->confirmadas ?? 0),
            'expiradas' => (int) ($stats->expiradas ?? 0),
            'canceladas' => (int) ($stats->canceladas ?? 0),
            'rechazadas' => (int) ($stats->rechazadas ?? 0),
            'monto_confirmado' => (float) ($stats->monto_confirmado ?? 0),
        ];
    }

    /**
     * Genera filas CSV aplanadas: una fila por ítem.
     *
     * P8.3E: ahora incluye tanto franjas horarias como servicios adicionales.
     *
     * Usa lazy/chunk para no cargar todo el dataset en memoria.
     *
     * @return Generator<int, array<int, string>>
     */
    public function exportarCsv(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): Generator
    {
        $this->forzarFiltrosParaFuncionarioControl($filtros, $funcionario);

        $query = $this->consultaBaseAdmin($filtros, $funcionario);

        yield [
            'codigo_seguimiento',
            'estado',
            'nombre_pagador',
            'telefono_pagador',
            'ci_nit_pagador',
            'codigo_reserva',
            'campo_nombre',
            'fecha_reserva',
            'hora_inicio',
            'hora_fin',
            'monto_pagado',
            'asistencia_marcada_en',
            'tipo_item',
            'variante_nombre',
            'servicio_sireb_codigo',
            'etiqueta_sireb',
            'cantidad',
            'precio_unitario',
            'subtotal',
        ];

        foreach ($query->lazy(500) as $solicitud) {
            foreach ($solicitud->detalles as $detalle) {
                $reserva = $detalle->reserva;

                $montoPagado = '';

                if ($reserva && $reserva->monto_pagado !== null) {
                    $montoPagado = number_format((float) $reserva->monto_pagado, 2, '.', '');
                }

                $cantidad = (int) ($detalle->cantidad ?? 1);
                $precioUnitario = (float) $detalle->tarifa_aplicada;
                $subtotal = round($precioUnitario * $cantidad, 2);

                yield [
                    (string) $solicitud->codigo_seguimiento,
                    $solicitud->estado->value,
                    (string) $solicitud->nombre_pagador,
                    (string) $solicitud->telefono_pagador,
                    (string) $solicitud->ci_nit_pagador,
                    (string) ($reserva?->codigo_reserva ?? ''),
                    (string) ($detalle->campo?->nombre ?? ''),
                    (string) ($detalle->fecha_reserva?->format('Y-m-d') ?? ''),
                    (string) substr((string) $detalle->hora_inicio, 0, 5),
                    (string) substr((string) $detalle->hora_fin, 0, 5),
                    $montoPagado,
                    (string) ($reserva?->asistencia_marcada_en?->format('Y-m-d H:i:s') ?? ''),
                    'franja',
                    (string) ($detalle->variante?->nombre ?? ''),
                    (string) ($detalle->variante?->servicio_sireb_codigo ?? $detalle->campo?->servicio_sireb_codigo ?? ''),
                    (string) ($detalle->etiqueta_sireb ?? ''),
                    (string) $cantidad,
                    number_format($precioUnitario, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                ];
            }

            foreach ($solicitud->servicios as $servicio) {
                $cantidad = (int) ($servicio->cantidad ?? 1);
                $precioUnitario = (float) $servicio->precio_unitario;
                $subtotal = (float) $servicio->subtotal;

                yield [
                    (string) $solicitud->codigo_seguimiento,
                    $solicitud->estado->value,
                    (string) $solicitud->nombre_pagador,
                    (string) $solicitud->telefono_pagador,
                    (string) $solicitud->ci_nit_pagador,
                    '',
                    (string) ($servicio->campo?->nombre ?? ''),
                    '',
                    '',
                    '',
                    '',
                    '',
                    'servicio',
                    (string) ($servicio->variante?->nombre ?? ''),
                    (string) ($servicio->variante?->servicio_sireb_codigo ?? ''),
                    (string) ($servicio->etiqueta_sireb ?? ''),
                    (string) $cantidad,
                    number_format($precioUnitario, 2, '.', ''),
                    number_format($subtotal, 2, '.', ''),
                ];
            }
        }
    }

    /**
     * @return array{solicitud: SolicitudReserva, auditoria: Collection<int, Auditoria>}
     */
    public function obtenerAdmin(string $id, Funcionario $funcionario): array
    {
        $esAdmin = $this->esAdministrador($funcionario);

        $solicitud = SolicitudReserva::with([
            'detalles',
            'detalles.campo',
            'detalles.variante',
            'detalles.reserva',
            'servicios',
            'servicios.campo',
            'servicios.variante',
        ])->find($id);

        if (! $solicitud) {
            abort(404);
        }

        if (! $esAdmin) {
            $tieneAcceso = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->exists();

            if (! $tieneAcceso) {
                abort(403);
            }

            $detallesVisibles = $solicitud->detalles()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->with(['campo', 'variante', 'reserva'])
                ->get();

            $solicitud->setRelation('detalles', $detallesVisibles);

            // P8.3D: funcionario_control solo ve servicios de campos asignados.
            $serviciosVisibles = $solicitud->servicios()
                ->whereHas('campo.asignacionesFuncionario', function ($q) use ($funcionario) {
                    $q->where('funcionario_id', $funcionario->id);
                })
                ->with(['campo', 'variante'])
                ->get();

            $solicitud->setRelation('servicios', $serviciosVisibles);
        }

        $auditoria = Auditoria::where('tabla', 'solicitudes_reserva')
            ->where('registro_id', $solicitud->id)
            ->with('usuario')
            ->orderBy('fecha')
            ->get();

        return [
            'solicitud' => $solicitud,
            'auditoria' => $auditoria,
        ];
    }

    private function forzarFiltrosParaFuncionarioControl(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario
    ): void {
        if (! $this->esAdministrador($funcionario)) {
            $filtros->funcionarioControlId = $funcionario->id;
        }
    }

    private function consultaBaseAdmin(
        SolicitudFiltrosDTO $filtros,
        Funcionario $funcionario,
        bool $incluirEstado = true
    ): Builder {
        $esAdmin = $this->esAdministrador($funcionario);

        $query = SolicitudReserva::query();

        if ($esAdmin) {
            $query->with([
                'detalles',
                'detalles.campo',
                'detalles.variante',
                'detalles.reserva',
                'servicios',
                'servicios.campo',
                'servicios.variante',
            ]);
        } else {
            $query->with([
                'detalles' => function ($q) use ($funcionario) {
                    $q->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                        $aq->where('funcionario_id', $funcionario->id);
                    });
                },
                'detalles.campo',
                'detalles.variante',
                'detalles.reserva',
                'servicios' => function ($q) use ($funcionario) {
                    $q->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                        $aq->where('funcionario_id', $funcionario->id);
                    });
                },
                'servicios.campo',
                'servicios.variante',
            ]);
        }

        if ($incluirEstado && $filtros->estado) {
            $query->where('estado', $filtros->estado);
        }

        if ($filtros->desde) {
            $query->where('creado_en', '>=', $filtros->desde->startOfDay());
        }

        if ($filtros->hasta) {
            $query->where('creado_en', '<=', $filtros->hasta->endOfDay());
        }

        if ($filtros->campoId) {
            if ($esAdmin) {
                $query->whereHas('detalles', function ($q) use ($filtros) {
                    $q->where('campo_id', $filtros->campoId);
                });
            } else {
                $query->whereHas('detalles', function ($q) use ($filtros, $funcionario) {
                    $q->where('campo_id', $filtros->campoId)
                        ->whereHas('campo.asignacionesFuncionario', function ($aq) use ($funcionario) {
                            $aq->where('funcionario_id', $funcionario->id);
                        });
                });
            }
        }

        if ($filtros->funcionarioControlId) {
            $query->whereHas('detalles.campo.asignacionesFuncionario', function ($q) use ($filtros) {
                $q->where('funcionario_id', $filtros->funcionarioControlId);
            });
        }

        if ($filtros->buscar) {
            $termino = '%'.$this->escaparPatronLike($filtros->buscar).'%';

            $query->where(function ($q) use ($termino) {
                $q->whereRaw('codigo_seguimiento ILIKE ?', [$termino])
                    ->orWhereRaw('nombre_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('telefono_pagador ILIKE ?', [$termino])
                    ->orWhereRaw('ci_nit_pagador ILIKE ?', [$termino]);
            });
        }

        return $query
            ->orderByDesc('creado_en')
            ->orderByDesc('id');
    }

    private function escaparPatronLike(string $valor): string
    {
        return addcslashes($valor, '\\%_');
    }
}
