import { useEffect, useState } from 'react';
import type { ElementType } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Ban,
  Check,
  CheckCircle2,
  CircleDot,
  Clock,
  Copy,
  ExternalLink,
  FilePlus2,
  History,
  Loader2,
  Phone,
  QrCode,
  RefreshCw,
  ShieldAlert,
  Undo2,
  UserCheck,
} from 'lucide-react';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useAuth } from '@/context/AuthContext';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import { asistenciaService } from '@/services/asistenciaService';
import type {
  DetalleSolicitudAdmin,
  DetalleSolicitudResponse,
  EntradaAuditoria,
  EstadoSireb,
  SolicitudReservaAdmin,
} from '@/types/reservas';
import AnularLiquidacionDialog from './AnularLiquidacionDialog';

interface DialogDetalleReservaProps {
  solicitudId: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onRefresh?: () => void;
}

const SIREB_PANEL_URL =
  import.meta.env.VITE_SIREB_PANEL_URL ||
  'https://test.sireb.beni.gob.bo/panel/liquidaciones';

const coloresEstado: Record<string, string> = {
  pendiente: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  confirmada: 'bg-green-100 text-green-800 border-green-200',
  expirada: 'bg-slate-100 text-slate-800 border-slate-200',
  cancelada: 'bg-zinc-100 text-zinc-800 border-zinc-200',
  rechazada: 'bg-orange-100 text-orange-800 border-orange-200',
};

function formatoFecha(valor?: string | null): string {
  if (!valor) return '—';

  const fecha = new Date(valor);

  if (Number.isNaN(fecha.getTime())) return valor;

  return fecha.toLocaleString('es-BO');
}

function formatoMonto(valor?: number | null): string {
  return `Bs ${Number(valor ?? 0).toFixed(2)}`;
}

function formatoHoraLocal(valor?: string | null): string {
  if (!valor) return '--:--';

  const fecha = new Date(valor);

  if (Number.isNaN(fecha.getTime())) return valor;

  return fecha.toLocaleTimeString('es-BO', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function normalizarHora(hora: string): string {
  return hora.length >= 5 ? hora.slice(0, 5) : hora;
}

/**
 * Ventana temporal espejo del backend:
 * desde 1 hora antes del inicio hasta 24 horas después del fin.
 *
 * Importante: la UI es orientativa. La validación definitiva siempre
 * la hace MarcarAsistenciaService en backend.
 */
function estaDentroDeVentana(detalle: DetalleSolicitudAdmin): boolean {
  if (!detalle.fecha_reserva || !detalle.hora_inicio || !detalle.hora_fin) {
    return false;
  }

  const inicio = new Date(
    `${detalle.fecha_reserva}T${normalizarHora(detalle.hora_inicio)}:00`,
  );

  const fin = new Date(
    `${detalle.fecha_reserva}T${normalizarHora(detalle.hora_fin)}:00`,
  );

  if (Number.isNaN(inicio.getTime()) || Number.isNaN(fin.getTime())) {
    return false;
  }

  const ventanaInicio = inicio.getTime() - 60 * 60 * 1000;
  const ventanaFin = fin.getTime() + 24 * 60 * 60 * 1000;
  const ahora = Date.now();

  return ahora >= ventanaInicio && ahora <= ventanaFin;
}

function puedeMarcarAsistencia(
  solicitud: SolicitudReservaAdmin,
  detalle: DetalleSolicitudAdmin,
  esAdmin: boolean,
): boolean {
  if (solicitud.estado !== 'confirmada') return false;
  if (!detalle.reserva) return false;

  // Admin puede marcar/desmarcar fuera de ventana.
  if (esAdmin) return true;

  // funcionario_control solo dentro de ventana.
  // Si el backend le devolvió este detalle, ya implica asignación al campo.
  return estaDentroDeVentana(detalle);
}

function mensajeErrorAsistencia(error: any): string {
  const status = error?.response?.status;
  const data = error?.response?.data;

  if (status === 403) {
    return 'No tienes permisos para marcar asistencia en este campo.';
  }

  if (status === 422) {
    const errores = data?.errors?.reserva;

    if (Array.isArray(errores) && errores.length > 0) {
      return String(errores[0]);
    }

    return data?.message || 'No se puede marcar asistencia en este momento.';
  }

  return data?.message || error?.message || 'Error al registrar asistencia.';
}

function auditoriaVisual(
  accion: string,
): { label: string; Icon: ElementType; className: string } {
  switch (accion) {
    case 'crear':
      return {
        label: 'Solicitud creada',
        Icon: FilePlus2,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };

    case 'liquidacion_creada_sireb':
      return {
        label: 'Liquidación creada en SIREB',
        Icon: QrCode,
        className: 'border-teal-200 bg-teal-50 text-teal-700',
      };

    case 'confirmar_cobro':
      return {
        label: 'Cobro confirmado',
        Icon: CheckCircle2,
        className: 'border-green-200 bg-green-50 text-green-700',
      };

    case 'expirar_automaticamente':
      return {
        label: 'Solicitud expirada automáticamente',
        Icon: Clock,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };

    case 'anular_liquidacion_manual':
      return {
        label: 'Liquidación anulada manualmente',
        Icon: Ban,
        className: 'border-red-200 bg-red-50 text-red-700',
      };

    case 'anulacion_intentada_no_anulable':
      return {
        label: 'Anulación intentada no permitida',
        Icon: ShieldAlert,
        className: 'border-orange-200 bg-orange-50 text-orange-700',
      };

    case 'confirmacion_tardia_no_conciliada':
      return {
        label: 'Confirmación tardía no conciliada',
        Icon: AlertTriangle,
        className: 'border-amber-200 bg-amber-50 text-amber-700',
      };

    case 'discrepancia_monto_confirmado':
      return {
        label: 'Discrepancia de monto confirmado',
        Icon: AlertTriangle,
        className: 'border-red-200 bg-red-50 text-red-700',
      };

    case 'marcar_asistencia':
      return {
        label: 'Asistencia marcada',
        Icon: UserCheck,
        className: 'border-emerald-200 bg-emerald-50 text-emerald-700',
      };

    case 'desmarcar_asistencia':
      return {
        label: 'Asistencia desmarcada',
        Icon: Undo2,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };

    default:
      return {
        label: accion.replace(/_/g, ' '),
        Icon: CircleDot,
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };
  }
}

export default function DialogDetalleReserva({
  solicitudId,
  open,
  onOpenChange,
  onRefresh,
}: DialogDetalleReservaProps) {
  const { funcionario } = useAuth();

  const rol = (funcionario as any)?.rol?.nombre as string | undefined;
  const esAdmin =
    rol === 'admin_parametricas' || rol === 'admin_reservas';

  const [data, setData] = useState<DetalleSolicitudResponse | null>(null);
  const [cargando, setCargando] = useState(false);
  const [refrescando, setRefrescando] = useState(false);
  const [copiado, setCopiado] = useState(false);
  const [anularOpen, setAnularOpen] = useState(false);

  const [confirmandoAsistencia, setConfirmandoAsistencia] = useState<{
    reservaId: string;
    marcar: boolean;
    codigoReserva: string;
  } | null>(null);

  const [procesandoAsistencia, setProcesandoAsistencia] = useState(false);

  useEffect(() => {
    if (open && solicitudId) {
      cargarDetalle();
    }
  }, [open, solicitudId]);

  const cargarDetalle = async () => {
    if (!solicitudId) return;

    setCargando(true);

    try {
      const detalle = await solicitudesReservaService.obtener(solicitudId);
      setData(detalle);
    } catch (error: any) {
      toast.error(
        'Error al cargar detalle: ' +
          (error.response?.data?.message || error.message),
      );
    } finally {
      setCargando(false);
    }
  };

  const handleRefrescar = async () => {
    if (!solicitudId || !esAdmin) return;

    setRefrescando(true);

    try {
      const resultado = await solicitudesReservaService.refrescarSireb(
        solicitudId,
      );

      setData((prev) =>
        prev
          ? {
              ...prev,
              estado_sireb: resultado.estado_sireb as EstadoSireb,
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
    if (!data?.solicitud.referencia_recaudaciones) return;

    await navigator.clipboard.writeText(
      data.solicitud.referencia_recaudaciones,
    );

    setCopiado(true);
    toast.success('Código copiado al portapapeles');

    setTimeout(() => setCopiado(false), 2000);
  };

  const handleAnularExito = () => {
    setAnularOpen(false);
    onRefresh?.();
    onOpenChange(false);
  };

  const confirmarAsistencia = async () => {
    if (!confirmandoAsistencia) return;

    setProcesandoAsistencia(true);

    try {
      const resultado = await asistenciaService.marcar(
        confirmandoAsistencia.reservaId,
        confirmandoAsistencia.marcar,
      );

      if (confirmandoAsistencia.marcar) {
        toast.success(
          `Asistencia marcada a las ${formatoHoraLocal(
            resultado.asistencia_marcada_en,
          )}`,
        );
      } else {
        toast.success('Asistencia desmarcada correctamente');
      }

      await cargarDetalle();
      onRefresh?.();
      setConfirmandoAsistencia(null);
    } catch (error: any) {
      toast.error(mensajeErrorAsistencia(error));
    } finally {
      setProcesandoAsistencia(false);
    }
  };

  if (!solicitudId) return null;

  const solicitud = data?.solicitud;
  const auditoria: EntradaAuditoria[] = data?.auditoria ?? [];
  const estadoSireb = data?.estado_sireb;
  const puedeAnularse = data?.puede_anularse ?? false;
  const codigoPublico = solicitud?.referencia_recaudaciones;

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              Detalle de reserva
              {solicitud && (
                <Badge className={coloresEstado[solicitud.estado]}>
                  {solicitud.estado}
                </Badge>
              )}
            </DialogTitle>
            <DialogDescription>
              Información operativa, historial de auditoría y estado SIREB
            </DialogDescription>
          </DialogHeader>

          {cargando ? (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="size-8 animate-spin text-teal-600" />
            </div>
          ) : solicitud ? (
            <div className="space-y-6">
              {/* Pagador y montos */}
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <p className="text-xs text-muted-foreground">
                    Código de seguimiento
                  </p>
                  <p className="font-mono font-semibold">
                    {solicitud.codigo_seguimiento}
                  </p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">
                    Fecha de creación
                  </p>
                  <p className="text-sm">{formatoFecha(solicitud.creado_en)}</p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Pagador</p>
                  <p className="text-sm font-medium">
                    {solicitud.nombre_pagador || '—'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    CI/NIT: {solicitud.ci_nit_pagador || '—'}
                  </p>

                  {solicitud.telefono_pagador && (
                    <a
                      href={`tel:${solicitud.telefono_pagador}`}
                      className="mt-1 inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700"
                    >
                      <Phone className="size-3.5" />
                      {solicitud.telefono_pagador}
                    </a>
                  )}
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Monto total</p>
                  <p className="text-lg font-bold tabular-nums">
                    {formatoMonto(solicitud.monto_total)}
                  </p>

                  {solicitud.monto_confirmado !== null && (
                    <p className="text-xs text-green-600">
                      Confirmado: {formatoMonto(solicitud.monto_confirmado)}
                    </p>
                  )}
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Expira</p>
                  <p className="text-sm">{formatoFecha(solicitud.expira_en)}</p>
                </div>

                <div>
                  <p className="text-xs text-muted-foreground">Confirmado</p>
                  <p className="text-sm">
                    {formatoFecha(solicitud.confirmado_en)}
                  </p>
                </div>
              </div>

              <Separator />

              {/* Auditoría */}
              <div>
                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                  <History className="size-4 text-teal-600" />
                  Historial de auditoría
                </h3>

                {auditoria.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Sin historial registrado para esta solicitud.
                  </p>
                ) : (
                  <ol className="relative ml-3 space-y-5 border-l border-slate-200 pl-6">
                    {auditoria.map((entrada) => {
                      const visual = auditoriaVisual(entrada.accion);
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
                            {entrada.usuario_nombre
                              ? ` · ${entrada.usuario_nombre}`
                              : ''}
                          </p>

                          {(entrada.antes || entrada.despues) && (
                            <details className="mt-1 text-xs text-muted-foreground">
                              <summary className="cursor-pointer hover:text-foreground">
                                Ver datos registrados
                              </summary>
                              <pre className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap break-words rounded bg-slate-50 p-2">
                                {JSON.stringify(
                                  {
                                    antes: entrada.antes,
                                    despues: entrada.despues,
                                  },
                                  null,
                                  2,
                                )}
                              </pre>
                            </details>
                          )}
                        </li>
                      );
                    })}
                  </ol>
                )}
              </div>

              <Separator />

              {/* SIREB */}
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
                      disabled={refrescando || !codigoPublico}
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

                {!codigoPublico ? (
                  <div className="rounded-lg bg-slate-50 p-4 text-sm text-muted-foreground">
                    Esta solicitud no tiene liquidación creada en SIREB.
                  </div>
                ) : (
                  <>
                    <div className="rounded-lg bg-slate-50 p-4">
                      <p className="mb-2 text-xs text-muted-foreground">
                        Código público
                      </p>

                      <div className="flex items-center gap-2">
                        <code className="font-mono text-lg font-bold tracking-widest">
                          {codigoPublico}
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

                      {estadoSireb ? (
                        <div className="space-y-1 text-sm">
                          <p>
                            Estado:{' '}
                            <strong>{String(estadoSireb.estado ?? '—')}</strong>
                          </p>
                          <p>
                            Pagado:{' '}
                            <strong>
                              {estadoSireb.pagado ? 'Sí' : 'No'}
                            </strong>
                          </p>

                          {estadoSireb.monto != null && (
                            <p>
                              Monto:{' '}
                              <strong>
                                {formatoMonto(Number(estadoSireb.monto))}
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
                      href={`${SIREB_PANEL_URL}/${codigoPublico}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm text-teal-600 hover:text-teal-700"
                    >
                      Ver liquidación en panel oficial de SIREB
                      <ExternalLink className="size-3.5" />
                    </a>

                    {solicitud.motivo_rechazo && (
                      <div className="rounded-lg border border-orange-200 bg-orange-50 p-3">
                        <p className="mb-1 text-xs font-semibold text-orange-900">
                          Motivo de rechazo
                        </p>
                        <p className="text-sm text-orange-800">
                          {solicitud.motivo_rechazo}
                        </p>
                      </div>
                    )}

                    {esAdmin &&
                      solicitud.estado === 'pendiente' &&
                      codigoPublico && (
                        <div className="pt-2">
                          {puedeAnularse ? (
                            <Button
                              variant="destructive"
                              onClick={() => setAnularOpen(true)}
                              className="w-full"
                            >
                              <Ban className="mr-2 size-4" />
                              Anular liquidación
                            </Button>
                          ) : (
                            <p className="text-xs text-muted-foreground">
                              No se puede anular: la liquidación ya tiene pago
                              registrado o no está disponible para anulación.
                            </p>
                          )}
                        </div>
                      )}
                  </>
                )}
              </div>

              <Separator />

              {/* Franjas y asistencia */}
              <div>
                <h3 className="mb-3 text-sm font-semibold">
                  Franjas reservadas
                </h3>

                {!solicitud.detalles || solicitud.detalles.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Esta solicitud no tiene detalles registrados.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {solicitud.detalles.map((detalle) => {
                      const reserva = detalle.reserva;
                      const tieneAsistencia = Boolean(
                        reserva?.asistencia_marcada_en,
                      );

                      const puedeMarcar = reserva
                        ? puedeMarcarAsistencia(solicitud, detalle, esAdmin)
                        : false;

                      const puedeDesmarcar =
                        tieneAsistencia && puedeMarcar;

                      const fueraDeVentanaControl =
                        !esAdmin && Boolean(reserva) && !estaDentroDeVentana(detalle);

                      return (
                        <div
                          key={detalle.id}
                          className="rounded-lg border bg-slate-50 p-3"
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="text-sm font-medium">
                                {detalle.campo_nombre || 'Campo'}
                              </p>
                              <p className="text-xs text-muted-foreground">
                                {detalle.fecha_reserva ?? '—'} ·{' '}
                                {detalle.hora_inicio?.substring(0, 5)} -{' '}
                                {detalle.hora_fin?.substring(0, 5)}
                              </p>
                            </div>

                            <p className="text-sm font-semibold tabular-nums">
                              {formatoMonto(detalle.tarifa_aplicada)}
                            </p>
                          </div>

                          {reserva ? (
                            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                              <Badge
                                variant="outline"
                                className="font-mono"
                              >
                                {reserva.codigo_reserva}
                              </Badge>

                              {tieneAsistencia ? (
                                <Badge className="border-emerald-200 bg-emerald-50 text-emerald-700">
                                  <UserCheck className="mr-1 size-3" />
                                  Asistió{' '}
                                  {formatoHoraLocal(
                                    reserva.asistencia_marcada_en,
                                  )}
                                </Badge>
                              ) : (
                                <span className="text-muted-foreground">
                                  Sin asistencia marcada
                                </span>
                              )}

                              {puedeMarcar && !tieneAsistencia && (
                                <Button
                                  size="sm"
                                  variant="outline"
                                  disabled={procesandoAsistencia}
                                  onClick={() =>
                                    setConfirmandoAsistencia({
                                      reservaId: reserva.id,
                                      marcar: true,
                                      codigoReserva: reserva.codigo_reserva,
                                    })
                                  }
                                >
                                  <UserCheck className="mr-1 size-3.5" />
                                  Marcar asistencia
                                </Button>
                              )}

                              {puedeDesmarcar && (
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  disabled={procesandoAsistencia}
                                  onClick={() =>
                                    setConfirmandoAsistencia({
                                      reservaId: reserva.id,
                                      marcar: false,
                                      codigoReserva: reserva.codigo_reserva,
                                    })
                                  }
                                >
                                  <Undo2 className="mr-1 size-3.5" />
                                  Desmarcar
                                </Button>
                              )}

                              {fueraDeVentanaControl && (
                                <span className="text-muted-foreground">
                                  Fuera de ventana
                                </span>
                              )}
                            </div>
                          ) : (
                            <p className="mt-2 text-xs text-muted-foreground">
                              Sin reserva confirmada todavía.
                            </p>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      {solicitudId && esAdmin && (
        <AnularLiquidacionDialog
          solicitudId={solicitudId}
          open={anularOpen}
          onOpenChange={setAnularOpen}
          onSuccess={handleAnularExito}
        />
      )}

      <AlertDialog
        open={Boolean(confirmandoAsistencia)}
        onOpenChange={(open) => {
          if (!open && !procesandoAsistencia) {
            setConfirmandoAsistencia(null);
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              {confirmandoAsistencia?.marcar ? (
                <>
                  <UserCheck className="size-5 text-emerald-600" />
                  Marcar asistencia
                </>
              ) : (
                <>
                  <Undo2 className="size-5 text-slate-600" />
                  Desmarcar asistencia
                </>
              )}
            </AlertDialogTitle>

            <AlertDialogDescription>
              {confirmandoAsistencia?.marcar
                ? `Se registrará la asistencia de la franja ${confirmandoAsistencia.codigoReserva}. Esta acción queda auditada.`
                : `Se desmarcará la asistencia de la franja ${confirmandoAsistencia?.codigoReserva}. Esta acción queda auditada.`}
            </AlertDialogDescription>
          </AlertDialogHeader>

          <AlertDialogFooter>
            <AlertDialogCancel disabled={procesandoAsistencia}>
              Cancelar
            </AlertDialogCancel>

            <Button
              onClick={confirmarAsistencia}
              disabled={procesandoAsistencia}
              variant={confirmandoAsistencia?.marcar ? 'default' : 'destructive'}
            >
              {procesandoAsistencia ? (
                <Loader2 className="mr-2 size-4 animate-spin" />
              ) : confirmandoAsistencia?.marcar ? (
                <UserCheck className="mr-2 size-4" />
              ) : (
                <Undo2 className="mr-2 size-4" />
              )}

              {confirmandoAsistencia?.marcar
                ? 'Confirmar marcado'
                : 'Confirmar desmarcado'}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
