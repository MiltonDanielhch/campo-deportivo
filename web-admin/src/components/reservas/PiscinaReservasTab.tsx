import { useCallback, useEffect, useState } from 'react';
import type { ElementType } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Ban,
  Banknote,
  Check,
  CheckCircle2,
  CircleDot,
  Clock3,
  Copy,
  Download,
  ExternalLink,
  Eye,
  FilePlus2,
  Filter,
  History,
  Loader2,
  MapPin,
  Phone,
  Plus,
  QrCode,
  RefreshCw,
  RotateCcw,
  Search,
  TrendingUp,
  Waves,
  XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import { piscinaService } from '@/services/piscinaService';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type {
  CampoOption,
  DetallePiscinaResponse,
  EntradaAuditoria,
  EstadoReserva,
  FiltrosPiscinaReserva,
  MetaListado,
  PiscinaReservaAdmin,
} from '@/types/reservas';
import DialogCrearReservaPiscinaVentanilla from '@/components/reservas/DialogCrearReservaPiscinaVentanilla';
import { SnapshotEtiquetado } from '@/components/reservas/SnapshotAuditoria';

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

const perfilLabel: Record<string, string> = {
  mayor_12: 'Mayores de 12',
  menor_12: 'Menores de 12',
  institucion: 'Institución',
};

const SIREB_PANEL_URL =
  import.meta.env.VITE_SIREB_PANEL_URL ||
  'https://test.sireb.beni.gob.bo/panel/liquidaciones';

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

function formatoMonto(valor?: number | string | null): string {
  return `Bs ${Number(valor ?? 0).toFixed(2)}`;
}

function formatoFecha(valor?: string | null): string {
  if (!valor) return '—';
  const fecha = new Date(valor);
  if (Number.isNaN(fecha.getTime())) return valor;
  return fecha.toLocaleString('es-BO');
}

/** HH:MM en hora oficial de Bolivia, a partir de un ISO datetime o de "HH:MM:SS". */
function formatoHora(valor?: string | null): string {
  if (!valor) return '--:--';

  // Ya viene como hora pura ("14:30:00" o "14:30")
  if (/^\d{2}:\d{2}/.test(valor) && !valor.includes('T')) {
    return valor.slice(0, 5);
  }

  const fecha = new Date(valor);
  if (Number.isNaN(fecha.getTime())) return '--:--';
  try {
    return fecha.toLocaleTimeString('es-BO', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'America/La_Paz',
    });
  } catch {
    return fecha.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
  }
}

/** Fecha (Y-m-d) a partir de un ISO datetime. */
function fechaCorta(valor?: string | null): string {
  if (!valor) return '—';
  return valor.slice(0, 10);
}

function resumirCampo(reserva: PiscinaReservaAdmin): string {
  return reserva.campo?.nombre ?? 'Piscina';
}

function primeraFechaDetalle(reserva: PiscinaReservaAdmin): string {
  const detalle = reserva.detalles?.[0];
  return detalle?.fecha ? fechaCorta(detalle.fecha) : '—';
}

/**
 * Normaliza nombre a title case (para datos viejos que vienen en mayúsculas/minúsculas).
 * Los datos nuevos ya vienen normalizados del backend.
 */
function normalizarNombre(nombre?: string | null): string {
  if (!nombre) return '—';
  return nombre
    .toLowerCase()
    .split(' ')
    .map((palabra) => palabra.charAt(0).toUpperCase() + palabra.slice(1))
    .join(' ');
}

/**
 * [B95a] Mapper de acciones de auditoría de piscina. Espeja `auditoriaVisual` de cancha
 * pero con las acciones reales que registra PiscinaReservaService:
 *   crear_reserva / cancelar_reserva / rechazada_liquidacion_ventanilla,
 *   más las que aporta el helper SIREB (liquidacion_creada_sireb / confirmar_cobro)
 *   y el job de expiración (expirar_automaticamente).
 */
function auditoriaVisualPiscina(
  accion: string,
): { label: string; Icon: ElementType; className: string } {
  switch (accion) {
    case 'crear_reserva':
      return {
        label: 'Reserva creada',
        Icon: FilePlus2,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };

    case 'liquidacion_creada_sireb':
      return {
        label: 'Liquidación creada en SIREB',
        Icon: QrCode,
        className: 'border-teal-200 bg-teal-50 text-teal-700',
      };

    case 'confirmar_pago':
      return {
        label: 'Cobro confirmado',
        Icon: CheckCircle2,
        className: 'border-green-200 bg-green-50 text-green-700',
      };

    case 'expirar_automaticamente':
      return {
        label: 'Reserva expirada automáticamente',
        Icon: Clock3,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };

    case 'cancelar_reserva':
      return {
        label: 'Reserva cancelada',
        Icon: Ban,
        className: 'border-zinc-200 bg-zinc-50 text-zinc-700',
      };

    case 'rechazada_liquidacion_ventanilla':
      return {
        label: 'Liquidación rechazada en ventanilla',
        Icon: AlertTriangle,
        className: 'border-orange-200 bg-orange-50 text-orange-700',
      };

    default:
      return {
        label: accion.replace(/_/g, ' '),
        Icon: CircleDot,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };
  }
}


export default function PiscinaReservasTab() {
  const [reservas, setReservas] = useState<PiscinaReservaAdmin[]>([]);
  const [meta, setMeta] = useState<MetaListado | null>(null);
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [cargando, setCargando] = useState(true);

  const [crearOpen, setCrearOpen] = useState(false);
  const [exportando, setExportando] = useState(false);

  const [filtroEstado, setFiltroEstado] = useState<string>('todos');
  const [filtroBuscar, setFiltroBuscar] = useState('');
  const [buscarDebounce, setBuscarDebounce] = useState('');
  const [desde, setDesde] = useState('');
  const [hasta, setHasta] = useState('');
  const [campoId, setCampoId] = useState('todos');
  const [paginaActual, setPaginaActual] = useState(1);

  // [B95a] el detalle ahora trae envelope { reserva, auditoria, estado_sireb, puede_anularse }
  const [detalle, setDetalle] = useState<DetallePiscinaResponse | null>(null);
  const [detalleOpen, setDetalleOpen] = useState(false);
  const [cargandoDetalle, setCargandoDetalle] = useState(false);
  const [refrescando, setRefrescando] = useState(false);
  const [copiado, setCopiado] = useState(false);

  const { funcionario } = useAuth();
  const rol = (funcionario as any)?.rol?.nombre as string | undefined;
  const esAdmin = rol === 'admin_parametricas' || rol === 'admin_reservas';

  // Derivados del envelope (se usan en el JSX y en los handlers de abajo).
  const reservaDetalle = detalle?.reserva ?? null;
  const auditoriaDetalle: EntradaAuditoria[] = detalle?.auditoria ?? [];
  const estadoSirebDetalle = detalle?.estado_sireb ?? null;
  const codigoPublicoDetalle = reservaDetalle?.referencia_recaudaciones ?? null;

  const cargarCampos = useCallback(async () => {
    try {
      const data = await solicitudesReservaService.listarCamposPublicos();
      // Filtrar solo campos de piscina (modalidad turno_por_perfil)
      const camposPiscina = data.filter((c: any) => c.modalidad_reserva === 'turno_por_perfil');
      setCampos(camposPiscina);
    } catch {
      setCampos([]);
    }
  }, []);

  const cargarReservas = useCallback(async () => {
    setCargando(true);

    try {
      const params: FiltrosPiscinaReserva = {
        page: paginaActual,
        per_page: 15,
      };

      if (filtroEstado !== 'todos') params.estado = filtroEstado;
      if (buscarDebounce) params.buscar = buscarDebounce;
      if (desde) params.fecha_desde = desde;
      if (hasta) params.fecha_hasta = hasta;
      if (campoId !== 'todos' && campoId) params.campo_id = campoId;

      const response = await piscinaService.listarReservas(params);

      setReservas(response.data ?? []);
      setMeta(response.meta ?? null);
    } catch (error: any) {
      const status = error.response?.status;
      const message =
        error.response?.data?.message ||
        error.response?.data?.error ||
        error.message;

      if (status === 403) {
        toast.error('No tienes permisos para ver reservas de piscina.');
      } else {
        toast.error(`Error al cargar reservas de piscina: ${message}`);
      }

      setReservas([]);
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
    cargarReservas();
  }, [cargarReservas]);

  const abrirDetalle = async (id: string) => {
    setDetalleOpen(true);
    setCargandoDetalle(true);

    try {
      const data = await piscinaService.obtenerReserva(id);
      setDetalle(data);
    } catch (error: any) {
      toast.error(
        'Error al cargar el detalle: ' +
          (error.response?.data?.message || error.message),
      );
      setDetalle(null);
    } finally {
      setCargandoDetalle(false);
    }
  };

  // [B95a] Refresca el estado de la liquidación desde SIREB (solo admin, como cancha).
  const handleRefrescar = async () => {
    if (!reservaDetalle?.id || !esAdmin) return;

    setRefrescando(true);

    try {
      const resultado = await piscinaService.refrescarSireb(reservaDetalle.id);

      setDetalle((prev) =>
        prev
          ? {
              ...prev,
              estado_sireb: resultado.estado_sireb,
              puede_anularse: resultado.puede_anularse,
            }
          : null,
      );

      toast.success('Estado actualizado desde SIREB');
    } catch (error: any) {
      toast.error(
        'Error al refrescar: ' +
          (error.response?.data?.message || error.message),
      );
    } finally {
      setRefrescando(false);
    }
  };

  const handleCopiar = async () => {
    if (!codigoPublicoDetalle) return;

    await navigator.clipboard.writeText(codigoPublicoDetalle);
    setCopiado(true);
    toast.success('Código copiado al portapapeles');

    setTimeout(() => setCopiado(false), 2000);
  };

  const handleExportarCsv = async () => {
    setExportando(true);
    try {
      const filtros: FiltrosPiscinaReserva = {};

      if (filtroEstado !== 'todos') filtros.estado = filtroEstado;
      if (buscarDebounce) filtros.buscar = buscarDebounce;
      if (desde) filtros.fecha_desde = desde;
      if (hasta) filtros.fecha_hasta = hasta;
      if (campoId !== 'todos' && campoId) filtros.campo_id = campoId;

      await piscinaService.exportarCsv(filtros);
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

  // Calcular tasa de conversión (confirmadas / total de intentos de pago)
  const totalIntentos = (stats?.confirmadas ?? 0) + (stats?.expiradas ?? 0);
  const tasaConversion = totalIntentos > 0
    ? ((stats?.confirmadas ?? 0) / totalIntentos) * 100
    : 0;

  const cards = [
    {
      label: 'Total',
      value: stats?.total ?? 0,
      Icon: Waves,
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
      label: 'Expiradas',
      value: stats?.expiradas ?? 0,
      Icon: XCircle,
      accent: 'text-red-600',
    },
    {
      label: 'Tasa conversión',
      value: `${tasaConversion.toFixed(1)}%`,
      Icon: TrendingUp,
      accent: 'text-blue-600',
    },
    {
      label: 'Monto confirmado',
      value: formatoMonto(stats?.monto_confirmado ?? 0),
      Icon: Banknote,
      accent: 'text-teal-600',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Stats cards */}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
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
              placeholder="Buscar código, pagador, CI, teléfono o código de ventanilla"
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
                <span className="flex min-w-0 items-center gap-2 text-left">
                  <MapPin className="size-4 shrink-0 text-muted-foreground" />
                  <span className="truncate">
                    {campos.find((c) => c.id === campoId)?.nombre || 'Piscina'}
                  </span>
                </span>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="todos">Todas las piscinas</SelectItem>
                {campos.map((campo) => (
                  <SelectItem key={campo.id} value={campo.id}>
                    {campo.nombre || campo.id}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          <Button variant="outline" onClick={limpiarFiltros}>
            Limpiar
          </Button>

          <Button variant="outline" onClick={cargarReservas} disabled={cargando}>
            <RotateCcw className="mr-2 size-4" />
            Actualizar
          </Button>

          <Button
            variant="outline"
            onClick={handleExportarCsv}
            disabled={exportando || cargando}
          >
            {exportando ? (
              <Loader2 className="mr-2 size-4 animate-spin" />
            ) : (
              <Download className="mr-2 size-4" />
            )}
            Exportar CSV
          </Button>

          <Button onClick={() => setCrearOpen(true)} className="bg-teal-600 hover:bg-teal-700">
            <Plus className="mr-2 size-4" />
            Nueva Reserva
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
              <TableHead>Campo</TableHead>
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
                  Cargando reservas de piscina...
                </TableCell>
              </TableRow>
            ) : reservas.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                  No se encontraron reservas de piscina con los filtros aplicados.
                </TableCell>
              </TableRow>
            ) : (
              reservas.map((reserva) => (
                <TableRow
                  key={reserva.id}
                  className="cursor-pointer hover:bg-slate-50"
                  onClick={() => abrirDetalle(reserva.id)}
                >
                  <TableCell className="font-mono text-xs">
                    {reserva.codigo_seguimiento}
                  </TableCell>

                  <TableCell>
                    <p className="text-sm font-medium">
                      {normalizarNombre(reserva.nombre_pagador)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {reserva.ci_nit_pagador || 'Sin CI/NIT'}
                    </p>
                  </TableCell>

                  <TableCell className="text-sm">{resumirCampo(reserva)}</TableCell>

                  <TableCell className="text-sm">
                    {primeraFechaDetalle(reserva)}
                  </TableCell>

                  <TableCell>
                    <p className="font-semibold tabular-nums">
                      {formatoMonto(reserva.monto_total)}
                    </p>
                    {reserva.estado === 'confirmada' &&
                      reserva.monto_confirmado !== null && (
                        <p className="text-xs text-green-600">
                          Confirmado: {formatoMonto(reserva.monto_confirmado)}
                        </p>
                      )}
                  </TableCell>

                  <TableCell>
                    <BadgeEstado estado={reserva.estado} />
                  </TableCell>

                  <TableCell className="text-xs text-muted-foreground">
                    {formatoFecha(reserva.created_at)}
                  </TableCell>

                  <TableCell className="text-right">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={(e) => {
                        e.stopPropagation();
                        abrirDetalle(reserva.id);
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

      <Dialog open={detalleOpen} onOpenChange={setDetalleOpen}>
        <DialogContent
            className="max-h-[90vh] w-full overflow-y-auto"
            style={{ maxWidth: '56rem' }}
          >
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              Detalle de reserva de piscina
              {reservaDetalle && <BadgeEstado estado={reservaDetalle.estado} />}
            </DialogTitle>
            <DialogDescription>
              Información del pagador, turnos reservados, historial de auditoría y estado SIREB
            </DialogDescription>
          </DialogHeader>

          {cargandoDetalle ? (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="size-8 animate-spin text-teal-600" />
            </div>
          ) : reservaDetalle ? (
            <div className="space-y-6">
              {/* Pagador y montos */}
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <p className="text-xs text-muted-foreground">Código de seguimiento</p>
                  <p className="font-mono font-semibold">{reservaDetalle.codigo_seguimiento}</p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Campo</p>
                  <p className="text-sm font-medium">{resumirCampo(reservaDetalle)}</p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Pagador</p>
                  <p className="text-sm font-medium">
                    {normalizarNombre(reservaDetalle.nombre_pagador)}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    CI/NIT: {reservaDetalle.ci_nit_pagador || '—'}
                  </p>

                  {reservaDetalle.telefono_pagador && (
                    <a
                      href={`tel:${reservaDetalle.telefono_pagador}`}
                      className="mt-1 inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700"
                    >
                      <Phone className="size-3.5" />
                      {reservaDetalle.telefono_pagador}
                    </a>
                  )}
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Monto total</p>
                  <p className="text-lg font-bold tabular-nums">
                    {formatoMonto(reservaDetalle.monto_total)}
                  </p>

                  {reservaDetalle.monto_confirmado !== null && (
                    <p className="text-xs text-green-600">
                      Confirmado: {formatoMonto(reservaDetalle.monto_confirmado)}
                    </p>
                  )}
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Creado</p>
                  <p className="text-sm">{formatoFecha(reservaDetalle.created_at)}</p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Expira</p>
                  <p className="text-sm">{formatoFecha(reservaDetalle.expira_en)}</p>
                </div>
              </div>

              <div>
                <p className="text-xs text-muted-foreground">Confirmado</p>
                <p className="text-sm">{formatoFecha(reservaDetalle.confirmado_en)}</p>
              </div>

              <Separator />

              {/* [B95a] Auditoría */}
              <div>
                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                  <History className="size-4 text-teal-600" />
                  Historial de auditoría
                </h3>

                {auditoriaDetalle.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Sin historial registrado para esta reserva.
                  </p>
                ) : (
                  <ol className="relative ml-3 space-y-5 border-l border-slate-200 pl-6">
                    {auditoriaDetalle.map((entrada) => {
                      const visual = auditoriaVisualPiscina(entrada.accion);
                      const Icon = visual.Icon;

                      return (
                        <li key={entrada.id} className="relative">
                          <span
                            className={`absolute -left-[34px] flex size-6 items-center justify-center rounded-full border ${visual.className}`}
                          >
                            <Icon className="size-3.5" />
                          </span>

                          <p className="text-sm font-medium">{visual.label}</p>

                          <p className="text-xs text-muted-foreground">
                            {formatoFecha(entrada.fecha)}
                            {entrada.usuario_nombre ? ` · ${entrada.usuario_nombre}` : ''}
                          </p>

                          {Boolean(entrada.antes || entrada.despues) && (
                            <details className="mt-1 text-xs text-muted-foreground">
                              <summary className="cursor-pointer hover:text-foreground">
                                Ver datos registrados
                              </summary>
                              <div className="mt-1 rounded bg-slate-50 p-2">
                                <SnapshotEtiquetado titulo="Antes" datos={entrada.antes} />
                                <SnapshotEtiquetado titulo="Después" datos={entrada.despues} />
                              </div>
                            </details>
                          )}
                        </li>
                      );
                    })}
                  </ol>
                )}
              </div>

              <Separator />

              {/* [B95a] SIREB */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <QrCode className="size-4 text-teal-600" />
                    Integración con SIREB
                  </h3>

                  {esAdmin && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={handleRefrescar}
                      disabled={refrescando || !codigoPublicoDetalle}
                    >
                      {refrescando ? (
                        <Loader2 className="mr-2 size-4 animate-spin" />
                      ) : (
                        <RefreshCw className="mr-2 size-4" />
                      )}
                      Refrescar desde SIREB
                    </Button>
                  )}
                </div>

                {!codigoPublicoDetalle ? (
                  <div className="rounded-lg bg-slate-50 p-4 text-sm text-muted-foreground">
                    Esta reserva no tiene liquidación creada en SIREB.
                  </div>
                ) : (
                  <>
                    <div className="rounded-lg bg-slate-50 p-4">
                      <p className="mb-2 text-xs text-muted-foreground">Código público</p>

                      <div className="flex items-center gap-2">
                        <code className="font-mono text-lg font-bold tracking-widest">
                          {codigoPublicoDetalle}
                        </code>

                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={handleCopiar}
                          className="size-8"
                        >
                          {copiado ? (
                            <Check className="size-4 text-green-600" />
                          ) : (
                            <Copy className="size-4" />
                          )}
                        </Button>
                      </div>
                    </div>

                    <div className="rounded-lg bg-slate-50 p-4">
                      <p className="mb-2 text-xs text-muted-foreground">
                        Estado actual en SIREB
                      </p>

                      {estadoSirebDetalle ? (
                        <div className="space-y-1 text-sm">
                          <p>
                            Estado:{' '}
                            <strong>{String(estadoSirebDetalle.estado ?? '—')}</strong>
                          </p>
                          <p>
                            Pagado:{' '}
                            <strong>{estadoSirebDetalle.pagado ? 'Sí' : 'No'}</strong>
                          </p>

                          {estadoSirebDetalle.monto != null && (
                            <p>
                              Monto:{' '}
                              <strong>
                                {formatoMonto(Number(estadoSirebDetalle.monto))}
                              </strong>
                            </p>
                          )}
                        </div>
                      ) : (
                        <p className="text-sm text-muted-foreground">
                          No se pudo obtener estado de SIREB.
                        </p>
                      )}
                    </div>

                    <a
                      href={`${SIREB_PANEL_URL}/${codigoPublicoDetalle}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm text-teal-600 hover:text-teal-700"
                    >
                      Ver liquidación en panel oficial de SIREB
                      <ExternalLink className="size-3.5" />
                    </a>
                  </>
                )}

                {/* Motivo fuera del branch de código público: una reserva RECHAZADA por
                    ventanilla (rechazada_liquidacion_ventanilla) puede no tener referencia
                    SIREB, y aun así el funcionario debe ver por qué se rechazó. Cancha lo
                    pone dentro; acá lo sacamos a propósito para cubrir ese caso de piscina. */}
                {reservaDetalle.motivo_rechazo && (
                  <div className="rounded-lg border border-orange-200 bg-orange-50 p-3">
                    <p className="mb-1 text-xs font-semibold text-orange-900">
                      Motivo de rechazo
                    </p>
                    <p className="text-sm text-orange-800">
                      {reservaDetalle.motivo_rechazo}
                    </p>
                  </div>
                )}
              </div>

              <Separator />

              {/* Turnos reservados */}
              <div>
                <h3 className="mb-3 text-sm font-semibold">Turnos reservados</h3>

                {!reservaDetalle.detalles || reservaDetalle.detalles.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Esta reserva no tiene turnos registrados.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {reservaDetalle.detalles.map((d) => (
                      <div
                        key={d.id}
                        className="rounded-lg border bg-slate-50 p-3"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="text-sm font-medium">
                              {perfilLabel[d.perfil] ?? d.perfil}
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {fechaCorta(d.fecha)} · {formatoHora(d.hora_inicio)} -{' '}
                              {formatoHora(d.hora_fin)}
                            </p>
                          </div>

                          <div className="text-right">
                            <p className="text-sm font-semibold tabular-nums">
                              {formatoMonto(d.precio_unitario)} c/u
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {d.cantidad} {d.cantidad === 1 ? 'persona' : 'personas'}
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
      <DialogCrearReservaPiscinaVentanilla
        open={crearOpen}
        onOpenChange={setCrearOpen}
        onCreada={cargarReservas}
      />
    </div>
  );
}
