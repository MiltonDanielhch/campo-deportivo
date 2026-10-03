import { useCallback, useEffect, useState } from 'react';
import type { ElementType } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Ban,
  Banknote,
  CheckCircle2,
  CircleDot,
  ClipboardList,
  Clock3,
  Download,
  Eye,
  Filter,
  Loader2,
  MapPin,
  MoreHorizontal,
  RotateCcw,
  Search,
  UserCheck,
  XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useAuth } from '@/context/AuthContext';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type {
  CampoOption,
  DetalleSolicitudAdmin,
  EstadoReserva,
  FiltrosReserva,
  MetaListado,
  SolicitudReservaAdmin,
} from '@/types/reservas';
import DialogDetalleReserva from '@/components/reservas/DialogDetalleReserva';

const estadoConfig: Record<
  EstadoReserva,
  { label: string; className: string; Icon: ElementType }
> = {
  pendiente: {
    label: 'Pendiente',
    className: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    Icon: Clock3,
  },
  confirmada: {
    label: 'Confirmada',
    className: 'bg-green-100 text-green-800 border-green-200',
    Icon: CheckCircle2,
  },
  expirada: {
    label: 'Expirada',
    className: 'bg-slate-100 text-slate-800 border-slate-200',
    Icon: XCircle,
  },
  cancelada: {
    label: 'Cancelada',
    className: 'bg-zinc-100 text-zinc-800 border-zinc-200',
    Icon: Ban,
  },
  rechazada: {
    label: 'Rechazada',
    className: 'bg-orange-100 text-orange-800 border-orange-200',
    Icon: AlertTriangle,
  },
};

function BadgeEstado({ estado }: { estado: EstadoReserva }) {
  const config =
    estadoConfig[estado] ?? {
      label: estado,
      className: 'bg-slate-100 text-slate-800 border-slate-200',
      Icon: CircleDot,
    };

  const Icon = config.Icon;

  return (
    <Badge className={config.className}>
      <Icon className="mr-1 size-3" />
      {config.label}
    </Badge>
  );
}

function formatoMonto(valor?: number | null): string {
  return `Bs ${Number(valor ?? 0).toFixed(2)}`;
}

function formatoFecha(valor?: string | null): string {
  if (!valor) return '—';

  const fecha = new Date(valor);

  if (Number.isNaN(fecha.getTime())) return valor;

  return fecha.toLocaleString('es-BO');
}

function resumirCampos(detalles?: DetalleSolicitudAdmin[]): string {
  if (!detalles || detalles.length === 0) return '—';

  const nombres = detalles
    .map((detalle) => detalle.campo_nombre)
    .filter((nombre): nombre is string => Boolean(nombre));

  if (nombres.length === 0) return '—';

  const unicos = Array.from(new Set(nombres));

  if (unicos.length === 1) return unicos[0];

  return `${unicos[0]} +${unicos.length - 1}`;
}

function primeraFecha(detalles?: DetalleSolicitudAdmin[]): string {
  return detalles?.find((detalle) => detalle.fecha_reserva)?.fecha_reserva ?? '—';
}

export default function Reservas() {
  const { funcionario } = useAuth();

  const rol = (funcionario as any)?.rol?.nombre as string | undefined;
  const esAdminParametricas = rol === 'admin_parametricas';
  const esAdmin =
  rol === 'admin_parametricas' || rol === 'admin_reservas';

  const [solicitudes, setSolicitudes] = useState<SolicitudReservaAdmin[]>([]);
  const [meta, setMeta] = useState<MetaListado | null>(null);
  const [campos, setCampos] = useState<CampoOption[]>([]);

  const [cargando, setCargando] = useState(true);

  const [filtroEstado, setFiltroEstado] = useState<string>('todos');
  const [filtroBuscar, setFiltroBuscar] = useState('');
  const [buscarDebounce, setBuscarDebounce] = useState('');
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const [campoId, setCampoId] = useState('todos');

  const [paginaActual, setPaginaActual] = useState(1);

  const [detalleId, setDetalleId] = useState<string | null>(null);
  const [detalleOpen, setDetalleOpen] = useState(false);

  const [exportando, setExportando] = useState(false);

  const cargarCampos = useCallback(async () => {
    if (!esAdminParametricas) {
      setCampos([]);
      return;
    }

    try {
      const data = await solicitudesReservaService.listarCampos();
      setCampos(data);
    } catch {
      setCampos([]);
    }
  }, [esAdminParametricas]);

  const cargarSolicitudes = useCallback(async () => {
    setCargando(true);

    try {
      const params: FiltrosReserva = {
        page: paginaActual,
        per_page: 15,
      };

      if (filtroEstado !== 'todos') {
        params.estado = filtroEstado;
      }

      if (buscarDebounce) {
        params.buscar = buscarDebounce;
      }

      if (desde) {
        params.desde = desde;
      }

      if (hasta) {
        params.hasta = hasta;
      }

      if (campoId !== 'todos' && campoId) {
        params.campo_id = campoId;
      }

      const response = await solicitudesReservaService.listar(params);

      setSolicitudes(response.data ?? []);
      setMeta(response.meta ?? null);
    } catch (error: any) {
      const status = error.response?.status;
      const message =
        error.response?.data?.message ||
        error.response?.data?.error ||
        error.message;

      if (status === 403) {
        toast.error('No tienes permisos para ver reservas.');
      } else {
        toast.error(`Error al cargar solicitudes: ${message}`);
      }

      setSolicitudes([]);
      setMeta(null);
    } finally {
      setCargando(false);
    }
  }, [paginaActual, filtroEstado, buscarDebounce, desde, hasta, campoId]);

  useEffect(() => {
    cargarCampos();
  }, [cargarCampos]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setBuscarDebounce(filtroBuscar.trim());
      setPaginaActual(1);
    }, 350);

    return () => clearTimeout(timer);
  }, [filtroBuscar]);

  useEffect(() => {
    cargarSolicitudes();
  }, [cargarSolicitudes]);

  const abrirDetalle = (id: string) => {
    setDetalleId(id);
    setDetalleOpen(true);
  };

  const handleExportarCsv = async () => {
    if (!esAdmin) {
      toast.error('Solo administradores pueden exportar el CSV.');
      return;
    }

    setExportando(true);

    try {
      const filtros: FiltrosReserva = {};

      if (filtroEstado !== 'todos') {
        filtros.estado = filtroEstado;
      }

      if (buscarDebounce) {
        filtros.buscar = buscarDebounce;
      }

      if (desde) {
        filtros.desde = desde;
      }

      if (hasta) {
        filtros.hasta = hasta;
      }

      if (campoId !== 'todos' && campoId) {
        filtros.campo_id = campoId;
      }

      await solicitudesReservaService.exportarCsv(filtros);

      toast.success('Exportación CSV iniciada.');
    } catch (error: any) {
      toast.error(error.message || 'Error al exportar el CSV.');
    } finally {
      setExportando(false);
    }
  };

  const limpiarFiltros = () => {
    setFiltroBuscar('');
    setBuscarDebounce('');
    setFiltroEstado('todos');
    setDesde('');
    setHasta('');
    setCampoId('todos');
    setPaginaActual(1);
  };

  const stats = meta?.stats;

  const cards = [
    {
      label: 'Total',
      value: stats?.total ?? 0,
      Icon: ClipboardList,
      accent: 'text-slate-600',
    },
    {
      label: 'Pendientes',
      value: stats?.pendientes ?? 0,
      Icon: Clock3,
      accent: 'text-amber-600',
    },
    {
      label: 'Confirmadas',
      value: stats?.confirmadas ?? 0,
      Icon: CheckCircle2,
      accent: 'text-emerald-600',
    },
    {
      label: 'Monto confirmado',
      value: formatoMonto(stats?.monto_confirmado ?? 0),
      Icon: Banknote,
      accent: 'text-teal-600',
    },
  ];

  return (
    <div className="space-y-6 p-6">
      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Gestión operativa
          </p>
          <h1 className="text-2xl font-bold tracking-tight">Reservas</h1>
          <p className="text-sm text-muted-foreground">
            Auditoría de todas las solicitudes del sistema
          </p>
        </div>

        <div className="flex gap-2">
          <Button variant="outline" onClick={cargarSolicitudes} disabled={cargando}>
            <RotateCcw className="mr-2 size-4" />
            Actualizar
          </Button>

          {esAdmin ? (
            <Button
              variant="outline"
              onClick={handleExportarCsv}
              disabled={exportando || cargando}
              title="Exportar reservas con los filtros actuales"
            >
              {exportando ? (
                <Loader2 className="mr-2 size-4 animate-spin" />
              ) : (
                <Download className="mr-2 size-4" />
              )}
              Exportar CSV
            </Button>
          ) : (
            <Button
              variant="outline"
              disabled
              title="Solo administradores pueden exportar CSV"
            >
              <Download className="mr-2 size-4" />
              Exportar CSV
            </Button>
          )}

        </div>
      </div>

      {/* Stats cards */}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.Icon;

          return (
            <Card key={card.label}>
              <CardContent className="p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs font-medium text-muted-foreground">
                      {card.label}
                    </p>
                    <p className="mt-1 text-xl font-bold tabular-nums">
                      {card.value}
                    </p>
                  </div>
                  <Icon className={`size-8 ${card.accent}`} />
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Toolbar */}
      <div className="rounded-lg border bg-white p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
          <div className="relative min-w-[240px] flex-1">
            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={filtroBuscar}
              onChange={(e) => setFiltroBuscar(e.target.value)}
              placeholder="Buscar código, pagador, CI o teléfono"
              className="pl-9"
            />
          </div>

          <Select
            value={filtroEstado}
            onValueChange={(value) => {
              setFiltroEstado(value);
              setPaginaActual(1);
            }}
          >
            <SelectTrigger className="w-full lg:w-48">
              <Filter className="mr-2 size-4" />
              <SelectValue placeholder="Estado" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos los estados</SelectItem>
              <SelectItem value="pendiente">Pendiente</SelectItem>
              <SelectItem value="confirmada">Confirmada</SelectItem>
              <SelectItem value="expirada">Expirada</SelectItem>
              <SelectItem value="cancelada">Cancelada</SelectItem>
              <SelectItem value="rechazada">Rechazada</SelectItem>
            </SelectContent>
          </Select>

          <Input
            type="date"
            value={desde}
            onChange={(e) => {
              setDesde(e.target.value);
              setPaginaActual(1);
            }}
            className="w-full lg:w-44"
            title="Desde"
          />

          <Input
            type="date"
            value={hasta}
            onChange={(e) => {
              setHasta(e.target.value);
              setPaginaActual(1);
            }}
            className="w-full lg:w-44"
            title="Hasta"
          />

          {campos.length > 0 && (
            <Select
              value={campoId}
              onValueChange={(value) => {
                setCampoId(value);
                setPaginaActual(1);
              }}
            >
              <SelectTrigger className="w-full lg:w-56">
                <MapPin className="mr-2 size-4" />
                <SelectValue placeholder="Campo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="todos">Todos los campos</SelectItem>
                {campos.map((campo) => (
                  <SelectItem key={campo.id} value={campo.id}>
                    {campo.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          <Button variant="outline" onClick={limpiarFiltros}>
            Limpiar
          </Button>
        </div>
      </div>

      {/* Tabla */}
      <div className="overflow-hidden rounded-lg border bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Código</TableHead>
              <TableHead>Pagador</TableHead>
              <TableHead>Campo(s)</TableHead>
              <TableHead>Fecha</TableHead>
              <TableHead>Monto</TableHead>
              <TableHead>Estado</TableHead>
              <TableHead>Creado</TableHead>
              <TableHead className="text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                  Cargando solicitudes...
                </TableCell>
              </TableRow>
            ) : solicitudes.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                  No se encontraron solicitudes con los filtros aplicados.
                </TableCell>
              </TableRow>
            ) : (
              solicitudes.map((solicitud) => (
                <TableRow
                  key={solicitud.id}
                  className="cursor-pointer hover:bg-slate-50"
                  onClick={() => abrirDetalle(solicitud.id)}
                >
                  <TableCell className="font-mono text-xs">
                    {solicitud.codigo_seguimiento}
                  </TableCell>

                  <TableCell>
                    <p className="text-sm font-medium">
                      {solicitud.nombre_pagador || '—'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {solicitud.ci_nit_pagador || 'Sin CI/NIT'}
                    </p>
                  </TableCell>

                  <TableCell className="text-sm">
                    <div className="flex items-center gap-2">
                      <span>{resumirCampos(solicitud.detalles)}</span>

                      {solicitud.detalles?.some((detalle) => Boolean(detalle.reserva?.asistencia_marcada_en)) && (
                        <Badge
                          variant="outline"
                          className="border-emerald-200 bg-emerald-50 text-emerald-700"
                        >
                          <UserCheck className="mr-1 size-3" />
                          Asistió
                        </Badge>
                      )}
                    </div>
                  </TableCell>

                  <TableCell className="text-sm">
                    {primeraFecha(solicitud.detalles)}
                  </TableCell>

                  <TableCell>
                    <p className="font-semibold tabular-nums">
                      {formatoMonto(solicitud.monto_total)}
                    </p>
                    {solicitud.estado === 'confirmada' &&
                      solicitud.monto_confirmado !== null && (
                        <p className="text-xs text-green-600">
                          Confirmado: {formatoMonto(solicitud.monto_confirmado)}
                        </p>
                      )}
                  </TableCell>

                  <TableCell>
                    <BadgeEstado estado={solicitud.estado} />
                  </TableCell>

                  <TableCell className="text-xs text-muted-foreground">
                    {formatoFecha(solicitud.creado_en)}
                  </TableCell>

                  <TableCell className="text-right">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={(e) => {
                        e.stopPropagation();
                        abrirDetalle(solicitud.id);
                      }}
                    >
                      <Eye className="size-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t p-4">
            <p className="text-sm text-muted-foreground">
              Página {meta.current_page} de {meta.last_page} · {meta.total} resultados
            </p>

            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={paginaActual === 1 || cargando}
                onClick={() => setPaginaActual((p) => Math.max(1, p - 1))}
              >
                Anterior
              </Button>

              <Button
                variant="outline"
                size="sm"
                disabled={paginaActual >= meta.last_page || cargando}
                onClick={() => setPaginaActual((p) => p + 1)}
              >
                Siguiente
              </Button>
            </div>
          </div>
        )}
      </div>

      <DialogDetalleReserva
        solicitudId={detalleId}
        open={detalleOpen}
        onOpenChange={setDetalleOpen}
        onRefresh={cargarSolicitudes}
      />
    </div>
  );
}
