import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  Check,
  ChevronDown,
  Copy,
  Download,
  Loader2,
  MapPin,
  MessageCircle,
  Minus,
  Plus,
  QrCode,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
} from '@/components/ui/select';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import {
  type VarianteArancelaria,
  type VarianteArancelariaTarifa,
} from '@/services/catalogoSirebService';
import { QRCodeCanvas } from 'qrcode.react';
import { useVentanillaAsociacion } from './ventanilla/useVentanillaAsociacion';
import { useVentanillaCampos } from './ventanilla/useVentanillaCampos';
import { useVentanillaVariantes } from './ventanilla/useVentanillaVariantes';
import { useVentanillaServicios } from './ventanilla/useVentanillaServicios';
import { useVentanillaSlots } from './ventanilla/useVentanillaSlots';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreada: () => void;
}

interface Resultado {
  codigo_seguimiento: string;
  monto_total: number;
  referencia_recaudaciones: string | null;
  qr_string: string | null;
}

type ApiError = {
  response?: {
    data?: {
      message?: string;
      errors?: {
        franjas?: string[];
        servicios?: string[];
      };
    };
  };
};

const hhmm = (h: string) => h.substring(0, 5);

// [FIX B79] parsea "HH:MM[:SS]" a segundos, espejo de DisponibilidadService::aSegundos.
function aSegundosHora(h: string): number {
  const [hh, mm, ss] = (h || '').split(':').map(Number);
  return (hh || 0) * 3600 + (mm || 0) * 60 + (ss || 0);
}

function resolverTarifaServicio(
  v: VarianteArancelaria,
  clasif: string | null,
): VarianteArancelariaTarifa | null {
  const t = v.tarifas;
  const unica = t.find((x) => x.clasificacion === 'unica');
  if (unica) return unica;
  if (t.length === 1) return t[0];
  if (clasif) {
    const por = t.find((x) => x.clasificacion === clasif);
    if (por) return por;
  }
  return null;
}

const MODAL_MAX_WIDTH = '52rem';

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

function StepperCantidad({
  valor,
  onChange,
  ariaLabel,
}: {
  valor: number;
  onChange: (v: number) => void;
  ariaLabel: string;
}) {
  return (
    <div className="flex shrink-0 items-center gap-1 rounded-lg border bg-background p-0.5">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-7"
        disabled={valor <= 1}
        onClick={() => onChange(valor - 1)}
        aria-label={`Restar ${ariaLabel}`}
      >
        <Minus className="size-3.5" />
      </Button>
      <Input
        type="number"
        min={1}
        max={100}
        value={valor}
        onChange={(e) => onChange(Number(e.target.value))}
        className="h-7 w-12 border-0 bg-transparent px-0 text-center text-sm tabular-nums focus-visible:ring-0"
        aria-label={ariaLabel}
      />
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-7"
        disabled={valor >= 100}
        onClick={() => onChange(valor + 1)}
        aria-label={`Sumar ${ariaLabel}`}
      >
        <Plus className="size-3.5" />
      </Button>
    </div>
  );
}

export default function DialogCrearReservaVentanilla({
  open,
  onOpenChange,
  onCreada,
}: Props) {
  // ── Hooks de carga (PR #61a) ──
  const {
    campoId,
    campoNombre,
    busquedaCampo,
    setBusquedaCampo,
    campoAbierto,
    setCampoAbierto,
    campoWrapRef,
    campoInputRef,
    camposFiltrados,
    handleCampoChange,
    reset: resetCampos,
  } = useVentanillaCampos(open);

  const {
    cargandoVariantes,
    varianteId,
    varianteNombre,
    variantesHorarias,
    tieneVariantes,
    requiereVariante,
    bloqueadoPorVariantesNoHorarias,
    varianteSeleccionada,
    handleVarianteChange,
    setVarianteConvenio,
    reset: resetVariantes,
  } = useVentanillaVariantes(campoId, open);

  const {
    serviciosAdicionales,
    cargandoServicios,
    serviciosSeleccionados,
    serviciosAdicionalesValidos,
    toggleServicio,
    setCantidadServicio,
    reset: resetServicios,
  } = useVentanillaServicios(campoId, open);

  const {
    fecha,
    setFecha,
    bloques,
    cargandoSlots,
    slots,
    toggleSlot,
    horaAmedida,
    setHoraAmedida,
    toggleAmedida,
    slotsAmedida,
    horaInicioNocheGrilla,
    limpiarSlots,
    reset: resetSlots,
  } = useVentanillaSlots(open, campoId);

  // ── Estado del pagador ──
  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');

  const {
    asociacionDetectada,
    asociacionCargando,
    alumnos,
    cargandoAlumnos,
    reset: resetAsociacion,
  } = useVentanillaAsociacion(open, ciNit);

  const [alumnosPorFranja, setAlumnosPorFranja] = useState<Record<string, string[]>>({});

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);


  // [FIX B79] segundos del corte nocturno, con default 18:00:00 si la grilla no lo manda.
  const corteNocheSeg = useMemo(
    () => aSegundosHora(horaInicioNocheGrilla ?? '18:00:00'),
    [horaInicioNocheGrilla],
  );

  const clasificacionAplicable = useMemo(() => {
    if (slots.length === 0) return null;
    return aSegundosHora(slots[0].inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
  }, [slots, corteNocheSeg]);

  const totalServicios = useMemo(() => {
    return Object.entries(serviciosSeleccionados).reduce((acc, [id, cantidad]) => {
      const variante = serviciosAdicionalesValidos.find((v) => v.id === id);
      if (!variante) return acc;
      const tarifa = resolverTarifaServicio(variante, clasificacionAplicable);
      if (!tarifa) return acc;
      return acc + Number(tarifa.monto) * Math.max(1, Number(cantidad) || 1);
    }, 0);
  }, [serviciosSeleccionados, serviciosAdicionalesValidos, clasificacionAplicable]);

  const subtotalFranja = useMemo(() => {
    if (slots.length === 0) return 0;

    if (varianteSeleccionada) {
      const tarifas = varianteSeleccionada.tarifas;
      const unica = tarifas.find((t) => t.clasificacion === 'unica');

      const precioDe = (inicio: string): number => {
        if (unica) return Number(unica.monto);
        const tipo = aSegundosHora(inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
        const t = tarifas.find((x) => x.clasificacion === tipo);
        return t ? Number(t.monto) : 0;
      };

      return slots.reduce((acc, o) => acc + precioDe(o.inicio), 0);
    }

    const precioTramo = (inicio: string): number => {
      const enGrilla = bloques.find((b) => b.hora_inicio === inicio);
      if (enGrilla?.precio != null) return Number(enGrilla.precio);
      const tipo = aSegundosHora(inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
      const ref = bloques.find((b) => {
        const bt = aSegundosHora(b.hora_inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
        return bt === tipo && b.precio != null;
      });
      return ref?.precio != null ? Number(ref.precio) : 0;
    };
    return slots.reduce((acc, o) => acc + precioTramo(o.inicio), 0);
  }, [varianteSeleccionada, slots, clasificacionAplicable, bloques, corteNocheSeg]);

  const totalEstimado = subtotalFranja + totalServicios;

  // Al cambiar de campo, los hooks de variantes/servicios se resetean solos por
  // su efecto [open, campoId]. Acá solo limpiamos franjas/hora a medida, que
  // viven en este componente.
  const handleCampoChangeCompuesto = (nuevoId: string) => {
    handleCampoChange(nuevoId);
    limpiarSlots();
  };

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      resetCampos();
      resetVariantes();
      resetServicios();
      resetSlots();
      setNombre('');
      setCiNit('');
      setTelefono('');
      resetAsociacion();
      setAlumnosPorFranja({});
      setResultado(null);
    }, 200);
  };


  // P2b: si hay asociación vigente con tipo, preseleccionar la variante de convenio
  // que matchee. Declarado DESPUES del hook de variantes para que gane sobre la
  // preselección "única horaria". Sugerencia, no bloqueo: el backend (guardia
  // 5.6-5.8) sigue siendo la última línea.
  useEffect(() => {
    if (!asociacionDetectada?.vigente || !asociacionDetectada.tipo) return;
    const match = variantesHorarias.find(
      (v) => v.tipo_asociacion === asociacionDetectada.tipo,
    );
    if (match) {
      setVarianteConvenio(match.id, match.nombre);
    }
  }, [asociacionDetectada, variantesHorarias, setVarianteConvenio]);

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
  }, [campoAbierto, campoInputRef, campoWrapRef, setCampoAbierto]);

  const copiar = async (texto: string, clave: string) => {
    await navigator.clipboard.writeText(texto);
    setCopiado(clave);
    setTimeout(() => setCopiado(null), 1500);
  };

  const descargarQr = () => {
    const canvas = document.getElementById('qr-ventanilla') as HTMLCanvasElement | null;
    if (!canvas) return;
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `qr-${resultado?.codigo_seguimiento ?? 'reserva'}.png`;
    a.click();
  };

  const enviarWhatsapp = () => {
    if (!resultado) return;
    const texto = encodeURIComponent(
      `Reserva ${resultado.codigo_seguimiento}\nMonto: Bs ${resultado.monto_total.toFixed(2)}\n` +
        `Código de pago: ${resultado.referencia_recaudaciones ?? ''}\n` +
        `Pagá con el código en ventanilla de cualquier banco (presentá tu CI/NIT) o escaneá el QR para pagar por Síntesis.`,
    );
    window.open(`https://wa.me/?text=${texto}`, '_blank');
  };

  // [Fase 3] toggle alumno<->franja, keyed por slot.inicio (identificador
  // estable del slot, igual que toggleSlot/toggleAmedida).
  const toggleAlumnoFranja = (inicio: string, alumnoId: string) =>
    setAlumnosPorFranja((prev) => {
      const actual = prev[inicio] ?? [];
      const next = actual.includes(alumnoId)
        ? actual.filter((id) => id !== alumnoId)
        : [...actual, alumnoId];
      return { ...prev, [inicio]: next };
    });

  const puedeEnviar =
    !!campoId &&
    !!fecha &&
    slots.length > 0 &&
    !bloqueadoPorVariantesNoHorarias &&
    (!requiereVariante || !!varianteId) &&
    !!nombre.trim() &&
    !!ciNit.trim();

  const motivoDeshabilitado = !campoId
    ? 'Elegí el campo.'
    : !fecha
      ? 'Elegí la fecha.'
      : slots.length === 0
        ? 'Elegí al menos una franja libre.'
        : bloqueadoPorVariantesNoHorarias
          ? 'Este campo no tiene variantes horarias reservables.'
          : requiereVariante && !varianteId
            ? 'Elegí la variante arancelaria.'
            : !nombre.trim()
              ? 'Completá el nombre del pagador.'
              : !ciNit.trim()
                ? 'Completá el CI/NIT del pagador.'
                : null;

  const enviar = async () => {
    if (!puedeEnviar) return;
    const serviciosPayload = Object.entries(serviciosSeleccionados)
      .filter(([, cantidad]) => cantidad > 0)
      .map(([vid, cantidad]) => ({ campo_id: campoId, variante_id: vid, cantidad }));

    setEnviando(true);
    try {
      const res = await solicitudesReservaService.crearDesdeVentanilla({
        franjas: slots.map((o) => ({
          campo_id: campoId,
          ...(varianteId ? { variante_id: varianteId } : {}),
          fecha,
          hora_inicio: o.inicio,
          hora_fin: o.fin,
          ...((alumnosPorFranja[o.inicio] ?? []).length > 0
            ? { alumnos_ids: alumnosPorFranja[o.inicio] }
            : {}),
        })),
        ...(serviciosPayload.length > 0 ? { servicios: serviciosPayload } : {}),
        nombre_pagador: nombre.trim(),
        ci_nit_pagador: ciNit.trim(),
        telefono_pagador: telefono.trim() || undefined,
      });
      setResultado(res.data);
      onCreada();
    } catch (error) {
      const apiError = error as ApiError;
      const errs = apiError.response?.data?.errors;
      const mensaje =
        Array.isArray(errs?.franjas)
          ? errs.franjas[0]
          : Array.isArray(errs?.servicios)
            ? errs.servicios[0]
            : apiError.response?.data?.message ?? 'No se pudo crear la reserva.';
      toast.error(mensaje);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => (v ? onOpenChange(v) : cerrar())}>
      <DialogContent
        className="max-h-[92vh] w-full overflow-y-auto"
        style={{ maxWidth: MODAL_MAX_WIDTH }}
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plus className="size-5 text-teal-600" />
            Nueva reserva de ventanilla
          </DialogTitle>
          <DialogDescription>
            Generá la reserva y el código de pago SIREB para el ciudadano. El precio sale del espejo de SIREB, no se fija acá.
          </DialogDescription>
        </DialogHeader>

        {resultado ? (
          <div className="space-y-4 py-1">
            <div className="rounded-xl border border-emerald-300/60 bg-emerald-500/10 p-4 text-center dark:border-emerald-700">
              <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                Reserva creada
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
                        id="qr-ventanilla"
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

                {resultado.qr_string && (
                  <div className="flex items-center justify-between gap-2 pt-1">
                    <code className="min-w-0 truncate font-mono text-xs text-muted-foreground">
                      {resultado.qr_string}
                    </code>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="shrink-0"
                      onClick={() => copiar(resultado.qr_string!, 'qr')}
                    >
                      {copiado === 'qr' ? (
                        <Check className="size-4 text-green-600" />
                      ) : (
                        <Copy className="size-4" />
                      )}
                    </Button>
                  </div>
                )}

                <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">
                  <QrCode className="size-3.5 shrink-0" />
                  Pagá con este código en ventanilla de cualquier banco, presentando tu CI/NIT. O escaneá el QR para pagar por Síntesis.
                </p>
              </div>
            ) : (
              <p className="rounded-xl border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                La reserva quedó pendiente: SIREB no respondió y el reintento corre en segundo plano. El código de pago aparece en el detalle en unos minutos.
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
                              onClick={() => handleCampoChangeCompuesto(c.id)}
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
                <Label>Fecha *</Label>
                <Input
                  type="date"
                  value={fecha}
                  min={hoyBoliviaISO()}
                  onChange={(e) => setFecha(e.target.value)}
                />
              </div>
            </div>

            {(cargandoVariantes || tieneVariantes) && (
              <div className="space-y-2">
                <Label className="flex items-center gap-2">
                  Variante arancelaria
                  {requiereVariante && <span className="text-destructive">*</span>}
                </Label>

                {cargandoVariantes ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Cargando variantes del campo…
                  </div>
                ) : bloqueadoPorVariantesNoHorarias ? (
                  <p className="rounded-lg border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                    Este campo tiene variantes arancelarias, pero ninguna es por hora.
                    Esta ventanilla por ahora solo reserva franjas horarias.
                  </p>

                ) : (
                  <>
                    <Select
                      value={varianteId}
                      onValueChange={handleVarianteChange}
                      disabled={!requiereVariante}
                    >
                      <SelectTrigger className="w-full" aria-label="Variante arancelaria">
                        <span className="flex min-w-0 items-center gap-2 text-left">
                          <span className={`truncate ${varianteNombre ? '' : 'text-muted-foreground'}`}>
                            {varianteNombre || 'Elegí la variante arancelaria'}
                          </span>
                        </span>
                      </SelectTrigger>
                      <SelectContent>
                        {variantesHorarias.length === 0 ? (
                          <div className="px-2 py-1.5 text-sm text-muted-foreground">
                            Sin variantes horarias disponibles
                          </div>
                        ) : (
                          variantesHorarias.map((v) => {
                            const full = `${v.nombre}${v.servicio_sireb_codigo ? ` · ${v.servicio_sireb_codigo}` : ''}`;
                            return (
                              <SelectItem key={v.id} value={v.id}>
                                <span className="block truncate" title={full}>
                                  {full}
                                </span>
                              </SelectItem>
                            );
                          })
                        )}
                      </SelectContent>
                    </Select>

                    {asociacionDetectada?.vigente && varianteSeleccionada?.tipo_asociacion && (
                      <p className="flex items-center gap-1.5 pt-1 text-xs text-emerald-700 dark:text-emerald-400">
                        <Check className="size-3.5 shrink-0" />
                        Convenio {varianteSeleccionada.tipo_asociacion} · {asociacionDetectada.nombre}
                      </p>
                    )}

                    {varianteSeleccionada?.tarifas?.length ? (
                      <div className="flex flex-wrap gap-2 pt-1">
                        {varianteSeleccionada.tarifas.map((t) => {
                          const aplicable =
                            slots.length > 0 &&
                            (t.clasificacion === 'unica' ||
                              t.clasificacion === clasificacionAplicable);
                          return (
                            <Badge
                              key={t.tarifa_sireb_id}
                              variant={aplicable ? 'default' : 'secondary'}
                              className="gap-1.5"
                            >
                              <span className="text-xs">
                                {t.etiqueta ?? t.clasificacion ?? 'Tarifa'}
                              </span>
                              <span className="text-xs font-semibold tabular-nums">
                                Bs {Number(t.monto).toFixed(2)}
                              </span>
                            </Badge>
                          );
                        })}
                      </div>
                    ) : null}
                  </>
                )}
              </div>
            )}

            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <Label>Franjas libres *</Label>
                {cargandoSlots && <Loader2 className="size-4 animate-spin text-muted-foreground" />}
              </div>

              {!campoId || !fecha ? (
                <p className="text-sm text-muted-foreground">Elegí campo y fecha para ver horarios.</p>
              ) : (
                <>
                  {bloques.length === 0 && !cargandoSlots && (
                    <p className="text-sm text-muted-foreground">Sin franjas libres ese día.</p>
                  )}

                  {bloques.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {bloques.map((b) => {
                        const activo = slots.some((s) => s.inicio === b.hora_inicio);
                        const seleccionable = b.estado === 'libre';
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
                                ? undefined
                                : `${etiqueta} — no disponible para vender`
                            }
                            className={
                              seleccionable
                                ? ''
                                : 'pointer-events-none opacity-50 line-through decoration-muted-foreground/40'
                            }
                            onClick={() => toggleSlot(b)}
                          >
                            {hhmm(b.hora_inicio)}–{hhmm(b.hora_fin)}
                            {!seleccionable && (
                              <span className="ml-1.5 text-[10px] font-normal">
                                {etiqueta}
                              </span>
                            )}
                          </Button>
                        );
                      })}
                    </div>
                  )}

                  {/* ── Opción B: franja a medida (inicio libre + 1 hora) ── */}
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

                    <p className="text-[11px] leading-snug text-muted-foreground">
                      Para horarios que no están en la grilla (ej. 08:30). Se cobra como 1 hora,
                      con el tramo (diurno/nocturno) de su hora de inicio.
                      El solape con otras franjas lo valida el sistema al crear.
                    </p>
                    {/* [D42] el funcionario no busca el botón de cobro atrasado acá. */}
                    <p className="text-[11px] leading-snug text-amber-700 dark:text-amber-400">
                      Las franjas ya pasadas no se venden desde acá. Si alguien usó la cancha y no
                      pudo pagar (caída del sistema), cobralo desde <strong>Regularizar uso</strong>{' '}
                      en el listado de Reservas.
                    </p>
                  </div>
                </>
              )}
            </div>

            {asociacionDetectada?.vigente && slots.length > 0 && (cargandoAlumnos || alumnos.length > 0) && (
              <div className="space-y-3 rounded-xl border bg-muted/30 p-3">
                <Label className="text-sm font-semibold">Alumnos por franja</Label>
                {cargandoAlumnos ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Cargando alumnos de la asociación…
                  </div>
                ) : (
                  <>
                    <p className="text-xs text-muted-foreground">
                      Marcá quiénes asisten a cada franja. La lista la cargó el presidente de la asociación.
                    </p>
                    {slots.map((o) => (
                      <div key={o.inicio} className="space-y-1.5">
                        <p className="text-xs font-medium tabular-nums">
                          {hhmm(o.inicio)}–{hhmm(o.fin)}
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                          {alumnos.map((al) => {
                            const activo = (alumnosPorFranja[o.inicio] ?? []).includes(al.id);
                            return (
                              <Button
                                key={al.id}
                                type="button"
                                variant={activo ? 'default' : 'outline'}
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => toggleAlumnoFranja(o.inicio, al.id)}
                                title={`CI ${al.ci}`}
                              >
                                {al.nombre_completo}
                              </Button>
                            );
                          })}
                        </div>
                      </div>
                    ))}
                  </>
                )}
              </div>
            )}

            {(cargandoServicios || serviciosAdicionales.length > 0) && (
              <div className="space-y-2">
                <Label className="text-sm font-semibold">Servicios adicionales</Label>

                {cargandoServicios ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Cargando servicios del campo…
                  </div>
                ) : serviciosAdicionalesValidos.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    {serviciosAdicionales.length === 0
                      ? 'Este campo no tiene servicios adicionales disponibles.'
                      : 'Los servicios disponibles no tienen una tarifa única vigente.'}
                  </p>
                ) : (
                  <div className="space-y-2">
                    {serviciosAdicionalesValidos.map((v) => {
                      const cantidad = serviciosSeleccionados[v.id] ?? 0;
                      const seleccionado = cantidad > 0;
                      const tarifa = resolverTarifaServicio(v, clasificacionAplicable);
                      if (!tarifa) return null;
                      const codigo = v.servicio_sireb_codigo ?? v.codigo;
                      const esTramo = v.tarifas.length > 1;

                      return (
                        <div
                          key={v.id}
                          className={`flex items-center justify-between gap-3 rounded-lg border p-3 transition-colors ${
                            seleccionado ? 'border-primary/40 bg-primary/5' : 'hover:border-primary/30'
                          }`}
                        >
                          <div className="flex min-w-0 flex-1 items-start gap-3">
                            <Checkbox
                              id={v.id}
                              checked={seleccionado}
                              onCheckedChange={(checked) => toggleServicio(v.id, checked)}
                              className="mt-0.5 shrink-0"
                            />
                            <Label htmlFor={v.id} className="min-w-0 flex-1 cursor-pointer">
                              <span className="block truncate text-sm font-medium" title={v.nombre}>
                                {v.nombre}
                              </span>
                              <span
                                className="block truncate text-xs text-muted-foreground"
                                title={codigo}
                              >
                                {codigo}
                              </span>
                            </Label>
                          </div>

                          <div className="flex shrink-0 items-center gap-3">
                            <div className="w-28 text-right">
                              <span className="block text-sm font-semibold tabular-nums">
                                Bs {Number(tarifa.monto).toFixed(2)}
                              </span>
                              {esTramo && (
                                <span className="block text-[10px] leading-tight text-muted-foreground">
                                  Diurno Bs{' '}
                                  {Number(
                                    v.tarifas.find((t) => t.clasificacion === 'diurna')?.monto ?? 0,
                                  ).toFixed(2)}{' '}
                                  · Nocturno Bs{' '}
                                  {Number(
                                    v.tarifas.find((t) => t.clasificacion === 'nocturna')?.monto ?? 0,
                                  ).toFixed(2)}
                                </span>
                              )}
                            </div>
                            {seleccionado && (
                              <StepperCantidad
                                valor={cantidad}
                                onChange={(val) => setCantidadServicio(v.id, val)}
                                ariaLabel={v.nombre}
                              />
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            )}

            <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
              <p className="text-sm font-semibold">Datos del pagador</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Nombre completo *</Label>
                  <Input value={nombre} onChange={(e) => setNombre(e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>CI / NIT *</Label>
                  <div className="relative">
                    <Input value={ciNit} onChange={(e) => setCiNit(e.target.value)} />
                    {asociacionCargando && (
                      <Loader2 className="absolute right-2 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    )}
                  </div>
                  {asociacionDetectada?.vigente && (
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">
                      Asociación vigente: {asociacionDetectada.nombre}
                    </p>
                  )}
                  {!asociacionDetectada && !asociacionCargando && (
                    <p className="text-xs text-muted-foreground">
                      Tipeá el CI de un responsable con convenio: la variante se elige sola.
                    </p>
                  )}
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Teléfono (opcional)</Label>
                  <Input value={telefono} onChange={(e) => setTelefono(e.target.value)} />
                </div>
              </div>
            </div>

            <div className="rounded-xl border bg-card p-3">
              <div className="flex items-center justify-between gap-3">
                <span className="min-w-0 truncate text-xs text-muted-foreground">
                  Estimado · el monto final lo confirma SIREB
                </span>
                <span className="shrink-0 text-lg font-bold tabular-nums">
                  Bs {totalEstimado.toFixed(2)}
                </span>
              </div>
              <p className="mt-1 truncate text-xs text-muted-foreground">
                {slots.length > 0
                  ? `${slots.length} franja${slots.length === 1 ? '' : 's'} · Bs ${subtotalFranja.toFixed(2)}`
                  : 'Sin franja'}
                {' · '}
                Servicios Bs {totalServicios.toFixed(2)}
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
                Crear reserva y código de pago
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
