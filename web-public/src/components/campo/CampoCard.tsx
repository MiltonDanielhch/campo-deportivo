import { ImageIcon, MapPin, ArrowRight, Sun, Lightbulb, AlertCircle, BadgeCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { CardContent } from '@/components/ui/card';
import type { CampoPublico } from '@/types/campo';

interface CampoCardProps {
  campo: CampoPublico;
}

export default function CampoCard({ campo }: CampoCardProps) {
  const esMantenimiento = campo.estado === 'mantenimiento';
  const esReservable = campo.reservable_online && !esMantenimiento;
  const { diurna, nocturna } = campo.tarifas ?? { diurna: null, nocturna: null };

  // El nombre y el código son los oficiales de SIREB cuando el campo está
  // vinculado; el backend ya los resuelve, acá solo se muestran.
  const esOficial = campo.fuente_precios === 'SIREB' && campo.sireb != null;
  const codigoOficial = campo.sireb?.codigo ?? campo.servicio_sireb_codigo;

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
      className={`group block rounded-2xl overflow-hidden bg-white border border-slate-100 shadow-sm transition-all duration-300 ${
        !esReservable
          ? 'opacity-70 cursor-not-allowed'
          : 'hover:shadow-xl hover:-translate-y-1'
      }`}
    >
      {/* ─── Imagen 16:9 ─── */}
      <div className="relative aspect-video w-full bg-slate-100 overflow-hidden">
        {campo.imagen_url ? (
          <img
            src={campo.imagen_url}
            alt={campo.nombre}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full w-full flex-col items-center justify-center text-slate-400">
            <ImageIcon className="mb-1 h-10 w-10" />
            <span className="text-xs">Sin foto</span>
          </div>
        )}

        {/* Badge de estado */}
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

      {/* ─── Contenido ─── */}
      <CardContent className="p-5">
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

        {/* ─── Fila de precios ─── */}
        <div className="flex items-center gap-3 pb-4 mb-4 border-b border-slate-100 min-h-[28px]">
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

        {/* ─── Botón CTA ─── */}
        {esReservable ? (
          <div className="flex items-center justify-between text-sm font-semibold text-teal-600 group-hover:text-teal-700">
            <span>Ver disponibilidad</span>
            <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
          </div>
        ) : (
          <div className="space-y-1">
            {campo.mensaje_no_reservable && (
              <p className="text-xs text-slate-500 italic flex items-start gap-1">
                <AlertCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
                {campo.mensaje_no_reservable}
              </p>
            )}
            <p className="text-sm text-orange-600 italic">
              {esMantenimiento ? 'No disponible temporalmente' : 'No disponible para reserva online'}
            </p>
          </div>
        )}
      </CardContent>
    </Link>
  );
}
