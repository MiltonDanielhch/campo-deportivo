import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  ArrowLeft,
  MapPin,
  Lightbulb,
  Sun,
  Calendar,
  Clock,
  Navigation,
  Info,
  ExternalLink,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import SelectorFechas from '@/components/campo/SelectorFechas';
import GrillaDisponibilidad from '@/components/campo/GrillaDisponibilidad';
import BarraCarrito from '@/components/campo/BarraCarrito';

interface InfoTarifa {
  precio_por_hora: number;
  vigente_desde: string;
}

interface HorarioAtencion {
  dia_semana: number;
  hora_apertura: string;
  hora_cierre: string;
}

interface Campo {
  id: string;
  codigo?: string;
  nombre: string;
  nombre_local?: string;
  tipo_campo?: { nombre: string } | null;
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
  imagen_url: string | null;
  hora_inicio_noche: string;
  servicio_sireb_id?: string | null;
  servicio_sireb_codigo?: string | null;
  fuente_precios?: string;
  tarifas: {
    diurna: InfoTarifa | null;
    nocturna: InfoTarifa | null;
  };
  horarios_atencion?: HorarioAtencion[];
}

interface Disponibilidad {
  abierto: boolean;
  bloques: Array<{
    hora_inicio: string;
    hora_fin: string;
    estado: 'libre' | 'ocupada' | 'bloqueada_temporal' | 'pasada';
  }>;
}

const DIAS_SEMANA = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const formatHora = (hhmmss: string): string => hhmmss.substring(0, 5);

/** Normaliza URLs locales al proxy CORS del backend. */
const normalizarUrlImagen = (url: string | null): string | null => {
  if (!url) return null;
  if (url.startsWith('http')) return url;
  if (url.startsWith('/storage/')) {
    return url.replace('/storage/', '/api/v1/public/storage/');
  }
  return url;
};

export default function CampoDetalle() {
  const { id } = useParams<{ id: string }>();
  const [campo, setCampo] = useState<Campo | null>(null);
  const [disponibilidad, setDisponibilidad] = useState<Disponibilidad | null>(null);
  const [fecha, setFecha] = useState<string>(() => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  });
  const [cargandoCampo, setCargandoCampo] = useState(true);
  const [cargandoDisponibilidad, setCargandoDisponibilidad] = useState(true);
  const [imagenRota, setImagenRota] = useState(false);

  useEffect(() => {
    if (!id) return;
    api
      .get(`/public/campos/${id}`)
      .then((res) => setCampo(res.data.data))
      .catch((err) => console.error('Error al cargar campo:', err))
      .finally(() => setCargandoCampo(false));
  }, [id]);

  useEffect(() => {
    if (!id || !campo) return;
    setCargandoDisponibilidad(true);
    api
      .get(`/public/campos/${id}/disponibilidad`, { params: { fecha } })
      .then((res) => setDisponibilidad(res.data.data))
      .catch((err) => console.error('Error al cargar disponibilidad:', err))
      .finally(() => setCargandoDisponibilidad(false));
  }, [id, campo, fecha]);

  useEffect(() => {
    if (!id || !campo) return;
    const interval = setInterval(() => {
      api
        .get(`/public/campos/${id}/disponibilidad`, { params: { fecha } })
        .then((res) => setDisponibilidad(res.data.data))
        .catch(() => {
          /* silencioso en refresco */
        });
    }, 45000);
    return () => clearInterval(interval);
  }, [id, campo, fecha]);

  // ─── Skeleton ──
  if (cargandoCampo) {
    return (
      <div className="container mx-auto px-4 py-6 max-w-6xl">
        <div className="animate-pulse space-y-4">
          <div className="h-10 bg-slate-200 rounded-full w-32" />
          <div className="aspect-[16/7] bg-slate-200 rounded-2xl" />
          <div className="h-16 bg-slate-200 rounded-xl" />
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="h-20 bg-slate-200 rounded-xl" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  // ─── 404 ───
  if (!campo) {
    return (
      <div className="container mx-auto px-4 py-16 text-center max-w-md">
        <div className="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-6">
          <Info className="w-10 h-10 text-slate-400" />
        </div>
        <h1 className="text-2xl font-bold mb-3 tracking-tight">
          Campo no encontrado
        </h1>
        <p className="text-slate-500 mb-6">
          El campo que buscás no existe o fue eliminado.
        </p>
        <Button asChild className="rounded-full">
          <Link to="/campos">Volver al listado</Link>
        </Button>
      </div>
    );
  }

  const esMantenimiento = campo.estado === 'mantenimiento';
  const { diurna, nocturna } = campo.tarifas;
  const tieneAlgunaTarifa = diurna !== null || nocturna !== null;
  const horarios = campo.horarios_atencion ?? [];
  const imagenUrl = normalizarUrlImagen(campo.imagen_url);

  return (
    <>
      <Helmet>
        <title>{campo.nombre} - Canchas Deportivas GAD Beni</title>
        <meta
          name="description"
          content={`Reserva ${campo.nombre} (${campo.tipo_campo?.nombre ?? 'campo deportivo'}) en ${campo.direccion}.`}
        />
      </Helmet>

      <div className="min-h-screen bg-slate-50 pb-28">
        {/* ─── HERO COMPACTO ─── */}
        <section className="relative h-[180px] sm:h-[210px] md:h-[240px] overflow-hidden">
          {imagenUrl && !imagenRota ? (
            <img
              src={imagenUrl}
              alt={campo.nombre}
              className="absolute inset-0 w-full h-full object-cover"
              onError={() => setImagenRota(true)}
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-teal-600 via-teal-700 to-emerald-800">
              <div
                className="absolute inset-0 opacity-[0.07]"
                style={{
                  backgroundImage:
                    'repeating-linear-gradient(45deg, white 0, white 1px, transparent 1px, transparent 20px)',
                }}
              />
              <div className="absolute inset-0 flex items-center justify-center opacity-25">
                <div className="relative w-40 h-24 md:w-56 md:h-32 rounded-md border-2 border-white">
                  <div className="absolute inset-x-0 top-1/2 h-0.5 bg-white" />
                  <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-10 md:w-14 md:h-14 rounded-full border-2 border-white" />
                  <div className="absolute left-0 top-1/2 -translate-y-1/2 w-6 h-12 md:w-8 md:h-16 border-2 border-l-0 border-white" />
                  <div className="absolute right-0 top-1/2 -translate-y-1/2 w-6 h-12 md:w-8 md:h-16 border-2 border-r-0 border-white" />
                </div>
              </div>
            </div>
          )}

          <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent" />

          <div className="absolute top-3 left-0 right-0 z-10">
            <div className="container mx-auto px-4 max-w-6xl">
              <Button
                asChild
                variant="outline"
                size="sm"
                className="bg-white/10 backdrop-blur border-white/25 text-white hover:bg-white/20 hover:text-white rounded-full h-8"
              >
                <Link to="/campos" className="flex items-center gap-1.5">
                  <ArrowLeft className="w-3.5 h-3.5" />
                  Volver
                </Link>
              </Button>
            </div>
          </div>

          <div className="absolute bottom-0 left-0 right-0 container mx-auto px-4 pb-4 md:pb-5 max-w-6xl text-white">
            <div className="flex items-center gap-2 mb-1.5 flex-wrap">
              <p className="text-[10px] md:text-xs font-semibold uppercase tracking-widest text-teal-200">
                {campo.tipo_campo?.nombre ?? 'Campo deportivo'}
              </p>
              <Badge
                variant={esMantenimiento ? 'secondary' : 'default'}
                className={
                  esMantenimiento
                    ? 'bg-orange-500 hover:bg-orange-600 text-white'
                    : 'bg-emerald-500 hover:bg-emerald-600 text-white'
                }
              >
                {esMantenimiento ? 'Mantenimiento' : campo.estado}
              </Badge>
            </div>
            <h1 className="text-2xl sm:text-3xl md:text-4xl font-bold tracking-tight leading-tight mb-1">
              {campo.nombre}
            </h1>
            <p className="flex items-center gap-1.5 text-xs md:text-sm text-white/90">
              <MapPin className="w-3.5 h-3.5 flex-shrink-0" />
              <span className="truncate">{campo.direccion}</span>
              {campo.servicio_sireb_codigo && (
                <span className="hidden sm:inline font-mono text-[10px] text-white/60 ml-2">
                  {campo.servicio_sireb_codigo}
                </span>
              )}
            </p>
          </div>
        </section>

        {/* ─── Contenido principal (UNA sola columna, sin sidebar) ─── */}
        <div className="container mx-auto px-4 pt-5 md:pt-8 pb-8 md:pb-10 max-w-6xl">
          {esMantenimiento ? (
            <div className="bg-orange-50 border border-orange-200 rounded-2xl p-8 text-center">
              <div className="w-16 h-16 mx-auto rounded-full bg-orange-100 flex items-center justify-center mb-4">
                <Info className="w-8 h-8 text-orange-500" />
              </div>
              <h2 className="text-xl font-bold text-orange-900 mb-2">
                Campo en mantenimiento
              </h2>
              <p className="text-orange-700 max-w-md mx-auto">
                Este campo no está disponible para reserva temporalmente.
                Volvé a consultar en unos días.
              </p>
            </div>
          ) : (
            <>
              <div className="mb-4">
                <h2 className="text-xl md:text-2xl font-bold tracking-tight text-slate-900">
                  Elegí fecha y horario
                </h2>
                <p className="text-sm text-slate-500 mt-1">
                  Tocá una o varias franjas para reservarlas.
                </p>
              </div>

              <SelectorFechas
                fechaSeleccionada={fecha}
                onFechaChange={setFecha}
              />

              {/* ─── Chips de tarifas: precios visibles mientras elegís ─── */}
              {tieneAlgunaTarifa && (
                <div className="flex flex-wrap items-center gap-2 mb-4">
                  {diurna && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-900">
                      <Sun className="w-3.5 h-3.5 text-amber-500" />
                      Regular Bs {diurna.precio_por_hora.toFixed(0)}
                    </span>
                  )}
                  {nocturna && (
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-900">
                      <Lightbulb className="w-3.5 h-3.5 text-indigo-500" />
                      Con luces Bs {nocturna.precio_por_hora.toFixed(0)}
                    </span>
                  )}
                </div>
              )}

              {cargandoDisponibilidad ? (
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                  {Array.from({ length: 8 }).map((_, i) => (
                    <div
                      key={i}
                      className="h-20 bg-slate-200 rounded-xl animate-pulse"
                    />
                  ))}
                </div>
              ) : disponibilidad && disponibilidad.abierto ? (
                <>
                  {/* Leyenda */}
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 mb-3 text-xs">
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-amber-50 border border-amber-300" />
                      <span className="text-slate-600">Regular</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-indigo-50 border border-indigo-300" />
                      <span className="text-slate-600">Con luces</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-teal-600" />
                      <span className="text-slate-600">Seleccionada</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-red-100 border border-red-300" />
                      <span className="text-slate-600">Ocupada</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-orange-100 border border-orange-300" />
                      <span className="text-slate-600">En cobro</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-sm bg-slate-200 border border-slate-300" />
                      <span className="text-slate-600">Pasada</span>
                    </div>
                  </div>

                  <GrillaDisponibilidad
                    bloques={disponibilidad.bloques}
                    campoId={campo.id}
                    campoNombre={campo.nombre}
                    fecha={fecha}
                    tarifas={campo.tarifas}
                    horaInicioNoche={campo.hora_inicio_noche}
                  />

                  <div className="mt-5 flex gap-3 p-3.5 bg-teal-50/60 border-l-4 border-teal-500 rounded-r-lg">
                    <Info className="w-4 h-4 text-teal-600 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-slate-700">
                      <span className="font-semibold">Tip:</span> podés
                      seleccionar múltiples franjas. El precio se ajusta
                      automáticamente (regular o con iluminación).
                    </p>
                  </div>
                </>
              ) : (
                <div className="text-center py-12 bg-white rounded-2xl border border-slate-200">
                  <div className="w-14 h-14 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-3">
                    <Calendar className="w-6 h-6 text-slate-400" />
                  </div>
                  <h3 className="text-lg font-bold mb-1">
                    Campo cerrado este día
                  </h3>
                  <p className="text-sm text-slate-500 max-w-sm mx-auto">
                    No hay horarios de atención para la fecha seleccionada.
                    Probá otro día.
                  </p>
                </div>
              )}

              {/* ─── Información del campo: fila de 3 cards a lo ancho ─── */}
              <section className="mt-10">
                <h3 className="text-lg font-bold tracking-tight text-slate-900 mb-4">
                  Información del campo
                </h3>
                <div className="grid md:grid-cols-3 gap-4">
                  <TarjetaTarifas
                    diurna={diurna}
                    nocturna={nocturna}
                    horaInicioNoche={campo.hora_inicio_noche}
                    tieneAlgunaTarifa={tieneAlgunaTarifa}
                  />
                  <TarjetaHorarios horarios={horarios} />
                  <TarjetaComoLlegar
                    direccion={campo.direccion}
                    latitud={campo.latitud}
                    longitud={campo.longitud}
                  />
                </div>
              </section>
            </>
          )}
        </div>

        <BarraCarrito />
      </div>
    </>
  );
}

/* ═══════════ Cards de información (fila inferior, sin sidebar) ═══════════ */

function TarjetaTarifas({
  diurna,
  nocturna,
  horaInicioNoche,
  tieneAlgunaTarifa,
}: {
  diurna: InfoTarifa | null;
  nocturna: InfoTarifa | null;
  horaInicioNoche: string;
  tieneAlgunaTarifa: boolean;
}) {
  return (
    <Card className="border-slate-200 shadow-sm">
      <CardContent className="p-4">
        <div className="flex items-center gap-2 mb-3">
          <div className="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center">
            <Calendar className="w-3.5 h-3.5 text-teal-600" />
          </div>
          <h4 className="font-bold tracking-tight text-slate-900">
            Tarifas por hora
          </h4>
        </div>

        {tieneAlgunaTarifa ? (
          <div className="space-y-2">
            {diurna && (
              <div className="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-3">
                <div className="flex items-center gap-2">
                  <Sun className="w-4 h-4 text-amber-500" />
                  <div>
                    <p className="text-xs font-semibold text-amber-900">Regular</p>
                    <p className="text-[10px] text-amber-700">
                      Antes de {formatHora(horaInicioNoche)}
                    </p>
                  </div>
                </div>
                <p className="text-lg font-bold text-amber-900">
                  Bs {diurna.precio_por_hora.toFixed(0)}
                </p>
              </div>
            )}
            {nocturna && (
              <div className="flex items-center justify-between rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                <div className="flex items-center gap-2">
                  <Lightbulb className="w-4 h-4 text-indigo-500" />
                  <div>
                    <p className="text-xs font-semibold text-indigo-900">
                      Con iluminación
                    </p>
                    <p className="text-[10px] text-indigo-700">
                      Desde {formatHora(horaInicioNoche)}
                    </p>
                  </div>
                </div>
                <p className="text-lg font-bold text-indigo-900">
                  Bs {nocturna.precio_por_hora.toFixed(0)}
                </p>
              </div>
            )}
          </div>
        ) : (
          <p className="text-sm text-slate-500 italic">Sin tarifas definidas.</p>
        )}
      </CardContent>
    </Card>
  );
}

function TarjetaHorarios({ horarios }: { horarios: HorarioAtencion[] }) {
  return (
    <Card className="border-slate-200 shadow-sm">
      <CardContent className="p-4">
        <div className="flex items-center gap-2 mb-3">
          <div className="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center">
            <Clock className="w-3.5 h-3.5 text-teal-600" />
          </div>
          <h4 className="font-bold tracking-tight text-slate-900">
            Horarios de atención
          </h4>
        </div>
        {horarios.length > 0 ? (
          <div className="space-y-1.5">
            {horarios
              .slice()
              .sort((a, b) => a.dia_semana - b.dia_semana)
              .map((h) => (
                <div
                  key={h.dia_semana}
                  className="flex items-center justify-between text-sm py-1.5 border-b border-slate-100 last:border-0"
                >
                  <span className="font-medium text-slate-700 w-10">
                    {DIAS_SEMANA[h.dia_semana]}
                  </span>
                  <span className="font-mono text-slate-900">
                    {h.hora_apertura.slice(0, 5)}–{h.hora_cierre.slice(0, 5)}
                  </span>
                </div>
              ))}
          </div>
        ) : (
          <p className="text-sm text-slate-500 italic">Sin horarios definidos.</p>
        )}
      </CardContent>
    </Card>
  );
}

function TarjetaComoLlegar({
  direccion,
  latitud,
  longitud,
}: {
  direccion: string;
  latitud: number;
  longitud: number;
}) {
  return (
    <Card className="border-slate-200 shadow-sm">
      <CardContent className="p-4">
        <div className="flex items-center gap-2 mb-3">
          <div className="w-7 h-7 rounded-lg bg-teal-50 flex items-center justify-center">
            <Navigation className="w-3.5 h-3.5 text-teal-600" />
          </div>
          <h4 className="font-bold tracking-tight text-slate-900">Cómo llegar</h4>
        </div>
        <p className="text-sm text-slate-600 mb-3 leading-snug">{direccion}</p>
        <a
          href={`https://www.google.com/maps?q=${latitud},${longitud}`}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-600 hover:text-teal-700"
        >
          Abrir en Google Maps
          <ExternalLink className="w-3.5 h-3.5" />
        </a>
      </CardContent>
    </Card>
  );
}
