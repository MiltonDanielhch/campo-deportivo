import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Sun, Lightbulb, ArrowRight, BadgeCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import type { CampoPublico, CamposResponse } from '@/types/campo';

/** Normaliza URLs locales al proxy CORS del backend. */
const normalizarUrlImagen = (url: string | null | undefined): string | null => {
  if (!url) return null;
  if (url.startsWith('http')) return url;
  if (url.startsWith('/storage/')) {
    return url.replace('/storage/', '/api/v1/public/storage/');
  }
  return url;
};

/** Fallback visual: cancha dibujada con líneas (igual que en CampoDetalle). */
function CampoSinFoto() {
  return (
    <div className="w-full h-full bg-gradient-to-br from-teal-600 to-emerald-700 flex items-center justify-center">
      <div className="relative w-28 h-16 md:w-36 md:h-20 rounded-sm border-2 border-white/70 opacity-80">
        <div className="absolute inset-x-0 top-1/2 h-0.5 bg-white/70" />
        <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-7 h-7 md:w-10 md:h-10 rounded-full border-2 border-white/70" />
        <div className="absolute left-0 top-1/2 -translate-y-1/2 w-4 h-8 md:w-6 md:h-12 border-2 border-l-0 border-white/70" />
        <div className="absolute right-0 top-1/2 -translate-y-1/2 w-4 h-8 md:w-6 md:h-12 border-2 border-r-0 border-white/70" />
      </div>
    </div>
  );
}

function rangoPreciosOficial(campo: CampoPublico): string | null {
  const sireb = campo.sireb;
  if (!sireb || campo.fuente_precios !== 'SIREB') return null;

  if (sireb.precio_min != null && sireb.precio_max != null) {
    return sireb.precio_min === sireb.precio_max
      ? `Bs ${sireb.precio_min.toFixed(0)}`
      : `Bs ${sireb.precio_min.toFixed(0)} – ${sireb.precio_max.toFixed(0)}`;
  }

  return null;
}

/**
 * Score para ordenar destacados: primero los campos más "vendibles"
 * (foto + precio + activo), luego el resto.
 */
function scoreDestacado(campo: CampoPublico): number {
  let score = 0;
  if (campo.estado === 'activo') score += 4;
  if (normalizarUrlImagen(campo.imagen_url)) score += 2;
  if (rangoPreciosOficial(campo) || campo.tarifas?.diurna || campo.tarifas?.nocturna) score += 1;
  if (campo.reservable_online) score += 1;
  return score;
}

export default function CamposDestacados() {
  const [campos, setCampos] = useState<CampoPublico[]>([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api
      .get<CamposResponse>('/public/campos')
      .then((res) => {
        // Priorizar campos con foto + precio + activo, tomar los 3 mejores
        const ordenados = [...res.data.data]
          .sort((a, b) => scoreDestacado(b) - scoreDestacado(a))
          .slice(0, 3);
        setCampos(ordenados);
      })
      .catch((err) => console.error('Error al cargar campos destacados:', err))
      .finally(() => setCargando(false));
  }, []);

  return (
    <section className="py-20 md:py-28 bg-slate-50">
      <div className="container mx-auto px-4 max-w-6xl">
        {/* Encabezado — desktop: link inline | mobile: botón full */}
        <div className="flex flex-col md:flex-row md:items-end md:justify-between mb-12 gap-4">
          <div>
            <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-3">
              Disponibles hoy
            </p>
            <h2 className="text-3xl md:text-5xl font-bold tracking-tight">
              Campos destacados
            </h2>
            <p className="text-slate-600 text-lg mt-3 max-w-xl">
              Una selección de nuestras canchas mejor valoradas, listas para
              reservar.
            </p>
          </div>

          {/* Mobile: botón full-width debajo del título */}
          <Button asChild variant="outline" className="rounded-full md:hidden">
            <Link to="/campos" className="flex items-center justify-center gap-2">
              Ver todas
              <ArrowRight className="w-4 h-4" />
            </Link>
          </Button>

          {/* Desktop: link inline al lado del título (ahorra una fila) */}
          <Link
            to="/campos"
            className="hidden md:inline-flex items-center gap-2 text-teal-700 hover:text-teal-800 font-semibold whitespace-nowrap"
          >
            Ver todas
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        {/* Grid de cards */}
        {cargando ? (
          <div className="grid md:grid-cols-3 gap-6">
            {[1, 2, 3].map((i) => (
              <div
                key={i}
                className="bg-white rounded-2xl overflow-hidden border border-slate-100 animate-pulse"
              >
                <div className="aspect-video bg-slate-200" />
                <div className="p-5 space-y-3">
                  <div className="h-5 bg-slate-200 rounded w-3/4" />
                  <div className="h-4 bg-slate-200 rounded w-1/2" />
                  <div className="h-4 bg-slate-200 rounded w-full" />
                </div>
              </div>
            ))}
          </div>
        ) : campos.length === 0 ? (
          <p className="text-center text-slate-500 py-12">
            No hay canchas destacadas por el momento.
          </p>
        ) : (
          <div className="grid md:grid-cols-3 gap-6">
            {campos.map((campo) => {
              const imagenUrl = normalizarUrlImagen(campo.imagen_url);
              return (
                <Link
                  key={campo.id}
                  to={`/campos/${campo.id}`}
                  className="group bg-white rounded-2xl overflow-hidden border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                >
                  {/* Imagen 16:9 */}
                  <div className="relative aspect-video bg-slate-100 overflow-hidden">
                    {imagenUrl ? (
                      <img
                        src={imagenUrl}
                        alt={campo.nombre}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        loading="lazy"
                        onError={(e) => {
                          // Si la imagen falla, reemplaza con el fallback dibujado
                          const target = e.currentTarget;
                          target.style.display = 'none';
                          const parent = target.parentElement;
                          if (parent && !parent.querySelector('.campo-fallback')) {
                            const fb = document.createElement('div');
                            fb.className = 'campo-fallback absolute inset-0';
                            parent.appendChild(fb);
                          }
                        }}
                      />
                    ) : (
                      <CampoSinFoto />
                    )}
                    {/* Fallback por si la imagen falla al cargar */}
                    {imagenUrl && (
                      <div className="absolute inset-0 pointer-events-none hidden has-[img[style*='display: none']]:block">
                        <CampoSinFoto />
                      </div>
                    )}
                    <div className="absolute top-3 right-3 flex flex-col items-end gap-2">
                      {campo.fuente_precios === 'SIREB' && campo.sireb && (
                        <Badge className="gap-1 bg-white/95 text-teal-700 hover:bg-white shadow-sm backdrop-blur">
                          <BadgeCheck className="w-3 h-3" />
                          Oficial
                        </Badge>
                      )}
                      <Badge
                        variant={campo.estado === 'activo' ? 'default' : 'secondary'}
                        className={
                          campo.estado === 'activo'
                            ? 'bg-emerald-500 hover:bg-emerald-600'
                            : ''
                        }
                      >
                        {campo.estado}
                      </Badge>
                    </div>
                  </div>

                  {/* Contenido */}
                  <div className="p-5">
                    <div className="flex items-center justify-between gap-2 mb-1">
                      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        {campo.tipo_campo?.nombre ?? 'Campo deportivo'}
                      </p>
                      {campo.servicio_sireb_codigo && (
                        <span className="font-mono text-[11px] text-slate-400">
                          {campo.servicio_sireb_codigo}
                        </span>
                      )}
                    </div>
                    <h3 className="text-lg font-bold mb-2 group-hover:text-teal-700 transition-colors">
                      {campo.nombre}
                    </h3>
                    <p className="flex items-center gap-1 text-sm text-slate-600 mb-4 line-clamp-1">
                      <MapPin className="w-3.5 h-3.5 flex-shrink-0" />
                      {campo.direccion}
                    </p>

                    {/* Tarifas */}
                    <div className="flex items-center gap-3 pt-4 border-t border-slate-100">
                      {rangoPreciosOficial(campo) ? (
                        <div className="flex items-center gap-1.5 text-sm">
                          <span className="font-semibold">
                            {rangoPreciosOficial(campo)}
                          </span>
                          <span className="text-xs text-slate-500">oficial</span>
                        </div>
                      ) : (
                        <>
                          {campo.tarifas?.diurna ? (
                            <div className="flex items-center gap-1 text-sm">
                              <Sun className="w-4 h-4 text-amber-500" />
                              <span className="font-semibold">
                                Bs {campo.tarifas.diurna.precio_por_hora.toFixed(0)}
                              </span>
                            </div>
                          ) : null}
                          {campo.tarifas?.nocturna ? (
                            <div className="flex items-center gap-1 text-sm">
                              <Lightbulb className="w-4 h-4 text-indigo-500" />
                              <span className="font-semibold">
                                Bs {campo.tarifas.nocturna.precio_por_hora.toFixed(0)}
                              </span>
                            </div>
                          ) : null}
                          {!campo.tarifas?.diurna && !campo.tarifas?.nocturna && (
                            <span className="text-xs text-slate-400 italic">
                              Consultar precio
                            </span>
                          )}
                        </>
                      )}
                      <ArrowRight className="w-4 h-4 ml-auto text-slate-400 group-hover:text-teal-600 group-hover:translate-x-1 transition-all" />
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}
