import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Check,
  Copy,
  Download,
  Loader2,
  MapPin,
  MessageCircle,
  QrCode,
  Waves,
} from 'lucide-react';
import { QRCodeCanvas } from 'qrcode.react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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
import { piscinaService } from '@/services/piscinaService';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { CampoOption } from '@/types/reservas';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreada: () => void;
}

interface PerfilDisponible {
  perfil: string;
  etiqueta?: string;
  disponible: number;
  reservado: number;
  aforo: number;
  precio: number; // [FIX B69/B66] el backend (147) ya lo devuelve; el parser lo tiraba
}

interface TurnoDisponible {
  id: string;
  fecha?: string;
  hora_inicio: string;
  hora_fin: string;
  perfiles: PerfilDisponible[];
  perfiles_sin_tarifa: string[]; // [FIX B70]
}

interface Resultado {
  codigo_seguimiento: string;
  monto_total: number;
  referencia_recaudaciones: string | null;
  qr_string: string | null;
}

type CampoConModalidad = CampoOption & { modalidad_reserva?: string };

const perfilLabel: Record<string, string> = {
  mayor_12: 'Mayores de 12',
  menor_12: 'Menores de 12',
  institucion: 'Institución',
};

const hhmm = (h: string) => (h || '').substring(0, 5);
const MODAL_MAX_WIDTH = '52rem';

// [FIX tz piscina] espejo de hoyBoliviaISO() del diálogo de cancha/regularizar.
// new Date().toISOString() es UTC: entre 20:00 y 23:59 hora Bolivia ya daba el
// día siguiente y el date input bloqueaba reservar HOY. Comparamos en America/La_Paz.
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

function extraerTurnos(resp: unknown): TurnoDisponible[] {
  const body = resp as any;
  const arr = Array.isArray(body)
    ? body
    : Array.isArray(body?.data)
      ? body.data
      : Array.isArray(body?.turnos)
        ? body.turnos
        : [];

  return (arr as any[])
    .map((t) => ({
      id: String(t.id ?? t.turno_id ?? ''),
      fecha: t.fecha ?? t.fecha_turno ?? undefined,
      hora_inicio: String(t.hora_inicio ?? t.inicio ?? ''),
      hora_fin: String(t.hora_fin ?? t.fin ?? ''),
      perfiles: (Array.isArray(t.perfiles) ? t.perfiles : []).map((p: any) => ({
        perfil: String(p.perfil ?? p.codigo ?? ''),
        etiqueta: p.etiqueta ?? undefined, // [FIX B66]
        disponible: Number(p.disponible ?? p.cupo_libre ?? 0),
        reservado: Number(p.reservado ?? 0),
        aforo: Number(p.aforo ?? p.cupo ?? 0),
        precio: Number(p.precio ?? 0),     // [FIX B69/B66] tarifa SIREB, ya no se descarta
      })),
      perfiles_sin_tarifa: Array.isArray(t.perfiles_sin_tarifa) ? t.perfiles_sin_tarifa : [], // [FIX B70]
    }))
    .filter((t) => t.id && (t.perfiles.length > 0 || t.perfiles_sin_tarifa.length > 0));
}

export default function DialogCrearReservaPiscinaVentanilla({
  open,
  onOpenChange,
  onCreada,
}: Props) {
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [campoId, setCampoId] = useState('');
  const [campoNombre, setCampoNombre] = useState('');
  const [fecha, setFecha] = useState('');

  const [turnos, setTurnos] = useState<TurnoDisponible[]>([]);
  const [cargandoTurnos, setCargandoTurnos] = useState(false);
  const [turnoId, setTurnoId] = useState('');
  const [cantidades, setCantidades] = useState<Record<string, number>>({});

  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);

  const camposPiscina = useMemo(
    () =>
      (campos as CampoConModalidad[]).filter(
        (c) => c.modalidad_reserva === 'turno_por_perfil',
      ),
    [campos],
  );

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      setCampoId('');
      setCampoNombre('');
      setFecha('');
      setTurnos([]);
      setTurnoId('');
      setCantidades({});
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
      .then(setCampos)
      .catch(() => setCampos([]));
  }, [open]);

  const cargarTurnos = useCallback(async () => {
    if (!campoId || !fecha) return;

    setCargandoTurnos(true);
    setTurnos([]);
    setTurnoId('');
    setCantidades({});

    try {
      const resp = await piscinaService.turnosDisponibles(campoId, fecha);
      const parsed = extraerTurnos(resp);
      setTurnos(parsed);

      if (parsed.length === 0) {
        console.warn('[piscina-ventanilla] respuesta sin turnos parseables:', resp);
      }
    } catch {
      toast.error('No se pudo consultar la disponibilidad de piscina.');
    } finally {
      setCargandoTurnos(false);
    }
  }, [campoId, fecha]);

  useEffect(() => {
    cargarTurnos();
  }, [cargarTurnos]);

  const handleCampoChange = (id: string) => {
    setCampoId(id);
    const campo = campos.find((c) => c.id === id);
    setCampoNombre(campo?.nombre && campo.nombre !== campo.id ? campo.nombre : '');
  };

  const seleccionarTurno = (id: string) => {
    setTurnoId(id);
    setCantidades({});
  };

  const setCantidad = (perfil: string, valor: number, max: number) => {
    const seguro = Math.max(0, Math.min(valor, max));
    setCantidades((prev) => ({ ...prev, [perfil]: seguro }));
  };

  const turnoSeleccionado = turnos.find((t) => t.id === turnoId);
  const totalPersonas = Object.values(cantidades).reduce((a, b) => a + b, 0);

  // [FIX B69/B66] total estimado en vivo, con la tarifa SIREB que ahora sí se parsea.
  const montoEstimado = useMemo(() => {
    if (!turnoSeleccionado) return 0;
    return turnoSeleccionado.perfiles.reduce((suma, p) => {
      const cant = cantidades[p.perfil] ?? 0;
      return suma + cant * p.precio;
    }, 0);
  }, [turnoSeleccionado, cantidades]);

  // [FIX B75] ¿existe algún turno con cupo real?
  const hayTurnoConCupo = turnos.some(
    (t) => t.perfiles.reduce((a, p) => a + p.disponible, 0) > 0,
  );

  const puedeEnviar =
    !!campoId &&
    !!fecha &&
    !!turnoId &&
    totalPersonas > 0 &&
    !!nombre.trim() &&
    !!ciNit.trim() &&
    !cargandoTurnos;

  const motivoDeshabilitado = !campoId
    ? 'Elegí el campo de piscina.'
    : !fecha
      ? 'Elegí la fecha.'
      : cargandoTurnos
        ? 'Consultando turnos…'
        : turnos.length === 0
          ? 'No hay turnos con cupo para esa fecha. Configuralos en Paramétricas → Piscina.'
          : !hayTurnoConCupo
            ? 'Todos los turnos de esa fecha están completos. Elegí otra fecha.' // [FIX B75]
            : !turnoId
              ? 'Elegí un turno.'
              : totalPersonas === 0
                ? 'Asigná al menos una persona en algún perfil.'
                : !nombre.trim()
                  ? 'Completá el nombre del pagador.'
                  : !ciNit.trim()
                    ? 'Completá el CI/NIT del pagador.'
                    : null;

  const copiar = async (texto: string, clave: string) => {
    await navigator.clipboard.writeText(texto);
    setCopiado(clave);
    setTimeout(() => setCopiado(null), 1500);
  };

  const descargarQr = () => {
    const canvas = document.getElementById('qr-piscina-ventanilla') as HTMLCanvasElement | null;
    if (!canvas) return;
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `qr-piscina-${resultado?.codigo_seguimiento ?? 'reserva'}.png`;
    a.click();
  };

  const enviarWhatsapp = () => {
    if (!resultado) return;
    const texto = encodeURIComponent(
      `Reserva piscina ${resultado.codigo_seguimiento}\n` +
      `Monto: Bs ${resultado.monto_total.toFixed(2)}\n` +
      `Código de pago: ${resultado.referencia_recaudaciones ?? ''}\n` +
      `Pagá en ventanilla del Banco Unión o escaneá el QR.`,
    );
    window.open(`https://wa.me/?text=${texto}`, '_blank');
  };

  const enviar = async () => {
    if (!puedeEnviar) return;

    const items = Object.entries(cantidades)
      .filter(([, cant]) => cant > 0)
      .map(([perfil, cant]) => ({ perfil, cantidad: cant }));

    setEnviando(true);
    try {
      const res = await piscinaService.crearDesdeVentanilla({
        fecha,
        turno_id: turnoId,
        items,
        nombre_pagador: nombre.trim(),
        ci_nit_pagador: ciNit.trim(),
        telefono_pagador: telefono.trim() || undefined,
      });
      setResultado(res.data);
      onCreada();
    } catch (error: any) {
      toast.error(
        error.response?.data?.message ??
          'No se pudo crear la reserva de piscina.',
      );
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
            <Waves className="size-5 text-teal-600" />
            Nueva reserva de piscina (ventanilla)
          </DialogTitle>
          {/* [PROTEGER] la frase SIREB ya estaba; no se toca. */}
          <DialogDescription>
            Elegí turno y cantidades por perfil. El precio sale del espejo SIREB.
          </DialogDescription>
        </DialogHeader>

        {resultado ? (
          <div className="space-y-4 py-2">
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
                        id="qr-piscina-ventanilla"
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
                  <code className="font-mono text-xl font-bold tracking-widest">
                    {resultado.referencia_recaudaciones}
                  </code>
                  <Button variant="ghost" size="icon" onClick={() => copiar(resultado.referencia_recaudaciones!, 'ref')}>
                    {copiado === 'ref' ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                  </Button>
                </div>

                {resultado.qr_string && (
                  <div className="flex items-center justify-between gap-2 pt-1">
                    <code className="truncate font-mono text-xs text-muted-foreground">
                      {resultado.qr_string}
                    </code>
                    <Button variant="ghost" size="icon" onClick={() => copiar(resultado.qr_string!, 'qr')}>
                      {copiado === 'qr' ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                    </Button>
                  </div>
                )}

                <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">
                  <QrCode className="size-3.5" />
                  El ciudadano paga con este código o QR; el sistema confirma solo.
                </p>
              </div>
            ) : (
              <p className="rounded-xl border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                La reserva quedó pendiente: SIREB no respondió. Revisá el detalle en unos minutos.
              </p>
            )}

            <Button className="w-full" onClick={cerrar}>
              Listo
            </Button>
          </div>
        ) : (
          <div className="space-y-5 py-2">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="min-w-0 space-y-2">
                <Label>Campo piscina *</Label>
                <Select value={campoId} onValueChange={handleCampoChange}>
                  <SelectTrigger className="w-full">
                    <span className="flex min-w-0 items-center gap-2 text-left">
                      <MapPin className="size-4 shrink-0 text-muted-foreground" />
                      <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
                        {campoNombre || 'Elegí el campo'}
                      </span>
                    </span>
                  </SelectTrigger>
                  <SelectContent>
                    {camposPiscina.length === 0 ? (
                      <div className="px-2 py-1.5 text-sm text-muted-foreground">
                        Sin campos de piscina disponibles
                      </div>
                    ) : (
                      camposPiscina.map((c) => (
                        <SelectItem key={c.id} value={c.id}>
                          {c.nombre || c.id}
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>

                {campos.length > 0 && camposPiscina.length === 0 && (
                  <p className="flex items-start gap-1.5 rounded-lg border border-amber-300/60 bg-amber-500/10 p-2 text-xs text-amber-800 dark:border-amber-700 dark:text-amber-200">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    No se pudo identificar qué campos son de piscina: el listado
                    no expone «modalidad_reserva». Confirmá el resource de
                    listarCamposPublicos o creá los turnos en Paramétricas → Piscina.
                  </p>
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

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label>Turnos disponibles *</Label>
                {cargandoTurnos && <Loader2 className="size-4 animate-spin text-muted-foreground" />}
              </div>

              {!campoId || !fecha ? (
                <p className="text-sm text-muted-foreground">Elegí campo y fecha para ver turnos.</p>
              ) : turnos.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  {cargandoTurnos ? 'Consultando…' : 'Sin turnos disponibles con cupo para esa fecha.'}
                </p>
              ) : (
                <div className="space-y-2">
                  {turnos.map((t) => {
                    const activo = turnoId === t.id;
                    const totalCupo = t.perfiles.reduce((a, p) => a + p.disponible, 0);
                    const completo = totalCupo === 0; // [FIX B75]
                    return (
                      <Button
                        key={t.id}
                        type="button"
                        variant={activo ? 'default' : 'outline'}
                        className="w-full justify-start"
                        disabled={completo}            // [FIX B75] no seleccionable si está lleno
                        onClick={() => seleccionarTurno(t.id)}
                      >
                        <div className="flex w-full items-center justify-between gap-2">
                          <span className="font-mono">
                            {hhmm(t.hora_inicio)}–{hhmm(t.hora_fin)}
                          </span>
                          <Badge variant={completo ? 'outline' : 'secondary'}
                                 className={completo ? 'text-muted-foreground' : ''}>
                            {completo ? 'Completo' : `${totalCupo} libres`}
                          </Badge>
                        </div>
                      </Button>
                    );
                  })}
                </div>
              )}
            </div>

            {turnoSeleccionado && (
              <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
                <p className="text-sm font-semibold">Cantidades por perfil</p>
                <div className="space-y-2">
                  {turnoSeleccionado.perfiles.map((p) => {
                    const cant = cantidades[p.perfil] ?? 0;
                    return (
                      <div key={p.perfil} className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                          <p className="text-sm font-medium">{p.etiqueta ?? perfilLabel[p.perfil] ?? p.perfil}</p>
                          <p className="text-xs text-muted-foreground">
                            Disponibles: {p.disponible} · {' '}
                            {/* [FIX B66] precio por persona visible */}
                            <span className="font-mono font-semibold text-slate-700">
                              Bs {p.precio.toFixed(2)} c/u
                            </span>
                          </p>
                          {/* [FIX B66] subtotal vivo por perfil */}
                          {cant > 0 && (
                            <p className="text-xs text-muted-foreground">
                              {cant} × Bs {p.precio.toFixed(2)} ={' '}
                              <span className="font-mono font-semibold text-slate-700">
                                Bs {(cant * p.precio).toFixed(2)}
                              </span>
                            </p>
                          )}
                        </div>
                        <Input
                          type="number"
                          min={0}
                          max={p.disponible}
                          value={cant}
                          onChange={(e) =>
                            setCantidad(p.perfil, Number(e.target.value || 0), p.disponible)
                          }
                          className="w-24"
                        />
                      </div>
                    );
                  })}
                </div>

                {/* [FIX B70] señal de perfiles que se cayeron por falta de tarifa SIREB */}
                {turnoSeleccionado.perfiles_sin_tarifa.length > 0 && (
                  <p className="flex items-start gap-1.5 rounded-lg border border-amber-300/60 bg-amber-500/10 p-2 text-xs text-amber-800 dark:border-amber-700 dark:text-amber-200">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    No se ofrecen {turnoSeleccionado.perfiles_sin_tarifa.length}{' '}
                    {turnoSeleccionado.perfiles_sin_tarifa.length === 1 ? 'perfil' : 'perfiles'}{' '}
                    ({turnoSeleccionado.perfiles_sin_tarifa.map((x) => perfilLabel[x] ?? x).join(', ')})
                    porque no tienen tarifa SIREB espejada. Crealos en Paramétricas → Piscina o
                    sincronizá las tarifas.
                  </p>
                )}

                {totalPersonas > 0 && (
                  <Badge variant="secondary">
                    {totalPersonas} {totalPersonas === 1 ? 'persona' : 'personas'}
                  </Badge>
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

            {/* [FIX B69] bloque de total SIREB, espejo del modal de cancha */}
            {totalPersonas > 0 && (
              <div className="flex items-center justify-between gap-3 rounded-xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900 dark:bg-teal-950/30">
                <div className="min-w-0">
                  <p className="text-xs font-semibold uppercase tracking-wider text-teal-700 dark:text-teal-300">
                    Estimado · el monto final lo confirma SIREB
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {totalPersonas} {totalPersonas === 1 ? 'persona' : 'personas'} ·{' '}
                    {turnoSeleccionado?.perfiles
                      .filter((p) => (cantidades[p.perfil] ?? 0) > 0)
                      .map((p) => `${cantidades[p.perfil]}×${perfilLabel[p.perfil] ?? p.perfil}`)
                      .join(' + ')}
                  </p>
                </div>
                <p className="text-2xl font-bold tabular-nums text-teal-900 dark:text-teal-100">
                  Bs {montoEstimado.toFixed(2)}
                </p>
              </div>
            )}

            {!puedeEnviar && motivoDeshabilitado && (
              <p className="text-xs text-amber-700 dark:text-amber-400">
                {motivoDeshabilitado}
              </p>
            )}

            <div className="flex justify-end gap-2">
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
