import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Sun, Lightbulb, ArrowRight, ImageIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';

interface CampoDestacado {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  imagen_url: string | null;
  tarifas: {
    diurna: { precio_por_hora: number } | null;
    nocturna: { precio_por_hora: number } | null;
  };
}

export default function CamposDestacados() {
  const [campos, setCampos] = useState<CampoDestacado[]>([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api
      .get('/public/campos')
      .then((res) => setCampos(res.data.data.slice(0, 3)))
      .catch((err) => console.error('Error al cargar campos destacados:', err))
      .finally(() => setCargando(false));
  }, []);

  return (
    <section className="py-20 md:py-28 bg-slate-50">
      <div className="container mx-auto px-4 max-w-6xl">
        {/* Encabezado */}
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
          <Button asChild variant="outline" className="rounded-full">
            <Link to="/campos" className="flex items-center gap-2">
              Ver todas
              <ArrowRight className="w-4 h-4" />
            </Link>
          </Button>
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
            {campos.map((campo) => (
              <Link
                key={campo.id}
                to={`/campos/${campo.id}`}
                className="group bg-white rounded-2xl overflow-hidden border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
              >
                {/* Imagen 16:9 */}
                <div className="relative aspect-video bg-slate-100 overflow-hidden">
                  {campo.imagen_url ? (
                    <img
                      src={campo.imagen_url}
                      alt={campo.nombre}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                      loading="lazy"
                    />
                  ) : (
                    <div className="w-full h-full flex flex-col items-center justify-center text-slate-400">
                      <ImageIcon className="w-10 h-10 mb-2" />
                      <span className="text-xs">Sin foto</span>
                    </div>
                  )}
                  <div className="absolute top-3 right-3">
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
                  <p className="text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">
                    {campo.tipo_campo.nombre}
                  </p>
                  <h3 className="text-lg font-bold mb-2 group-hover:text-teal-700 transition-colors">
                    {campo.nombre}
                  </h3>
                  <p className="flex items-center gap-1 text-sm text-slate-600 mb-4 line-clamp-1">
                    <MapPin className="w-3.5 h-3.5 flex-shrink-0" />
                    {campo.direccion}
                  </p>

                  {/* Tarifas */}
                  <div className="flex items-center gap-3 pt-4 border-t border-slate-100">
                    {campo.tarifas.diurna ? (
                      <div className="flex items-center gap-1 text-sm">
                        <Sun className="w-4 h-4 text-amber-500" />
                        <span className="font-semibold">
                          Bs {campo.tarifas.diurna.precio_por_hora.toFixed(0)}
                        </span>
                      </div>
                    ) : null}
                    {campo.tarifas.nocturna ? (
                      <div className="flex items-center gap-1 text-sm">
                        <Lightbulb className="w-4 h-4 text-indigo-500" />
                        <span className="font-semibold">
                          Bs {campo.tarifas.nocturna.precio_por_hora.toFixed(0)}
                        </span>
                      </div>
                    ) : null}
                    {!campo.tarifas.diurna && !campo.tarifas.nocturna && (
                      <span className="text-xs text-slate-400 italic">
                        Consultar precio
                      </span>
                    )}
                    <ArrowRight className="w-4 h-4 ml-auto text-slate-400 group-hover:text-teal-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
