import { ImageIcon, MapPin, ArrowRight, Sun, Lightbulb } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
  imagen_url: string | null;
  tarifas: {
    diurna: { precio_por_hora: number } | null;
    nocturna: { precio_por_hora: number } | null;
  };
}

interface CampoCardProps {
  campo: Campo;
}

export default function CampoCard({ campo }: CampoCardProps) {
  const esMantenimiento = campo.estado === 'mantenimiento';
  const { diurna, nocturna } = campo.tarifas ?? { diurna: null, nocturna: null };

  return (
    <Link
      to={esMantenimiento ? '#' : `/campos/${campo.id}`}
      className={`group block rounded-2xl overflow-hidden bg-white border border-slate-100 shadow-sm transition-all duration-300 ${
        esMantenimiento
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
        <div className="absolute top-3 right-3">
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
        <p className="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">
          {campo.tipo_campo.nombre}
        </p>
        <h3 className="text-lg font-bold leading-tight mb-2 group-hover:text-teal-700 transition-colors">
          {campo.nombre}
        </h3>
        <p className="flex items-center gap-1 text-sm text-slate-600 mb-4 line-clamp-1">
          <MapPin className="w-3.5 h-3.5 flex-shrink-0 text-slate-400" />
          {campo.direccion}
        </p>

        {/* ─── Fila de precios ─── */}
        <div className="flex items-center gap-3 pb-4 mb-4 border-b border-slate-100 min-h-[28px]">
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
        </div>

        {/* ─── Botón CTA ─── */}
        {!esMantenimiento ? (
          <div className="flex items-center justify-between text-sm font-semibold text-teal-600 group-hover:text-teal-700">
            <span>Ver disponibilidad</span>
            <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
          </div>
        ) : (
          <p className="text-sm text-orange-600 italic">
            No disponible temporalmente
          </p>
        )}
      </CardContent>
    </Link>
  );
}
