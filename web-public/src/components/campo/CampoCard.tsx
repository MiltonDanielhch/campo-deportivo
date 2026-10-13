import { useState } from 'react';
import { MapPin, ArrowRight, Sun, Lightbulb, AlertCircle, BadgeCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { CardContent } from '@/components/ui/card';
import type { CampoPublico } from '@/types/campo';

interface CampoCardProps {
  campo: CampoPublico;
}

/** Normaliza URLs locales al proxy CORS del backend. */
const normalizarUrlImagen = (url: string | null | undefined): string | null => {
  if (!url) return null;
  if (url.startsWith('http')) return url;
  if (url.startsWith('/storage/')) {
    return url.replace('/storage/', '/api/v1/public/storage/');
  }
  return url;
};

/** Fallback visual: cancha dibujada (coherente con landing y detalle). */
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

export default function CampoCard({ campo }: CampoCardProps) {
  const [imagenRota, setImagenRota] = useState(false);
  const esMantenimiento = campo.estado === 'mantenimiento';
  const esReservable = campo.reservable_online && !esMantenimiento;
  const { diurna, nocturna } = campo.tarifas ?? { diurna: null, nocturna: null };

  const esOficial = campo.fuente_precios === 'SIREB' && campo.sireb != null;
  const codigoOficial = campo.sireb?.codigo ?? campo.servicio_sireb_codigo;
  const imagenUrl = normalizarUrlImagen(campo.imagen_url);

  // Helper para formatear precio desde SIREB
  const formatoPrecioSireb = (): string => {
    const sireb = campo.sireb;

    if (!sireb) return 'Consultar precio';

    if (sireb.precio_min != null && sireb.precio_max != null) {
      return sireb.precio_min === sireb.precio_max
        ? `Bs. ${sireb.precio_min.toFixed(2)}`
        : `Bs. ${sireb.precio_min.toFixed(2)} – Bs. ${sireb.precio_max.toFixed(2)}`;
    }

    const precios = sireb.tarifas
      .map((t) => t.precio)
      .filter((p): p is number => p != null);

    if (precios.length === 0) return 'Consultar precio';

    const min = Math.min(...precios);
    const max = Math.max(...precios);

    return min === max
      ? `Bs. ${min.toFixed(2)}`
      : `Bs. ${min.toFixed(2)} – Bs. ${max.toFixed(2)}`;
  };

  return (
    <Link
      to={esReservable ? `/campos/${campo.id}` : '#'}
      className={`group flex h-full flex-col rounded-2xl overflow-hidden bg-white border border-slate-100 shadow-sm transition-all duration-300 ${
        !esReservable
          ? 'opacity-70 cursor-not-allowed'
          : 'hover:shadow-xl hover:-translate-y-1'
      }`}
    >
      {/* ─── Imagen 16:9 con fallback ─── */}
      <div className="relative aspect-video w-full bg-slate-100 overflow-hidden">
        {imagenUrl && !imagenRota ? (
          <img
            src={imagenUrl}
            alt={campo.nombre}
            onError={() => setImagenRota(true)}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
        ) : (
          <CampoSinFoto />
        )}

        {/* Badges de estado */}
        <div className="absolute top-3 right-3 flex flex-col items-end gap-2">
          {esOficial && (
            <Badge className="gap-1 bg-white/95 text-teal-700 hover:bg-white shadow-sm backdrop-blur">
              <BadgeCheck className="w-3 h-3" />
              Oficial
            </Badge>
          )}
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
      </div>

      {/* ─── Contenido (flex-col para alinear CTA abajo) ─── */}
      <CardContent className="p-5 flex flex-1 flex-col">
        <div className="flex items-center justify-between gap-2 mb-1">
          <p className="text-xs font-medium uppercase tracking-wider text-slate-500">
            {campo.tipo_campo?.nombre ?? 'Campo deportivo'}
          </p>
          {codigoOficial && (
            <span className="font-mono text-[11px] text-slate-400">
              {codigoOficial}
            </span>
          )}
        </div>
        <h3 className="text-lg font-bold leading-tight mb-2 group-hover:text-teal-700 transition-colors">
          {campo.nombre}
        </h3>
        <p className="flex items-center gap-1 text-sm text-slate-600 mb-4 line-clamp-1">
          <MapPin className="w-3.5 h-3.5 flex-shrink-0 text-slate-400" />
          {campo.direccion}
        </p>

        {/* ─── Fila de precios (mt-auto la ancla al fondo) ─── */}
        <div className="flex items-center gap-3 mt-auto pb-4 mb-4 border-b border-slate-100 min-h-[28px]">
          {esOficial ? (
            <div className="flex items-center gap-1.5 text-sm">
              <span className="font-semibold">{formatoPrecioSireb()}</span>
              <span className="text-xs text-slate-500">oficial</span>
            </div>
          ) : (
            <>
              {diurna ? (
                <div className="flex items-center gap-1 text-sm">
                  <Sun className="w-4 h-4 text-amber-500" />
                  <span className="font-semibold">
                    Bs {diurna.precio_por_hora.toFixed(0)}
                  </span>
                  <span className="text-xs text-slate-500">regular</span>
                </div>
              ) : null}
              {nocturna ? (
                <div className="flex items-center gap-1 text-sm">
                  <Lightbulb className="w-4 h-4 text-indigo-500" />
                  <span className="font-semibold">
                    Bs {nocturna.precio_por_hora.toFixed(0)}
                  </span>
                  <span className="text-xs text-slate-500">con luces</span>
                </div>
              ) : null}
              {!diurna && !nocturna && (
                <span className="text-xs text-slate-400 italic">
                  Consultar precio
                </span>
              )}
            </>
          )}
        </div>

        {/* ─── CTA o mensaje único de no reservable ─── */}
        {esReservable ? (
          <div className="flex items-center justify-between text-sm font-semibold text-teal-600 group-hover:text-teal-700">
            <span>Ver disponibilidad</span>
            <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
          </div>
        ) : (
          <p className="text-sm text-orange-600 italic flex items-start gap-1">
            <AlertCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
            {esMantenimiento
              ? 'No disponible temporalmente'
              : campo.mensaje_no_reservable ||
                'No disponible para reserva online'}
          </p>
        )}
      </CardContent>
    </Link>
  );
}
