import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  Check,
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
  catalogoSirebService,
  type VarianteArancelaria,
  type VarianteArancelariaTarifa,
} from '@/services/catalogoSirebService';
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

/**
 * Espejo frontend de resolverServiciosParaCrear: única > única-sola >
 * diurna/nocturna según la hora de la franja. Devuelve null si no se puede
 * resolver (el servicio no entra al checkbox ni al estimado).
 */
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

// Umbral nocturno = default del backend (hora_inicio_noche ?? 18:00).
// Solo para el ESTIMADO orientativo; el monto autoritativo lo confirma SIREB.
const HORA_INICIO_NOCHE_ESTIMADA = 18;

/** Ancho del modal en style inline para ganar al `sm:max-w-lg` del base. */
const MODAL_MAX_WIDTH = '52rem'; // ~832px

/** Hoy en hora Bolivia (UTC-4), no en UTC del navegador. */
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
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [campoId, setCampoId] = useState<string>('');
  const [campoNombre, setCampoNombre] = useState<string>('');

  const [variantes, setVariantes] = useState<VarianteArancelaria[]>([]);
  const [cargandoVariantes, setCargandoVariantes] = useState(false);
  const [varianteId, setVarianteId] = useState<string>('');
  const [varianteNombre, setVarianteNombre] = useState<string>('');

  const [serviciosAdicionales, setServiciosAdicionales] = useState<VarianteArancelaria[]>([]);
  const [cargandoServicios, setCargandoServicios] = useState(false);
  const [serviciosSeleccionados, setServiciosSeleccionados] = useState<Record<string, number>>({});

  const [fecha, setFecha] = useState<string>('');
  const [bloques, setBloques] = useState<Bloque[]>([]);
  const [cargandoSlots, setCargandoSlots] = useState(false);
  const [slots, setSlots] = useState<string[]>([]);

  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);

  const variantesHorarias = useMemo(
    () => variantes.filter((v) => v.unidad_medida === 'hora'),
    [variantes],
  );

  const tieneVariantes = variantes.length > 0;
  const requiereVariante = tieneVariantes;
  const bloqueadoPorVariantesNoHorarias =
    tieneVariantes && variantesHorarias.length === 0;

  const varianteSeleccionada = useMemo(
    () => variantesHorarias.find((v) => v.id === varianteId) ?? null,
    [variantesHorarias, varianteId],
  );

  const clasificacionAplicable = useMemo(() => {
    if (slots.length === 0) return null;
    const hora = parseInt(slots[0].slice(0, 2), 10);
    if (Number.isNaN(hora)) return null;
    return hora < HORA_INICIO_NOCHE_ESTIMADA ? 'diurna' : 'nocturna';
  }, [slots]);

  const serviciosAdicionalesValidos = useMemo(
    () =>
      serviciosAdicionales.filter((v) => {
        if (!v.servicio_sireb_id) return false;
        const t = v.tarifas;
        if (t.length === 0) return false;
        if (t.length === 1) return true;
        // P8.4B: servicios con tramo (escenario) son válidos si tienen
        // diurna o nocturna; el precio exacto se resuelve con la franja.
        return t.some(
          (x) =>
            x.clasificacion === 'unica' ||
            x.clasificacion === 'diurna' ||
            x.clasificacion === 'nocturna',
        );
      }),
    [serviciosAdicionales],
  );

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
    if (!varianteSeleccionada || slots.length === 0) return 0;
    const tarifas = varianteSeleccionada.tarifas;
    const unica = tarifas.find((t) => t.clasificacion === 'unica');
    const porClas = clasificacionAplicable
      ? tarifas.find((t) => t.clasificacion === clasificacionAplicable)
      : null;
    const tarifa = unica ?? porClas;
    if (!tarifa) return 0;
    return Number(tarifa.monto) * slots.length;
  }, [varianteSeleccionada, slots, clasificacionAplicable]);

  const totalEstimado = subtotalFranja + totalServicios;

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      setCampoId('');
      setCampoNombre('');
      setVariantes([]);
      setVarianteId('');
      setVarianteNombre('');
      setServiciosAdicionales([]);
      setServiciosSeleccionados({});
      setFecha('');
      setBloques([]);
      setSlots([]);
      setNombre('');
      setCiNit('');
      setTelefono('');
      setResultado(null);
    }, 200);
  };

  useEffect(() => {
    if (!open) return;
    solicitudesReservaService
      .listarCamposPublicos()
      .then((lista) => {
        setCampos(lista);
        const sinNombre = lista.filter((c) => !c.nombre || c.nombre === c.id);
        if (sinNombre.length > 0) {
          console.warn('[ventanilla] campos sin nombre legible:', sinNombre);
        }
      })
      .catch(() => setCampos([]));
  }, [open]);

  useEffect(() => {
    if (!open || !campoId) {
      setVariantes([]);
      setVarianteId('');
      setVarianteNombre('');
      return;
    }
    let activo = true;
    setCargandoVariantes(true);
    setVarianteId('');
    setVarianteNombre('');
    catalogoSirebService
      .listarVariantesPorCampo(campoId)
      .then((lista) => {
        if (!activo) return;
        setVariantes(lista);
        const horarias = lista.filter((v) => v.unidad_medida === 'hora');
        if (horarias.length === 1) {
          setVarianteId(horarias[0].id);
          setVarianteNombre(horarias[0].nombre);
        }
      })
      .catch(() => {
        if (!activo) return;
        setVariantes([]);
        toast.error('No se pudieron cargar las variantes arancelarias.');
      })
      .finally(() => {
        if (activo) setCargandoVariantes(false);
      });
    return () => {
      activo = false;
    };
  }, [open, campoId]);

  useEffect(() => {
    if (!open || !campoId) {
      setServiciosAdicionales([]);
      setServiciosSeleccionados({});
      return;
    }
    let activo = true;
    setCargandoServicios(true);
    catalogoSirebService
      .listarServiciosAdicionalesPorCampo(campoId)
      .then((lista) => {
        if (!activo) return;
        setServiciosAdicionales(lista);
        setServiciosSeleccionados((prev) => {
          const validos = new Set(
            lista
              .filter((v) => Boolean(v.servicio_sireb_id) && v.tarifas.length === 1)
              .map((v) => v.id),
          );
          const next: Record<string, number> = {};
          for (const [id, cantidad] of Object.entries(prev)) {
            if (validos.has(id)) next[id] = cantidad;
          }
          return next;
        });
      })
      .catch(() => {
        if (!activo) return;
        setServiciosAdicionales([]);
        setServiciosSeleccionados({});
        toast.error('No se pudieron cargar los servicios adicionales.');
      })
      .finally(() => {
        if (activo) setCargandoServicios(false);
      });
    return () => {
      activo = false;
    };
  }, [open, campoId]);

  const handleCampoChange = (nuevoId: string) => {
    setCampoId(nuevoId);
    const campo = campos.find((c) => c.id === nuevoId);
    setCampoNombre(campo?.nombre && campo.nombre !== campo.id ? campo.nombre : '');
    setVarianteId('');
    setVarianteNombre('');
    setVariantes([]);
    setServiciosAdicionales([]);
    setServiciosSeleccionados({});
  };

  const handleVarianteChange = (nuevoId: string) => {
    setVarianteId(nuevoId);
    const variante = variantesHorarias.find((v) => v.id === nuevoId);
    setVarianteNombre(variante?.nombre ?? '');
  };

  const cargarSlots = useCallback(async () => {
    if (!campoId || !fecha) return;
    setCargandoSlots(true);
    setSlots([]);
    try {
      const disp = await solicitudesReservaService.disponibilidad(campoId, fecha);
      setBloques(disp.abierto ? disp.bloques.filter((b) => b.estado === 'libre') : []);
    } catch {
      setBloques([]);
      toast.error('No se pudo consultar la disponibilidad del campo.');
    } finally {
      setCargandoSlots(false);
    }
  }, [campoId, fecha]);

  useEffect(() => {
    cargarSlots();
  }, [cargarSlots]);

  const toggleSlot = (hora: string) =>
    setSlots((prev) =>
      prev.includes(hora) ? prev.filter((h) => h !== hora) : [...prev, hora].sort(),
    );

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
        `Pagá en ventanilla del Banco Unión o escaneá el QR.`,
    );
    window.open(`https://wa.me/?text=${texto}`, '_blank');
  };

  const toggleServicio = (vid: string, checked: boolean | 'indeterminate') => {
    if (checked === true) {
      setServiciosSeleccionados((prev) => ({ ...prev, [vid]: prev[vid] ?? 1 }));
    } else {
      setServiciosSeleccionados((prev) => {
        const next = { ...prev };
        delete next[vid];
        return next;
      });
    }
  };

  const setCantidadServicio = (vid: string, valor: number) => {
    const cantidad = Number.isFinite(valor)
      ? Math.max(1, Math.min(100, Math.floor(valor)))
      : 1;
    setServiciosSeleccionados((prev) => ({ ...prev, [vid]: cantidad }));
  };

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
    const bloqueDe = (hora: string) => bloques.find((b) => b.hora_inicio === hora)!;
    const serviciosPayload = Object.entries(serviciosSeleccionados)
      .filter(([, cantidad]) => cantidad > 0)
      .map(([vid, cantidad]) => ({ campo_id: campoId, variante_id: vid, cantidad }));

    setEnviando(true);
    try {
      const res = await solicitudesReservaService.crearDesdeVentanilla({
        franjas: slots.map((hora) => ({
          campo_id: campoId,
          ...(varianteId ? { variante_id: varianteId } : {}),
          fecha,
          hora_inicio: bloqueDe(hora).hora_inicio,
          hora_fin: bloqueDe(hora).hora_fin,
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
                Monto confirmado por SIREB
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
                  Presentá este código o escaneá el QR en ventanilla del Banco Unión con tu CI.
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
              <div className="min-w-0 space-y-2">
                <Label>Campo *</Label>
                <Select value={campoId} onValueChange={handleCampoChange}>
                  <SelectTrigger className="w-full" aria-label="Campo deportivo">
                    <span className="flex min-w-0 items-center gap-2 text-left">
                      <MapPin className="size-4 shrink-0 text-muted-foreground" />
                      <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
                        {campoNombre || 'Elegí el campo'}
                      </span>
                    </span>
                  </SelectTrigger>
                  <SelectContent>
                    {campos.length === 0 ? (
                      <div className="px-2 py-1.5 text-sm text-muted-foreground">
                        Sin campos activos disponibles
                      </div>
                    ) : (
                      campos.map((c) => (
                        <SelectItem key={c.id} value={c.id}>
                          <span className="block truncate" title={c.nombre || c.id}>
                            {c.nombre || c.id}
                          </span>
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>
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
              ) : bloques.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  {cargandoSlots ? 'Consultando…' : 'Sin franjas libres ese día.'}
                </p>
              ) : (
                <div className="flex flex-wrap gap-2">
                  {bloques.map((b) => {
                    const activo = slots.includes(b.hora_inicio);
                    return (
                      <Button
                        key={b.hora_inicio}
                        type="button"
                        variant={activo ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => toggleSlot(b.hora_inicio)}
                      >
                        {hhmm(b.hora_inicio)}–{hhmm(b.hora_fin)}
                      </Button>
                    );
                  })}
                </div>
              )}
            </div>

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
                  <Input value={ciNit} onChange={(e) => setCiNit(e.target.value)} />
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Teléfono (opcional)</Label>
                  <Input value={telefono} onChange={(e) => setTelefono(e.target.value)} />
                </div>
              </div>
            </div>

            {/* Caja de estimado: block, con truncate → nunca colapsa palabra por palabra */}
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
