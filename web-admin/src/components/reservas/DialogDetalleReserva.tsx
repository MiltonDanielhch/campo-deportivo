import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
  Copy,
  Check,
  ExternalLink,
  RefreshCw,
  Ban,
  Loader2,
  QrCode,
} from 'lucide-react';
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
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { DetalleSolicitudResponse } from '@/types/reservas';
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
  expirada: 'bg-red-100 text-red-800 border-red-200',
  cancelada: 'bg-slate-100 text-slate-800 border-slate-200',
  rechazada: 'bg-orange-100 text-orange-800 border-orange-200',
};

export default function DialogDetalleReserva({
  solicitudId,
  open,
  onOpenChange,
  onRefresh,
}: DialogDetalleReservaProps) {
  const [data, setData] = useState<DetalleSolicitudResponse | null>(null);
  const [cargando, setCargando] = useState(false);
  const [refrescando, setRefrescando] = useState(false);
  const [copiado, setCopiado] = useState(false);
  const [anularOpen, setAnularOpen] = useState(false);

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
      toast.error('Error al cargar detalle: ' + (error.response?.data?.message || error.message));
    } finally {
      setCargando(false);
    }
  };

  const handleRefrescar = async () => {
    if (!solicitudId) return;
    setRefrescando(true);
    try {
      const resultado = await solicitudesReservaService.refrescarSireb(solicitudId);
      setData((prev) =>
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
      toast.error('Error al refrescar: ' + (error.response?.data?.message || error.message));
    } finally {
      setRefrescando(false);
    }
  };

  const handleCopiar = async () => {
    if (!data?.solicitud.referencia_recaudaciones) return;
    await navigator.clipboard.writeText(data.solicitud.referencia_recaudaciones);
    setCopiado(true);
    toast.success('Código copiado al portapapeles');
    setTimeout(() => setCopiado(false), 2000);
  };

  const handleAnularExito = () => {
    setAnularOpen(false);
    onRefresh?.();
    onOpenChange(false);
  };

  if (!solicitudId) return null;

  const solicitud = data?.solicitud;
  const estadoSireb = data?.estado_sireb;
  const puedeAnularse = data?.puede_anularse ?? false;
  const codigoPublico = solicitud?.referencia_recaudaciones;

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
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
              Información completa de la solicitud y su estado en SIREB
            </DialogDescription>
          </DialogHeader>

          {cargando ? (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="w-8 h-8 animate-spin text-teal-600" />
            </div>
          ) : solicitud ? (
            <div className="space-y-6">
              {/* Info básica */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-muted-foreground">Código de seguimiento</p>
                  <p className="font-mono font-semibold">{solicitud.codigo_seguimiento}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Fecha creación</p>
                  <p className="text-sm">
                    {new Date(solicitud.creado_en).toLocaleString('es-BO')}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Pagador</p>
                  <p className="text-sm">{solicitud.nombre_pagador}</p>
                  <p className="text-xs text-muted-foreground">
                    CI/NIT: {solicitud.ci_nit_pagador || '—'}
                  </p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Monto</p>
                  <p className="text-lg font-bold tabular-nums">
                    Bs {Number(solicitud.monto_total).toFixed(2)}
                  </p>
                  {solicitud.monto_confirmado !== null && (
                    <p className="text-xs text-green-600">
                      Confirmado: Bs {Number(solicitud.monto_confirmado).toFixed(2)}
                    </p>
                  )}
                </div>
              </div>

              <Separator />

              {/* ─── Bloque Integración SIREB ─── */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold flex items-center gap-2">
                    <QrCode className="w-4 h-4 text-teal-600" />
                    Integración con SIREB
                  </h3>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleRefrescar}
                    disabled={refrescando || !codigoPublico}
                  >
                    {refrescando ? (
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    ) : (
                      <RefreshCw className="w-4 h-4 mr-2" />
                    )}
                    Refrescar desde SIREB
                  </Button>
                </div>

                {!codigoPublico ? (
                  <div className="text-sm text-muted-foreground bg-slate-50 p-4 rounded-lg">
                    Esta solicitud no tiene liquidación creada en SIREB.
                  </div>
                ) : (
                  <>
                    {/* Código público */}
                    <div className="bg-slate-50 p-4 rounded-lg">
                      <p className="text-xs text-muted-foreground mb-2">Código público</p>
                      <div className="flex items-center gap-2">
                        <code className="text-lg font-mono font-bold tracking-widest">
                          {codigoPublico}
                        </code>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={handleCopiar}
                          className="h-8 w-8"
                        >
                          {copiado ? (
                            <Check className="w-4 h-4 text-green-600" />
                          ) : (
                            <Copy className="w-4 h-4" />
                          )}
                        </Button>
                      </div>
                    </div>

                    {/* Estado actual en SIREB */}
                    <div className="bg-slate-50 p-4 rounded-lg">
                      <p className="text-xs text-muted-foreground mb-2">
                        Estado actual en SIREB
                      </p>
                      {estadoSireb ? (
                        <div className="space-y-1">
                          <p className="text-sm">
                            Estado: <strong>{estadoSireb.estado}</strong>
                          </p>
                          <p className="text-sm">
                            Pagado: <strong>{estadoSireb.pagado ? 'Sí' : 'No'}</strong>
                          </p>
                          {estadoSireb.monto !== undefined && (
                            <p className="text-sm">
                              Monto: <strong>Bs {Number(estadoSireb.monto).toFixed(2)}</strong>
                            </p>
                          )}
                        </div>
                      ) : (
                        <p className="text-sm text-muted-foreground">
                          No se pudo obtener estado de SIREB
                        </p>
                      )}
                    </div>

                    {/* Link al panel de SIREB */}
                    <a
                      href={`${SIREB_PANEL_URL}/${codigoPublico}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-sm text-teal-600 hover:text-teal-700"
                    >
                      Ver liquidación en panel oficial de SIREB
                      <ExternalLink className="w-3.5 h-3.5" />
                    </a>

                    {/* liquidacion_id (debug) */}
                    {solicitud.liquidacion_id && (
                      <details className="text-xs text-muted-foreground">
                        <summary className="cursor-pointer hover:text-foreground">
                          ID interno de liquidación (debug)
                        </summary>
                        <code className="block mt-1 p-2 bg-slate-100 rounded">
                          {solicitud.liquidacion_id}
                        </code>
                      </details>
                    )}

                    {/* motivo_rechazo si está rechazada */}
                    {solicitud.motivo_rechazo && (
                      <div className="bg-orange-50 border border-orange-200 p-3 rounded-lg">
                        <p className="text-xs text-orange-900 font-semibold mb-1">
                          Motivo de rechazo
                        </p>
                        <p className="text-sm text-orange-800">{solicitud.motivo_rechazo}</p>
                      </div>
                    )}

                    {/* Botón anular */}
                    {solicitud.estado === 'pendiente' && (
                      <div className="pt-2">
                        <Button
                          variant="destructive"
                          onClick={() => setAnularOpen(true)}
                          disabled={!puedeAnularse}
                          className="w-full"
                        >
                          <Ban className="w-4 h-4 mr-2" />
                          {puedeAnularse
                            ? 'Anular liquidación'
                            : 'No se puede anular (pago registrado)'}
                        </Button>
                      </div>
                    )}
                  </>
                )}
              </div>

              <Separator />

              {/* Detalles (franjas) */}
              {solicitud.detalles && solicitud.detalles.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold mb-3">Franjas reservadas</h3>
                  <div className="space-y-2">
                    {solicitud.detalles.map((d) => (
                      <div
                        key={d.id}
                        className="bg-slate-50 p-3 rounded-lg flex items-center justify-between text-sm"
                      >
                        <div>
                          <p className="font-medium">{d.campo?.nombre || 'Campo'}</p>
                          <p className="text-xs text-muted-foreground">
                            {d.fecha_reserva} · {d.hora_inicio.substring(0, 5)} - {d.hora_fin.substring(0, 5)}
                          </p>
                        </div>
                        <p className="font-semibold tabular-nums">
                          Bs {Number(d.tarifa_aplicada).toFixed(2)}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Reservas confirmadas */}
              {solicitud.reservas && solicitud.reservas.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold mb-3">Reservas confirmadas</h3>
                  <div className="space-y-2">
                    {solicitud.reservas.map((r) => (
                      <div
                        key={r.id}
                        className="bg-green-50 p-3 rounded-lg flex items-center justify-between text-sm"
                      >
                        <div>
                          <p className="font-mono font-semibold">{r.codigo_reserva}</p>
                          <p className="text-xs text-muted-foreground">
                            {r.campo?.nombre} · {r.fecha_reserva}
                          </p>
                        </div>
                        <p className="text-xs text-green-700">
                          Confirmada: {new Date(r.confirmado_en).toLocaleDateString('es-BO')}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      {solicitudId && (
        <AnularLiquidacionDialog
          solicitudId={solicitudId}
          open={anularOpen}
          onOpenChange={setAnularOpen}
          onSuccess={handleAnularExito}
        />
      )}
    </>
  );
}
