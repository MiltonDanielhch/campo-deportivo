import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  ArrowLeft,
  MapPin,
  Lightbulb,
  Sun,
  ImageIcon,
  Calendar,
  Clock,
  Navigation,
  Info,
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
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
  imagen_url: string | null;
  hora_inicio_noche: string;
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
    estado: 'libre' | 'ocupada' | 'bloqueada_temporal';
  }>;
}

const DIAS_SEMANA = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const formatHora = (hhmmss: string): string => hhmmss.substring(0, 5);

export default function CampoDetalle() {
  const { id } = useParams<{ id: string }>();
  const [campo, setCampo] = useState<Campo | null>(null);
  const [disponibilidad, setDisponibilidad] = useState<Disponibilidad | null>(null);
  const [fecha, setFecha] = useState<string>(
    () => new Date().toISOString().split('T')[0],
  );
  const [cargandoCampo, setCargandoCampo] = useState(true);
  const [cargandoDisponibilidad, setCargandoDisponibilidad] = useState(true);

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

  // ─── Skeleton ───
  if (cargandoCampo) {
    return (
      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <div className="animate-pulse space-y-6">
          <div className="h-6 bg-slate-200 rounded w-40" />
          <div className="aspect-[21/9] bg-slate-200 rounded-2xl" />
          <div className="grid md:grid-cols-2 gap-6">
            <div className="h-60 bg-slate-200 rounded-2xl" />
            <div className="h-60 bg-slate-200 rounded-2xl" />
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

  return (
    <>
      <Helmet>
        <title>{campo.nombre} - Canchas Deportivas GAD Beni</title>
        <meta
          name="description"
          content={`Reserva ${campo.nombre} (${campo.tipo_campo.nombre}) en ${campo.direccion}. Disponible para reserva online.`}
        />
        <meta property="og:title" content={campo.nombre} />
        <meta
          property="og:description"
          content={`${campo.tipo_campo.nombre} en ${campo.direccion}`}
        />
      </Helmet>

      <div className="min-h-screen bg-slate-50 pb-28">
        {/* ─── HERO con foto del campo ─── */}
        <section className="relative h-[260px] md:h-[360px] overflow-hidden">
          {/* Imagen de fondo */}
          {campo.imagen_url ? (
            <img
              src={campo.imagen_url}
              alt={campo.nombre}
              className="absolute inset-0 w-full h-full object-cover"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-teal-600 via-teal-700 to-emerald-700">
              <div
                className="absolute inset-0 opacity-[0.08]"
                style={{
                  backgroundImage:
                    'repeating-linear-gradient(45deg, white 0, white 1px, transparent 1px, transparent 20px)',
                }}
              />
              {!campo.imagen_url && (
                <div className="absolute inset-0 flex items-center justify-center text-white/40">
                  <ImageIcon className="w-24 h-24" />
                </div>
              )}
            </div>
          )}

          {/* Overlay gradiente para legibilidad */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10" />

          {/* Volver (flotando sobre el hero) */}
          <div className="relative container mx-auto px-4 max-w-6xl pt-6">
            <Button
              asChild
              variant="outline"
              size="sm"
              className="bg-white/10 backdrop-blur border-white/30 text-white hover:bg-white/20 hover:text-white rounded-full"
            >
              <Link to="/campos" className="flex items-center gap-1.5">
                <ArrowLeft className="w-4 h-4" />
                Volver
              </Link>
            </Button>
          </div>

          {/* Contenido sobre el hero */}
          <div className="absolute bottom-0 left-0 right-0 container mx-auto px-4 pb-6 md:pb-8 max-w-6xl text-white">
            <div className="flex items-start justify-between gap-4 flex-wrap">
              <div className="min-w-0">
                <p className="text-xs md:text-sm font-semibold uppercase tracking-widest text-teal-200 mb-2">
                  {campo.tipo_campo.nombre}
                </p>
                <h1 className="text-3xl md:text-5xl font-bold tracking-tight mb-2">
                  {campo.nombre}
                </h1>
                <p className="flex items-center gap-2 text-sm md:text-base text-white/90">
                  <MapPin className="w-4 h-4 flex-shrink-0" />
                  <span className="truncate">{campo.direccion}</span>
                </p>
              </div>
              <Badge
                variant={esMantenimiento ? 'secondary' : 'default'}
                className={
                  esMantenimiento
                    ? 'bg-orange-500 hover:bg-orange-600 text-white'
                    : 'bg-emerald-500 hover:bg-emerald-600 text-white'
                }
              >
                {esMantenimiento ? 'En mantenimiento' : campo.estado}
              </Badge>
            </div>
          </div>
        </section>

        {/* ─── Contenido principal ─── */}
        <div className="container mx-auto px-4 py-8 md:py-10 max-w-6xl">
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
              {/* ─── Grid de 2 columnas: info + tarifas ─── */}
              <div className="grid md:grid-cols-5 gap-6 mb-10">
                {/* IZQ: Horarios + dirección */}
                <Card className="md:col-span-3 border-slate-200 shadow-sm">
                  <CardContent className="p-6">
                    {/* Horarios de atención */}
                    <div className="mb-6">
                      <div className="flex items-center gap-2 mb-3">
                        <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                          <Clock className="w-4 h-4 text-teal-600" />
                        </div>
                        <h2 className="text-lg font-bold tracking-tight">
                          Horarios de atención
                        </h2>
                      </div>
                      {horarios.length > 0 ? (
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                          {horarios
                            .slice()
                            .sort((a, b) => a.dia_semana - b.dia_semana)
                            .map((h) => (
                              <div
                                key={h.dia_semana}
                                className="bg-slate-50 rounded-lg px-3 py-2.5 border border-slate-100"
                              >
                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                  {DIAS_SEMANA[h.dia_semana]}
                                </p>
                                <p className="text-sm font-bold text-slate-900 mt-0.5">
                                  {h.hora_apertura.slice(0, 5)}–
                                  {h.hora_cierre.slice(0, 5)}
                                </p>
                              </div>
                            ))}
                        </div>
                      ) : (
                        <p className="text-sm text-slate-500 italic">
                          Sin horarios definidos.
                        </p>
                      )}
                    </div>

                    {/* Dirección con link a Google Maps */}
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                          <Navigation className="w-4 h-4 text-teal-600" />
                        </div>
                        <h2 className="text-lg font-bold tracking-tight">
                          Cómo llegar
                        </h2>
                      </div>
                      <p className="text-sm text-slate-600 mb-3">
                        {campo.direccion}
                      </p>
                      <a
                        href={`https://www.google.com/maps?q=${campo.latitud},${campo.longitud}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-600 hover:text-teal-700"
                      >
                        Abrir en Google Maps
                        <ArrowLeft className="w-3.5 h-3.5 rotate-180" />
                      </a>
                    </div>
                  </CardContent>
                </Card>

                {/* DER: Tarifas */}
                <Card className="md:col-span-2 border-slate-200 shadow-sm">
                  <CardContent className="p-6">
                    <div className="flex items-center gap-2 mb-4">
                      <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                        <Calendar className="w-4 h-4 text-teal-600" />
                      </div>
                      <h2 className="text-lg font-bold tracking-tight">
                        Tarifas por hora
                      </h2>
                    </div>

                    {tieneAlgunaTarifa ? (
                      <div className="space-y-3">
                        {/* Tarifa regular */}
                        <div
                          className={`rounded-xl border p-4 ${
                            diurna
                              ? 'border-amber-200 bg-amber-50'
                              : 'border-slate-200 bg-slate-50'
                          }`}
                        >
                          <div className="flex items-center justify-between mb-1">
                            <div className="flex items-center gap-2">
                              <Sun
                                className={`w-4 h-4 ${
                                  diurna ? 'text-amber-500' : 'text-slate-400'
                                }`}
                              />
                              <span className="text-xs font-semibold uppercase tracking-wide">
                                Regular
                              </span>
                            </div>
                            {diurna && (
                              <span className="text-xs text-amber-700">
                                Antes de {formatHora(campo.hora_inicio_noche)}
                              </span>
                            )}
                          </div>
                          {diurna ? (
                            <p className="text-2xl font-bold text-amber-900">
                              Bs {diurna.precio_por_hora.toFixed(2)}
                              <span className="text-sm font-medium text-amber-700 ml-1">
                                /hora
                              </span>
                            </p>
                          ) : (
                            <p className="text-sm text-slate-500 italic">
                              Sin tarifa definida
                            </p>
                          )}
                        </div>

                        {/* Tarifa con iluminación */}
                        <div
                          className={`rounded-xl border p-4 ${
                            nocturna
                              ? 'border-indigo-200 bg-indigo-50'
                              : 'border-slate-200 bg-slate-50'
                          }`}
                        >
                          <div className="flex items-center justify-between mb-1">
                            <div className="flex items-center gap-2">
                              <Lightbulb
                                className={`w-4 h-4 ${
                                  nocturna
                                    ? 'text-indigo-500'
                                    : 'text-slate-400'
                                }`}
                              />
                              <span className="text-xs font-semibold uppercase tracking-wide">
                                Con iluminación
                              </span>
                            </div>
                            {nocturna && (
                              <span className="text-xs text-indigo-700">
                                Desde {formatHora(campo.hora_inicio_noche)}
                              </span>
                            )}
                          </div>
                          {nocturna ? (
                            <p className="text-2xl font-bold text-indigo-900">
                              Bs {nocturna.precio_por_hora.toFixed(2)}
                              <span className="text-sm font-medium text-indigo-700 ml-1">
                                /hora
                              </span>
                            </p>
                          ) : (
                            <p className="text-sm text-slate-500 italic">
                              Sin tarifa definida
                            </p>
                          )}
                        </div>
                      </div>
                    ) : (
                      <p className="text-sm text-slate-500 italic">
                        Este campo aún no tiene tarifas definidas.
                      </p>
                    )}
                  </CardContent>
                </Card>
              </div>

              {/* ─── Reservá tu franja ─── */}
              <section>
                <div className="mb-6">
                  <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-2">
                    Reservá tu franja
                  </p>
                  <h2 className="text-2xl md:text-3xl font-bold tracking-tight">
                    Elegí fecha y horario
                  </h2>
                </div>

                <SelectorFechas
                  fechaSeleccionada={fecha}
                  onFechaChange={setFecha}
                />

                {cargandoDisponibilidad ? (
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                    {Array.from({ length: 8 }).map((_, i) => (
                      <div
                        key={i}
                        className="h-16 bg-slate-200 rounded-lg animate-pulse"
                      />
                    ))}
                  </div>
                ) : disponibilidad && disponibilidad.abierto ? (
                  <>
                    {/* Leyenda de colores */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mb-4 text-xs">
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
                    </div>

                    <GrillaDisponibilidad
                      bloques={disponibilidad.bloques}
                      campoId={campo.id}
                      campoNombre={campo.nombre}
                      fecha={fecha}
                      tarifas={campo.tarifas}
                      horaInicioNoche={campo.hora_inicio_noche}
                    />

                    {/* Nota integrada (reemplaza la caja azul genérica) */}
                    <div className="mt-6 flex gap-3 p-4 bg-teal-50/50 border-l-4 border-teal-500 rounded-r-lg">
                      <Info className="w-5 h-5 text-teal-600 flex-shrink-0 mt-0.5" />
                      <p className="text-sm text-slate-700 leading-relaxed">
                        <span className="font-semibold">Tip:</span> podés
                        seleccionar múltiples franjas a la vez. El precio se
                        ajusta automáticamente según el horario (regular o con
                        iluminación). La grilla se actualiza sola cada 45
                        segundos.
                      </p>
                    </div>
                  </>
                ) : (
                  <div className="text-center py-16 bg-white rounded-2xl border border-slate-200">
                    <div className="w-14 h-14 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                      <Calendar className="w-6 h-6 text-slate-400" />
                    </div>
                    <h3 className="text-lg font-bold mb-1">
                      Campo cerrado este día
                    </h3>
                    <p className="text-sm text-slate-500">
                      No hay horarios de atención para la fecha seleccionada.
                      Probá otro día.
                    </p>
                  </div>
                )}
              </section>
            </>
          )}
        </div>

        <BarraCarrito />
      </div>
    </>
  );
}
