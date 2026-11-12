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

function CampoSinFoto() {
  return (
    <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-600 to-teal-700">
      <div className="relative h-20 w-36 rounded-sm border-2 border-white/60 opacity-80">
        <div className="absolute inset-x-0 top-1/2 h-0.5 bg-white/60" />
        <div className="absolute left-1/2 top-1/2 h-10 w-10 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/60" />
        <div className="absolute left-0 top-1/2 h-12 w-6 -translate-y-1/2 border-2 border-l-0 border-white/60" />
        <div className="absolute right-0 top-1/2 h-12 w-6 -translate-y-1/2 border-2 border-r-0 border-white/60" />
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

function scoreDestacado(campo: CampoPublico): number {
  let score = 0;
  if (campo.estado === 'activo') score += 4;
  if (normalizarUrlImagen(campo.imagen_url)) score += 2;
  if (rangoPreciosOficial(campo) || campo.tarifas?.diurna || campo.tarifas?.nocturna) score += 1;
  if (campo.reservable_online) score += 1;
  return score;
}

function CardDestacado({ campo }: { campo: CampoPublico }) {
  const [imgError, setImgError] = useState(false);
  const imagenUrl = normalizarUrlImagen(campo.imagen_url);
  const precioOficial = rangoPreciosOficial(campo);

  return (
    <Link
      to={`/campos/${campo.id}`}
      className="group overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-emerald-900/10"
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-slate-100">
        {imagenUrl && !imgError ? (
          <img
            src={imagenUrl}
            alt={campo.nombre}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            onError={() => setImgError(true)}
          />
        ) : (
          <CampoSinFoto />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent" />
        <div className="absolute left-3 top-3 flex gap-2">
          {campo.fuente_precios === 'SIREB' && campo.sireb && (
            <Badge className="gap-1 bg-white/95 text-emerald-700 shadow-sm backdrop-blur hover:bg-white">
              <BadgeCheck className="h-3 w-3" />
              Oficial
            </Badge>
          )}
        </div>
        <Badge
          className={`absolute right-3 top-3 ${
            campo.estado === 'activo'
              ? 'bg-emerald-500 hover:bg-emerald-600'
              : 'bg-slate-500 hover:bg-slate-600'
          }`}
        >
          {campo.estado}
        </Badge>
        <p className="absolute bottom-3 left-4 text-xs font-semibold uppercase tracking-[0.15em] text-white/90">
          {campo.tipo_campo?.nombre ?? 'Campo deportivo'}
        </p>
      </div>

      <div className="p-5">
        <h3 className="text-lg font-bold text-slate-900 transition-colors group-hover:text-emerald-700">
          {campo.nombre}
        </h3>
        <p className="mt-1.5 flex items-center gap-1.5 text-sm text-slate-500 line-clamp-1">
          <MapPin className="h-3.5 w-3.5 flex-shrink-0" />
          {campo.direccion ?? [campo.municipio, campo.provincia].filter(Boolean).join(', ')}
        </p>

        <div className="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4">
          {precioOficial ? (
            <div className="flex items-baseline gap-1.5">
              <span className="text-base font-bold text-slate-900">{precioOficial}</span>
              <span className="text-xs text-slate-400">oficial</span>
            </div>
          ) : (
            <>
              {campo.tarifas?.diurna && (
                <span className="flex items-center gap-1 text-sm font-semibold text-slate-900">
                  <Sun className="h-4 w-4 text-amber-500" />
                  Bs {campo.tarifas.diurna.precio_por_hora.toFixed(0)}
                </span>
              )}
              {campo.tarifas?.nocturna && (
                <span className="flex items-center gap-1 text-sm font-semibold text-slate-900">
                  <Lightbulb className="h-4 w-4 text-indigo-500" />
                  Bs {campo.tarifas.nocturna.precio_por_hora.toFixed(0)}
                </span>
              )}
              {!campo.tarifas?.diurna && !campo.tarifas?.nocturna && (
                <span className="text-xs italic text-slate-400">Consultar precio</span>
              )}
            </>
          )}
          <span className="ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 transition-all group-hover:bg-emerald-600 group-hover:text-white">
            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
          </span>
        </div>
      </div>
    </Link>
  );
}

export default function CamposDestacados() {
  const [campos, setCampos] = useState<CampoPublico[]>([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api
      .get<CamposResponse>('/public/campos')
      .then((res) => {
        const ordenados = [...res.data.data]
          .sort((a, b) => scoreDestacado(b) - scoreDestacado(a))
          .slice(0, 3);
        setCampos(ordenados);
      })
      .catch((err) => console.error('Error al cargar campos destacados:', err))
      .finally(() => setCargando(false));
  }, []);

  return (
    <section id="campos" className="bg-slate-50 py-20 md:py-28">
      <div className="container mx-auto max-w-6xl px-4">
        <div className="mb-12 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">
              Disponibles hoy
            </p>
            <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-900 md:text-5xl">
              Campos destacados
            </h2>
            <p className="mt-3 max-w-xl text-lg text-slate-600">
              Infraestructura oficial del GAD Beni, con tarifas vigentes y
              reserva en línea.
            </p>
          </div>
          <Button asChild variant="outline" className="rounded-full md:w-auto">
            <Link to="/campos" className="flex items-center justify-center gap-2">
              Ver todas
              <ArrowRight className="h-4 w-4" />
            </Link>
          </Button>
        </div>

        {cargando ? (
          <div className="grid gap-6 md:grid-cols-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse overflow-hidden rounded-3xl border border-slate-100 bg-white">
                <div className="aspect-[4/3] bg-slate-200" />
                <div className="space-y-3 p-5">
                  <div className="h-5 w-3/4 rounded bg-slate-200" />
                  <div className="h-4 w-1/2 rounded bg-slate-200" />
                  <div className="h-4 w-full rounded bg-slate-200" />
                </div>
              </div>
            ))}
          </div>
        ) : campos.length === 0 ? (
          <p className="py-12 text-center text-slate-500">
            No hay canchas destacadas por el momento.
          </p>
        ) : (
          <div className="grid gap-6 md:grid-cols-3">
            {campos.map((campo) => (
              <CardDestacado key={campo.id} campo={campo} />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
