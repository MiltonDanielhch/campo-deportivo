import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Check,
  ChevronDown,
  Copy,
  Download,
  Loader2,
  MapPin,
  MessageCircle,
  Plus,
  QrCode,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { CampoOption } from '@/types/reservas';
import { QRCodeCanvas } from 'qrcode.react';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreada: () => void;
}

interface Bloque {
  hora_inicio: string;
  hora_fin: string;
  estado: string;
  precio?: number | null;
}

interface SlotSel {
  inicio: string;
  fin: string;
  origen: 'grilla' | 'amedida';
}

interface Resultado {
  codigo_seguimiento: string;
  monto_total: number;
  referencia_recaudaciones: string | null;
  qr_string: string | null;
}

type ApiError = {
  response?: { data?: { message?: string; errors?: { franjas?: string[]; motivo?: string[] } } };
};

const hhmm = (h: string) => h.substring(0, 5);

function sumarHora(hhmmStr: string): string {
  const [h, m] = hhmmStr.split(':').map(Number);
  const total = (h || 0) * 60 + (m || 0) + 60;
  const nh = Math.floor(total / 60) % 24;
  const nm = total % 60;
  return `${String(nh).padStart(2, '0')}:${String(nm).padStart(2, '0')}`;
}

// [FIX B79] espejo de DisponibilidadService::aSegundos, pa' el tramo de a-medida.
function aSegundosHora(h: string): number {
  const [hh, mm, ss] = (h || '').split(':').map(Number);
  return (hh || 0) * 3600 + (mm || 0) * 60 + (ss || 0);
}

function hoyBoliviaISO(): string {
  try {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/La_Paz',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date());
  } catch {
    return new Date().toISOString().substring(0, 10);
  }
}

const MODAL_MAX_WIDTH = '46rem';

export default function RegularizarUsoDialog({ open, onOpenChange, onCreada }: Props) {
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [campoId, setCampoId] = useState('');
  const [campoNombre, setCampoNombre] = useState('');
  const [campoAbierto, setCampoAbierto] = useState(false);
  const [busquedaCampo, setBusquedaCampo] = useState('');
  const campoWrapRef = useRef<HTMLDivElement>(null);
  const campoInputRef = useRef<HTMLInputElement>(null);

  const [fecha, setFecha] = useState('');
  const [bloques, setBloques] = useState<Bloque[]>([]);
  const [cargandoSlots, setCargandoSlots] = useState(false);
  const [slots, setSlots] = useState<SlotSel[]>([]);
  const [horaAmedida, setHoraAmedida] = useState('');
  const [horaInicioNocheGrilla, setHoraInicioNocheGrilla] = useState<string | null>(null);

  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');
  const [motivo, setMotivo] = useState('');

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);

  const corteNocheSeg = useMemo(
    () => aSegundosHora(horaInicioNocheGrilla ?? '18:00:00'),
    [horaInicioNocheGrilla],
  );

  const camposFiltrados = useMemo(
    () =>
      campos.filter((c) =>
        (c.nombre || c.id).toLowerCase().includes(busquedaCampo.toLowerCase().trim()),
      ),
    [campos, busquedaCampo],
  );

  // [B77-B] estimado = suma del precio clasificado de la grilla (B78). Si una franja
  // no tiene precio espejado (null), muestra '—' y el monto final lo confirma SIREB,
  // igual que la venta. Sin selector de variante: el caso B es uso tras caída (legacy).
  const totalEstimado = useMemo(() => {
    if (slots.length === 0) return null;
    let suma = 0;
    let faltaAlguna = false;
    for (const o of slots) {
      const enGrilla = bloques.find((b) => b.hora_inicio === o.inicio);
      if (enGrilla?.precio != null) {
        suma += Number(enGrilla.precio);
        continue;
      }
      // a-medida o bloque sin precio: busca un bloque del mismo tramo como referencia.
      const tipo = aSegundosHora(o.inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
      const ref = bloques.find((b) => {
        const bt = aSegundosHora(b.hora_inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
        return bt === tipo && b.precio != null;
      });
      if (ref?.precio != null) suma += Number(ref.precio);
      else faltaAlguna = true;
    }
    return faltaAlguna ? null : suma;
  }, [slots, bloques, corteNocheSeg]);

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      setCampoId('');
      setCampoNombre('');
      setBusquedaCampo('');
      setCampoAbierto(false);
      setFecha('');
      setBloques([]);
      setSlots([]);
      setHoraAmedida('');
      setHoraInicioNocheGrilla(null);
      setNombre('');
      setCiNit('');
      setTelefono('');
      setMotivo('');
      setResultado(null);
    }, 200);
  };

  useEffect(() => {
    if (!open) return;
    solicitudesReservaService
      .listarCampos({ estado: 'activo' })
      .then(setCampos)
      .catch(() => setCampos([]));
  }, [open]);

  useEffect(() => {
    if (!campoAbierto) return;
    campoInputRef.current?.focus();
    const onPointerDown = (e: MouseEvent) => {
      if (campoWrapRef.current && !campoWrapRef.current.contains(e.target as Node)) {
        setCampoAbierto(false);
      }
    };
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setCampoAbierto(false);
    };
    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [campoAbierto]);

  const cargarSlots = useCallback(async () => {
    if (!campoId || !fecha) return;
    setCargandoSlots(true);
    setSlots([]);
    try {
      const disp = await solicitudesReservaService.disponibilidad(campoId, fecha);
      setHoraInicioNocheGrilla((disp as any).hora_inicio_noche ?? null);
      setBloques(disp.abierto ? disp.bloques : []);
    } catch {
      setBloques([]);
      setHoraInicioNocheGrilla(null);
      toast.error('No se pudo consultar la disponibilidad del campo.');
    } finally {
      setCargandoSlots(false);
    }
  }, [campoId, fecha]);

  useEffect(() => {
    cargarSlots();
  }, [cargarSlots]);

  // [B77-B] gate INVERTIDO vs venta: 'pasada' SÍ es seleccionable (cobramos un uso
  // que ya corrió). 'ocupada'/'bloqueada_temporal' NO (el backend da 409 por EXCLUDE).
  const toggleSlot = (b: Bloque) =>
    setSlots((prev) =>
      prev.some((s) => s.inicio === b.hora_inicio)
        ? prev.filter((s) => s.inicio !== b.hora_inicio)
        : [...prev, { inicio: b.hora_inicio, fin: b.hora_fin, origen: 'grilla' as const }].sort(
            (a, z) => a.inicio.localeCompare(z.inicio),
          ),
    );

  const toggleAmedida = (hora: string) => {
    const inicio = `${hora}:00`;
    setSlots((prev) => {
      if (prev.some((s) => s.inicio === inicio)) {
        return prev.filter((s) => s.inicio !== inicio);
      }
      const fin = `${sumarHora(hora)}:00`;
      return [...prev, { inicio, fin, origen: 'amedida' as const }].sort(
        (a, z) => a.inicio.localeCompare(z.inicio),
      );
    });
  };

  const copiar = async (texto: string, clave: string) => {
    await navigator.clipboard.writeText(texto);
    setCopiado(clave);
    setTimeout(() => setCopiado(null), 1500);
  };

  const descargarQr = () => {
    const canvas = document.getElementById('qr-regularizar') as HTMLCanvasElement | null;
    if (!canvas) return;
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `qr-${resultado?.codigo_seguimiento ?? 'regularizar'}.png`;
    a.click();
  };

  // [KB wa.me] https://wa.me/?text=... abre el chat con el texto prellenado; si el
  // funcionario no tiene WhatsApp, cae al landing "Looks like you don't have WhatsApp
  // installed". Reuso el mismo mecanismo validado en la venta.
  const enviarWhatsapp = () => {
    if (!resultado) return;
    const texto = encodeURIComponent(
      `Regularización ${resultado.codigo_seguimiento}\nMonto: Bs ${resultado.monto_total.toFixed(2)}\n` +
        `Código de pago: ${resultado.referencia_recaudaciones ?? ''}\n` +
        `Pagá con el código en ventanilla de cualquier banco (presentá tu CI/NIT) o escaneá el QR para pagar por Síntesis.`,
    );
    window.open(`https://wa.me/?text=${texto}`, '_blank');
  };

  const puedeEnviar =
    !!campoId &&
    !!fecha &&
    slots.length > 0 &&
    !!nombre.trim() &&
    !!ciNit.trim() &&
    motivo.trim().length >= 10;

  const motivoDeshabilitado = !campoId
    ? 'Elegí el campo.'
    : !fecha
      ? 'Elegí la fecha del uso.'
      : slots.length === 0
        ? 'Elegí al menos una franja (libre o ya pasada).'
        : !nombre.trim()
          ? 'Completá el nombre del pagador.'
          : !ciNit.trim()
            ? 'Completá el CI/NIT del pagador.'
            : motivo.trim().length < 10
              ? 'El motivo debe tener al menos 10 caracteres.'
              : null;

  const enviar = async () => {
    if (!puedeEnviar) return;
    setEnviando(true);
    try {
      const res = await solicitudesReservaService.regularizar({
        franjas: slots.map((o) => ({
          campo_id: campoId,
          fecha,
          hora_inicio: o.inicio,
          hora_fin: o.fin,
        })),
        nombre_pagador: nombre.trim(),
        ci_nit_pagador: ciNit.trim(),
        telefono_pagador: telefono.trim() || undefined,
        motivo: motivo.trim(),
      });
      setResultado(res.data);
      onCreada();
    } catch (error) {
      const apiError = error as ApiError;
      const errs = apiError.response?.data?.errors;
      const mensaje =
        Array.isArray(errs?.motivo)
          ? errs.motivo[0]
          : Array.isArray(errs?.franjas)
            ? errs.franjas[0]
            : apiError.response?.data?.message ?? 'No se pudo regularizar el uso.';
      toast.error(mensaje);
    } finally {
      setEnviando(false);
    }
  };

  const slotsAmedida = slots.filter((s) => s.origen === 'amedida');

  return (
    <Dialog open={open} onOpenChange={(v) => (v ? onOpenChange(v) : cerrar())}>
      <DialogContent
        className="max-h-[92vh] w-full overflow-y-auto"
        style={{ maxWidth: MODAL_MAX_WIDTH }}
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <AlertTriangle className="size-5 text-amber-600" />
            Regularizar uso tras caída
          </DialogTitle>
          <DialogDescription>
            Cobrá un uso que ya ocurrió pero no se pudo pagar en el momento (caída del sistema /
            SIREB sin respuesta). Genera el código de pago igual que la venta. Solo administradores.
          </DialogDescription>
        </DialogHeader>

        {resultado ? (
          <div className="space-y-4 py-1">
            <div className="rounded-xl border border-emerald-300/60 bg-emerald-500/10 p-4 text-center dark:border-emerald-700">
              <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                Uso regularizado
              </p>
              <p className="mt-1 font-mono text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                {resultado.codigo_seguimiento}
              </p>
              <p className="mt-1 text-lg font-bold tabular-nums text-emerald-900 dark:text-emerald-100">
                Bs {resultado.monto_total.toFixed(2)}
              </p>
              <p className="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                Monto emitido por SIREB
              </p>
            </div>

            {resultado.referencia_recaudaciones ? (
              <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Código de pago en ventanilla
                </p>

                {resultado.qr_string && (
                  <div className="flex flex-col items-center gap-3 py-2">
                    <div className="rounded-xl border bg-white p-3">
                      <QRCodeCanvas
                        id="qr-regularizar"
                        value={resultado.qr_string}
                        size={220}
                        level="M"
                        marginSize={2}
                      />
                    </div>
                    <div className="flex flex-wrap justify-center gap-2">
                      <Button variant="outline" size="sm" onClick={descargarQr}>
                        <Download className="mr-2 size-4" />
                        Descargar QR
                      </Button>
                      <Button variant="outline" size="sm" onClick={enviarWhatsapp}>
                        <MessageCircle className="mr-2 size-4" />
                        Enviar por WhatsApp
                      </Button>
                    </div>
                  </div>
                )}

                <div className="flex items-center justify-between gap-2">
                  <code className="min-w-0 truncate font-mono text-xl font-bold tracking-widest">
                    {resultado.referencia_recaudaciones}
                  </code>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    onClick={() => copiar(resultado.referencia_recaudaciones!, 'ref')}
                  >
                    {copiado === 'ref' ? (
                      <Check className="size-4 text-green-600" />
                    ) : (
                      <Copy className="size-4" />
                    )}
                  </Button>
                </div>

                <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">
                  <QrCode className="size-3.5 shrink-0" />
                  Pagá con este código en ventanilla de cualquier banco, presentando tu CI/NIT. O escaneá el QR para pagar por Síntesis.
                </p>
              </div>
            ) : (
              <p className="rounded-xl border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                La regularización quedó pendiente: SIREB no respondió y el reintento corre en
                segundo plano. El código de pago aparece en el detalle en unos minutos.
              </p>
            )}

            <Button className="w-full" onClick={cerrar}>
              Listo
            </Button>
          </div>
        ) : (
          <div className="space-y-5 py-1">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="relative min-w-0 space-y-2" ref={campoWrapRef}>
                <Label>Campo *</Label>
                <Button
                  type="button"
                  variant="outline"
                  role="combobox"
                  aria-expanded={campoAbierto}
                  aria-label="Campo deportivo"
                  className="w-full justify-between font-normal"
                  onClick={() => setCampoAbierto((v) => !v)}
                >
                  <span className="flex min-w-0 items-center gap-2 text-left">
                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                    <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
                      {campoNombre || 'Elegí el campo'}
                    </span>
                  </span>
                  <ChevronDown className="size-4 shrink-0 opacity-50" />
                </Button>

                {campoAbierto && (
                  <div className="absolute left-0 right-0 top-full z-50 mt-1 rounded-md border bg-background p-1 shadow-md">
                    <Input
                      ref={campoInputRef}
                      value={busquedaCampo}
                      onChange={(e) => setBusquedaCampo(e.target.value)}
                      placeholder="Buscar campo…"
                      className="mb-1 h-8 text-sm"
                      aria-label="Buscar campo"
                    />
                    <div className="max-h-56 overflow-y-auto">
                      {camposFiltrados.length === 0 ? (
                        <p className="px-2 py-1.5 text-sm text-muted-foreground">
                          Sin campos que coincidan.
                        </p>
                      ) : (
                        camposFiltrados.map((c) => {
                          const activo = c.id === campoId;
                          return (
                            <button
                              key={c.id}
                              type="button"
                              onClick={() => {
                                setCampoId(c.id);
                                setCampoNombre(c.nombre && c.nombre !== c.id ? c.nombre : '');
                                setBusquedaCampo('');
                                setCampoAbierto(false);
                                setSlots([]);
                              }}
                              className={`flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted ${
                                activo ? 'bg-muted font-medium' : ''
                              }`}
                            >
                              <span className="block truncate" title={c.nombre || c.id}>
                                {c.nombre || c.id}
                              </span>
                              {activo && <Check className="size-4 shrink-0" />}
                            </button>
                          );
                        })
                      )}
                    </div>
                  </div>
                )}
              </div>

              <div className="min-w-0 space-y-2">
                <Label>Fecha del uso *</Label>
                <Input
                  type="date"
                  value={fecha}
                  max={hoyBoliviaISO()}
                  onChange={(e) => setFecha(e.target.value)}
                />
                <p className="text-[11px] leading-snug text-muted-foreground">
                  Puede ser hoy o un día anterior (el uso ya ocurrió).
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <Label>Franjas del uso *</Label>
                {cargandoSlots && <Loader2 className="size-4 animate-spin text-muted-foreground" />}
              </div>

              {!campoId || !fecha ? (
                <p className="text-sm text-muted-foreground">Elegí campo y fecha para ver horarios.</p>
              ) : (
                <>
                  {bloques.length === 0 && !cargandoSlots && (
                    <p className="text-sm text-muted-foreground">Sin franjas ese día.</p>
                  )}

                  {bloques.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {bloques.map((b) => {
                        const activo = slots.some((s) => s.inicio === b.hora_inicio);
                        // [B77-B] 'pasada' habilitada (a diferencia de la venta).
                        const seleccionable = b.estado === 'libre' || b.estado === 'pasada';
                        const etiqueta =
                          b.estado === 'ocupada'
                            ? 'Ocupada'
                            : b.estado === 'bloqueada_temporal'
                              ? 'En cobro'
                              : b.estado === 'pasada'
                                ? 'Pasada'
                                : b.estado;
                        return (
                          <Button
                            key={b.hora_inicio}
                            type="button"
                            variant={activo ? 'default' : seleccionable ? 'outline' : 'ghost'}
                            size="sm"
                            disabled={!seleccionable}
                            title={
                              seleccionable
                                ? b.estado === 'pasada'
                                  ? 'Franja ya corrida — se regulariza igual'
                                  : undefined
                                : `${etiqueta} — no disponible`
                            }
                            className={
                              seleccionable
                                ? b.estado === 'pasada'
                                  ? 'border-amber-300/60 bg-amber-500/10'
                                  : ''
                                : 'pointer-events-none opacity-50 line-through decoration-muted-foreground/40'
                            }
                            onClick={() => toggleSlot(b)}
                          >
                            {hhmm(b.hora_inicio)}–{hhmm(b.hora_fin)}
                            {!seleccionable && (
                              <span className="ml-1.5 text-[10px] font-normal">{etiqueta}</span>
                            )}
                          </Button>
                        );
                      })}
                    </div>
                  )}

                  <div className="space-y-2 rounded-lg border border-dashed bg-muted/30 p-3">
                    <Label className="text-xs font-semibold text-muted-foreground">
                      Otra franja a medida (inicio + 1 hora)
                    </Label>
                    <div className="flex items-center gap-2">
                      <Input
                        type="time"
                        step={1800}
                        value={horaAmedida}
                        onChange={(e) => setHoraAmedida(e.target.value)}
                        className="w-32"
                        disabled={!campoId || !fecha}
                        aria-label="Hora de inicio a medida"
                      />
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!horaAmedida || !campoId || !fecha}
                        onClick={() => {
                          toggleAmedida(horaAmedida);
                          setHoraAmedida('');
                        }}
                      >
                        <Plus className="mr-1 size-4" /> Agregar
                      </Button>
                    </div>

                    {slotsAmedida.length > 0 && (
                      <div className="flex flex-wrap gap-2">
                        {slotsAmedida.map((o) => (
                          <Button
                            key={o.inicio}
                            type="button"
                            variant="default"
                            size="sm"
                            onClick={() => toggleAmedida(o.inicio.slice(0, 5))}
                          >
                            {hhmm(o.inicio)}–{hhmm(o.fin)}
                          </Button>
                        ))}
                      </div>
                    )}
                  </div>
                </>
              )}
            </div>

            <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
              <p className="text-sm font-semibold">Datos del pagador</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Nombre completo *</Label>
                  <Input value={nombre} onChange={(e) => setNombre(e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>CI / NIT *</Label>
                  <Input value={ciNit} onChange={(e) => setCiNit(e.target.value)} />
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Teléfono (opcional)</Label>
                  <Input value={telefono} onChange={(e) => setTelefono(e.target.value)} />
                </div>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>Motivo de la regularización *</Label>
              <textarea
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                rows={3}
                maxLength={500}
                placeholder="Ej.: caída del sistema entre las 19:00 y 20:00, el ciudadano usó la cancha y no pudo pagar en el momento."
                className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Motivo"
              />
              <p className="text-[11px] leading-snug text-muted-foreground">
                Queda auditado (quién, cuándo, por qué). Mínimo 10 caracteres.
              </p>
            </div>

            <div className="rounded-xl border bg-card p-3">
              <div className="flex items-center justify-between gap-3">
                <span className="min-w-0 truncate text-xs text-muted-foreground">
                  Estimado · el monto final lo confirma SIREB
                </span>
                <span className="shrink-0 text-lg font-bold tabular-nums">
                  {totalEstimado === null ? 'Bs —' : `Bs ${totalEstimado.toFixed(2)}`}
                </span>
              </div>
              <p className="mt-1 truncate text-xs text-muted-foreground">
                {slots.length > 0
                  ? `${slots.length} franja${slots.length === 1 ? '' : 's'}`
                  : 'Sin franja'}
              </p>
              {!puedeEnviar && motivoDeshabilitado && (
                <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                  {motivoDeshabilitado}
                </p>
              )}
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={cerrar} disabled={enviando}>
                Cancelar
              </Button>
              <Button onClick={enviar} disabled={!puedeEnviar || enviando}>
                {enviando && <Loader2 className="mr-2 size-4 animate-spin" />}
                Regularizar y generar código de pago
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
