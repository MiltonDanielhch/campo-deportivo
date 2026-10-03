import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  ArrowUp,
  ArrowDown,
  Lightbulb,
  Sun,
  Save,
  Clock,
  CheckCircle2,
  Minus,
  RefreshCw,
  ShieldCheck,
  ExternalLink,
} from 'lucide-react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useBreadcrumbOverride } from '@/context/BreadcrumbContext';
import { camposService } from '@/services/camposService';
import { catalogoSirebService } from '@/services/catalogoSirebService';
import { tarifasService } from '@/services/tarifasService';
import type {
  CampoDeportivo,
  HistorialTarifas,
  TarifaCampo,
  TipoTarifa,
} from '@/types/parametricas';

/**
 * Panel de SIREB donde se administran los servicios y sus tarifas.
 * Este sistema NO fija precios: solo refleja lo que se define ahí.
 */
const SIREB_PANEL_SERVICIOS_URL =
  import.meta.env.VITE_SIREB_PANEL_SERVICIOS_URL ||
  'https://test.sireb.beni.gob.bo/panel/servicios';

// ─── Helpers ─────────────────────────────────────────────────────────────

const formatearPrecio = (precio: string | null | undefined) =>
  precio ? `Bs ${parseFloat(precio).toFixed(2)}` : null;

const formatearFecha = (iso: string) =>
  new Date(iso).toLocaleString('es-BO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

/** Fecha relativa corta: "hace 3 días", "hace 2 meses", "hoy" */
const fechaRelativa = (iso: string): string => {
  const diff = Date.now() - new Date(iso).getTime();
  const minutos = Math.floor(diff / 60_000);
  const horas = Math.floor(minutos / 60);
  const dias = Math.floor(horas / 24);
  const meses = Math.floor(dias / 30);

  if (minutos < 1) return 'recién';
  if (minutos < 60) return `hace ${minutos} min`;
  if (horas < 24) return `hace ${horas} h`;
  if (dias < 30) return `hace ${dias} d`;
  if (meses < 12) return `hace ${meses} mes${meses === 1 ? '' : 'es'}`;
  return formatearFecha(iso);
};

const horaParaInput = (hora: string | undefined): string => {
  if (!hora) return '18:00';
  return hora.substring(0, 5);
};

const minutosDesdeMedianoche = (hhmm: string): number => {
  const [h, m = 0] = hhmm.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
};

/**
 * Convierte "HH:MM" a porcentaje del día (0-100) para posicionar el marcador
 * en la barra de 24 horas.
 */
const horaAPorcentaje = (hhmm: string): number =>
  (minutosDesdeMedianoche(hhmm) / (24 * 60)) * 100;

/** Iniciales del nombre del creador */
const iniciales = (tarifa: TarifaCampo): string => {
  const obj =
    tarifa.creado_por && typeof tarifa.creado_por === 'object'
      ? tarifa.creado_por
      : null;

  if (!obj?.nombre_completo) return '?';

  return obj.nombre_completo
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
};

const nombreCreador = (tarifa: TarifaCampo): string => {
  const creador = tarifa.creado_por;

  // Ojo: typeof null === 'object'. Las tarifas espejadas desde SIREB
  // (creadas por el job de sincronización) no tienen funcionario creador.
  if (creador && typeof creador === 'object') {
    return creador.nombre_completo?.trim() || 'Funcionario';
  }

  return 'Sistema';
};

// ─── Barra visual de 24 horas ──────────────────────────────────────────

function BarraDia24h({ horaCorte }: { horaCorte: string }) {
  const porcentaje = horaAPorcentaje(horaCorte);

  return (
    <div className="space-y-2">
      <div className="relative h-8 rounded-lg overflow-hidden border shadow-inner">
        {/* Mitad ámbar (regular) */}
        <div
          className="absolute inset-y-0 left-0 bg-gradient-to-r from-amber-200 to-amber-300 dark:from-amber-500/40 dark:to-amber-600/40"
          style={{ width: `${porcentaje}%` }}
        />
        {/* Mitad índigo (con iluminación) */}
        <div
          className="absolute inset-y-0 bg-gradient-to-r from-indigo-300 to-indigo-400 dark:from-indigo-600/40 dark:to-indigo-700/40"
          style={{ left: `${porcentaje}%`, right: 0 }}
        />

        {/* Marcador de la hora de corte */}
        <div
          className="absolute top-0 bottom-0 w-0.5 bg-foreground"
          style={{ left: `${porcentaje}%` }}
        >
          <div className="absolute -top-1 -translate-x-1/2 w-2 h-2 rounded-full bg-foreground" />
          <div className="absolute -bottom-1 -translate-x-1/2 w-2 h-2 rounded-full bg-foreground" />
        </div>

        {/* Etiquetas de horas clave */}
        <div className="absolute inset-0 flex items-center px-1 pointer-events-none">
          <span className="text-[10px] font-semibold text-amber-900 dark:text-amber-100">
            00
          </span>
          <span className="ml-auto mr-auto text-[10px] font-semibold text-amber-900 dark:text-amber-100">
            12
          </span>
          <span className="ml-auto text-[10px] font-semibold text-indigo-50 dark:text-indigo-100">
            24
          </span>
        </div>
      </div>

      {/* Leyenda */}
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <div className="flex items-center gap-1.5">
          <Sun className="size-3 text-amber-500" />
          <span>
            <strong className="text-foreground">00:00 → {horaCorte}</strong> · tarifa regular
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <Lightbulb className="size-3 text-indigo-500" />
          <span>
            <strong className="text-foreground">{horaCorte} → 24:00</strong> · con iluminación
          </span>
        </div>
      </div>
    </div>
  );
}

// ─── Columna de tarifa (card con activa, form, historial con deltas) ──

interface PropsColumna {
  tipo: TipoTarifa;
  activa: TarifaCampo | null;
  historial: TarifaCampo[];
}

function ColumnaTarifa({ tipo, activa, historial }: PropsColumna) {
  const esDiurna = tipo === 'diurna';
  const Icono = esDiurna ? Sun : Lightbulb;
  const colorIcono = esDiurna
    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
    : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400';
  const colorPunto = esDiurna ? 'bg-amber-500' : 'bg-indigo-500';

  const labelTarifa = esDiurna ? 'regular' : 'con iluminación';

  const historialFiltrado = historial.filter((t) => t.tipo_tarifa === tipo);

  return (
    <Card className="flex flex-col overflow-hidden">
      <CardHeader className="border-b pb-4">
        <div className="flex items-center gap-3">
          <div className={`rounded-lg p-2.5 ${colorIcono}`}>
            <Icono className="size-5" />
          </div>
          <div className="flex-1">
            <CardTitle className="text-lg">
              {esDiurna ? 'Tarifa regular' : 'Tarifa con iluminación'}
            </CardTitle>
            <CardDescription className="text-xs">
              {esDiurna
                ? 'Bloques que inician antes de la hora de iluminación'
                : 'Bloques que inician desde la hora de iluminación (luces encendidas)'}
            </CardDescription>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-5 flex-1 pt-5">
        {/* ─── Tarifa activa (o empty state) ─── */}
        {activa ? (
          <div
            className={`rounded-xl border-2 p-5 ${
              esDiurna
                ? 'bg-amber-50/50 border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30'
                : 'bg-indigo-50/50 border-indigo-200 dark:bg-indigo-500/10 dark:border-indigo-500/30'
            }`}
          >
            <div className="flex items-center justify-between mb-1">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Tarifa activa
              </p>
              <Badge variant="outline" className="gap-1">
                <CheckCircle2 className="size-3" />
                Vigente
              </Badge>
            </div>
            <p className="text-3xl font-bold tracking-tight tabular-nums">
              {formatearPrecio(activa.precio_por_hora)}
              <span className="text-base font-medium text-muted-foreground ml-1">
                /hora
              </span>
            </p>
            <p className="text-xs text-muted-foreground mt-2">
              Desde {formatearFecha(activa.vigente_desde)}
            </p>
            <div className="flex items-center gap-1.5 mt-1 text-xs text-muted-foreground">
              <Avatar className="size-4">
                <AvatarFallback className="text-[9px] font-semibold">
                  {iniciales(activa)}
                </AvatarFallback>
              </Avatar>
              <span>{nombreCreador(activa)}</span>
            </div>
          </div>
        ) : (
          <div className="rounded-xl border-2 border-dashed border-border bg-muted/30 p-5 text-center">
            <Icono className="size-8 mx-auto mb-2 text-muted-foreground/50" />
            <p className="text-sm font-medium text-muted-foreground">
              SIREB no tiene tarifa {labelTarifa} para este servicio
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              Los bloques de este tipo aparecerán deshabilitados en la web pública.
            </p>
          </div>
        )}

        {/* ─── Solo lectura: el precio lo administra SIREB ─── */}
        <div className="flex items-start gap-2.5 rounded-xl border bg-muted/40 p-3">
          <ShieldCheck className="size-4 shrink-0 mt-0.5 text-primary" />
          <p className="text-xs text-muted-foreground">
            El precio de este tipo lo define{' '}
            <strong className="text-foreground">SIREB</strong> y este sistema
            solo lo refleja. Para cambiarlo hay que hacerlo en el panel de
            recaudaciones; acá podés volver a sincronizar cuando lo actualicen.
          </p>
        </div>

        <Separator />

        {/* ─── Timeline con deltas ─── */}
        <div>
          <div className="flex items-center justify-between mb-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              Historial
            </p>
            <Badge variant="secondary" className="text-[10px]">
              {historialFiltrado.length}{' '}
              {historialFiltrado.length === 1 ? 'registro' : 'registros'}
            </Badge>
          </div>

          {historialFiltrado.length === 0 ? (
            <p className="text-xs text-muted-foreground italic text-center py-4">
              Sin cambios todavía
            </p>
          ) : (
            <ol className="relative space-y-1 pl-4 border-l-2 border-border">
              {historialFiltrado.map((tarifa, i) => {
                const estaActiva = tarifa.vigente_hasta === null;
                const precioFloat = parseFloat(tarifa.precio_por_hora);

                // Delta vs la inmediatamente anterior (siguiente en el array)
                const anterior = historialFiltrado[i + 1];
                let deltaValor: number | null = null;
                if (anterior) {
                  deltaValor = precioFloat - parseFloat(anterior.precio_por_hora);
                }

                return (
                  <li
                    key={tarifa.id}
                    className={`relative pb-3 last:pb-0 ${
                      estaActiva ? '' : 'opacity-75'
                    }`}
                  >
                    {/* Punto en el timeline */}
                    <span
                      className={`absolute -left-[21px] top-1 size-3 rounded-full border-2 border-background ${
                        estaActiva ? colorPunto : 'bg-muted-foreground/30'
                      }`}
                    />

                    <div className="flex items-start justify-between gap-2 mb-1">
                      <div className="flex items-center gap-2">
                        <span className="font-bold tabular-nums">
                          {formatearPrecio(tarifa.precio_por_hora)}
                        </span>
                        {estaActiva && (
                          <Badge
                            variant="outline"
                            className="text-[10px] gap-1 px-1.5 py-0 h-5"
                          >
                            <CheckCircle2 className="size-2.5" />
                            activa
                          </Badge>
                        )}
                      </div>

                      {/* Delta vs anterior */}
                      {deltaValor !== null && deltaValor !== 0 && (
                        <span
                          className={`text-[11px] font-semibold flex items-center gap-0.5 tabular-nums ${
                            deltaValor > 0
                              ? 'text-destructive'
                              : 'text-emerald-600 dark:text-emerald-400'
                          }`}
                        >
                          {deltaValor > 0 ? (
                            <ArrowUp className="size-3" />
                          ) : (
                            <ArrowDown className="size-3" />
                          )}
                          Bs {Math.abs(deltaValor).toFixed(2)}
                        </span>
                      )}
                      {deltaValor === 0 && (
                        <span className="text-[11px] text-muted-foreground flex items-center gap-0.5">
                          <Minus className="size-3" />=
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-muted-foreground flex items-center gap-1.5">
                      <Clock className="size-3" />
                      <span>{fechaRelativa(tarifa.vigente_desde)}</span>
                      <span className="text-muted-foreground/50">·</span>
                      <span className="truncate">{formatearFecha(tarifa.vigente_desde)}</span>
                    </p>
                    <div className="flex items-center gap-1.5 mt-1 text-xs text-muted-foreground">
                      <Avatar className="size-4">
                        <AvatarFallback className="text-[9px] font-semibold">
                          {iniciales(tarifa)}
                        </AvatarFallback>
                      </Avatar>
                      <span className="truncate">{nombreCreador(tarifa)}</span>
                    </div>
                  </li>
                );
              })}
            </ol>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Página principal ──────────────────────────────────────────────────

export default function Tarifas() {
  const { campoId } = useParams<{ campoId: string }>();
  const navigate = useNavigate();

  const [campo, setCampo] = useState<CampoDeportivo | null>(null);
  const [historial, setHistorial] = useState<HistorialTarifas | null>(null);
  const [cargando, setCargando] = useState(true);

  const [horaNoche, setHoraNoche] = useState('18:00');
  const [horaOriginal, setHoraOriginal] = useState('18:00');
  const [guardandoHora, setGuardandoHora] = useState(false);
  const [sincronizando, setSincronizando] = useState(false);

  // Override del breadcrumb: mostrar el nombre del campo en vez del UUID
  useBreadcrumbOverride(
    `/panel/parametricas/campos/${campoId}`,
    campo?.nombre ?? '',
  );

  const cargar = useCallback(async () => {
    if (!campoId) return;
    setCargando(true);
    try {
      const [campoData, tarifasData] = await Promise.all([
        camposService.obtener(campoId),
        tarifasService.historial(campoId),
      ]);
      setCampo(campoData);
      setHistorial(tarifasData);
      const h = horaParaInput(tarifasData.hora_inicio_noche);
      setHoraNoche(h);
      setHoraOriginal(h);
    } catch {
      toast.error('No se pudieron cargar las tarifas del campo');
    } finally {
      setCargando(false);
    }
  }, [campoId]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const guardarHoraNoche = async () => {
    if (!campoId) return;
    if (!/^\d{2}:\d{2}$/.test(horaNoche)) {
      toast.error('Formato de hora inválido');
      return;
    }
    setGuardandoHora(true);
    try {
      await camposService.actualizarHoraNoche(campoId, horaNoche);
      toast.success('Hora de encendido de iluminación actualizada');
      setHoraOriginal(horaNoche);
      cargar();
    } catch {
      toast.error('No se pudo actualizar la hora');
    } finally {
      setGuardandoHora(false);
    }
  };

  const horaModificada = horaNoche !== horaOriginal;

  /**
   * Vuelve a espejar el tarifario de SIREB en tarifas_campo.
   * Es la única forma de que un precio cambie en este sistema.
   */
  const sincronizarDesdeSireb = async () => {
    setSincronizando(true);
    try {
      await catalogoSirebService.sincronizarTarifas();
      toast.success('Tarifas actualizadas desde SIREB');
      await cargar();
    } catch {
      toast.error('No se pudo consultar el tarifario de SIREB');
    } finally {
      setSincronizando(false);
    }
  };

  // Skeleton mientras carga
  if (cargando) {
    return (
      <div className="space-y-6 p-6 max-w-[1400px] mx-auto">
        <div className="h-12 bg-muted animate-pulse rounded-xl" />
        <div className="h-40 bg-muted animate-pulse rounded-xl" />
        <div className="grid lg:grid-cols-2 gap-6">
          <div className="h-96 bg-muted animate-pulse rounded-xl" />
          <div className="h-96 bg-muted animate-pulse rounded-xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6 max-w-[1400px] mx-auto">
      {/* ─── Encabezado ─── */}
      <div className="flex items-start gap-3">
        <Button
          variant="ghost"
          size="icon"
          onClick={() => navigate('/panel/parametricas/campos')}
          className="size-9 rounded-full"
        >
          <ArrowLeft className="size-4" />
        </Button>
        <div className="flex-1 min-w-0">
          <p className="text-xs font-semibold uppercase tracking-widest text-primary mb-1">
            Tarifas del campo
          </p>
          <h1 className="text-2xl md:text-3xl font-bold tracking-tight truncate">
            {campo?.nombre ?? '—'}
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Código{' '}
            <code className="font-mono text-xs bg-muted px-1.5 py-0.5 rounded">
              {campo?.codigo ?? '—'}
            </code>{' '}
            · {campo?.tipo_campo?.nombre ?? ''}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" className="rounded-full" asChild>
            <a
              href={SIREB_PANEL_SERVICIOS_URL}
              target="_blank"
              rel="noopener noreferrer"
            >
              <ExternalLink className="mr-2 size-4" />
              Ver tarifas en SIREB
            </a>
          </Button>
          <Button
            onClick={sincronizarDesdeSireb}
            disabled={sincronizando}
            className="rounded-full"
          >
            <RefreshCw
              className={`mr-2 size-4 ${sincronizando ? 'animate-spin' : ''}`}
            />
            {sincronizando ? 'Sincronizando…' : 'Sincronizar desde SIREB'}
          </Button>
        </div>
      </div>

      {/* ─── Aviso: el precio es de SIREB ─── */}
      <div className="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4">
        <ShieldCheck className="size-5 shrink-0 mt-0.5 text-primary" />
        <div className="text-sm">
          <p className="font-semibold">Los precios los define SIREB</p>
          <p className="text-muted-foreground">
            Este sistema no fija tarifas: refleja el tarifario oficial de
            recaudaciones. Si un precio está mal, hay que corregirlo en el
            panel de SIREB y después sincronizar.
          </p>
        </div>
      </div>

      {/* ─── Hora de corte con barra visual ─── */}
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Clock className="size-4" />
            </div>
            <div>
              <CardTitle className="text-base">
                Hora de encendido de iluminación
              </CardTitle>
              <CardDescription className="text-xs">
                El día se divide en tarifa regular (antes del corte) y con
                iluminación (desde el corte en adelante).
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Barra visual de 24h */}
          <BarraDia24h horaCorte={horaNoche} />

          <div className="flex items-end gap-3 pt-2">
            <div className="space-y-2 w-40">
              <Label htmlFor="hora-noche" className="text-xs font-semibold">
                Hora de corte
              </Label>
              <Input
                id="hora-noche"
                type="time"
                value={horaNoche}
                onChange={(e) => setHoraNoche(e.target.value)}
              />
            </div>
            <Button
              onClick={guardarHoraNoche}
              disabled={guardandoHora || !horaModificada}
              variant={horaModificada ? 'default' : 'outline'}
              className="rounded-full"
            >
              <Save className="size-4 mr-1.5" />
              {guardandoHora ? 'Guardando…' : 'Guardar hora'}
            </Button>
            {horaModificada && (
              <Button
                variant="ghost"
                onClick={() => setHoraNoche(horaOriginal)}
                disabled={guardandoHora}
              >
                Descartar
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* ─── Dos columnas: diurna y nocturna ─── */}
      {historial ? (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <ColumnaTarifa
            tipo="diurna"
            activa={historial.activas.diurna}
            historial={historial.historial}
          />
          <ColumnaTarifa
            tipo="nocturna"
            activa={historial.activas.nocturna}
            historial={historial.historial}
          />
        </div>
      ) : null}
    </div>
  );
}
