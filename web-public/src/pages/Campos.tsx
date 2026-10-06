import { useState, useEffect, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { Card, CardContent } from '@/components/ui/card';
import { SearchX, Trophy, AlertCircle, RefreshCw } from 'lucide-react';
import { api } from '@/lib/api';
import FiltrosCampos, { type FiltrosEstado } from '@/components/campo/FiltrosCampos';
import CampoCard from '@/components/campo/CampoCard';
import MapaCampos from '@/components/campo/MapaCampos';
import type { CampoPublico, CamposResponse } from '@/types/campo';

type VistaModo = 'lista' | 'mapa';

const FILTROS_INICIALES: FiltrosEstado = {
  tipoCampoId: null,
  estado: null,
  buscar: '',
};

export default function Campos() {
  const [campos, setCampos] = useState<CampoPublico[]>([]);
  const [cargando, setCargando] = useState(true);
  const [vista, setVista] = useState<VistaModo>('lista');
  const [filtros, setFiltros] = useState<FiltrosEstado>(FILTROS_INICIALES);
  const [meta, setMeta] = useState<CamposResponse['meta'] | null>(null);

  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (abortRef.current) abortRef.current.abort();
    abortRef.current = new AbortController();

    setCargando(true);

    const params: Record<string, string> = {};
    if (filtros.tipoCampoId) params.tipo_campo_id = filtros.tipoCampoId;
    if (filtros.estado) params.estado = filtros.estado;
    if (filtros.buscar.trim()) params.buscar = filtros.buscar.trim();

    api
      .get<CamposResponse>('/public/campos', {
        params,
        signal: abortRef.current.signal,
      })
      .then((res) => {
        setCampos(res.data.data);
        setMeta(res.data.meta);
      })
      .catch((err) => {
        if (err.name !== 'CanceledError' && err.name !== 'AbortError') {
          console.error('Error al cargar campos:', err);
        }
      })
      .finally(() => {
        if (!abortRef.current?.signal.aborted) {
          setCargando(false);
        }
      });

    return () => abortRef.current?.abort();
  }, [filtros]);

  // Estadísticas de resultados
  const totalActivos = campos.filter((c) => c.estado === 'activo').length;

  return (
    <>
      <Helmet>
        <title>Campos Deportivos Disponibles - GAD Beni</title>
        <meta
          name="description"
          content="Explora todos los campos deportivos disponibles en Trinidad, Beni. Buscá por nombre, filtrá por tipo de deporte y ubicación."
        />
      </Helmet>

      <div className="min-h-screen bg-slate-50">
        <div className="container mx-auto px-4 py-10 md:py-14 max-w-6xl">
          {/* ─── Encabezado con jerarquía ─── */}
          <div className="mb-10">
            <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-3 flex items-center gap-2">
              <Trophy className="w-3.5 h-3.5" />
              Catálogo público
            </p>
            <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
              <div>
                <h1 className="text-3xl md:text-5xl font-bold tracking-tight mb-2">
                  Campos deportivos
                </h1>
                <p className="text-slate-600 text-lg max-w-2xl">
                  Encontrá y reservá tu cancha en Trinidad, Beni.
                </p>
              </div>
              {!cargando && campos.length > 0 && (
                <div className="flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 shadow-sm">
                  <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                  <span className="text-sm font-semibold">
                    {campos.length} {campos.length === 1 ? 'cancha' : 'canchas'}
                  </span>
                  <span className="text-xs text-slate-500">
                    · {totalActivos} disponibles
                  </span>
                </div>
              )}
            </div>
          </div>

          {/* ─── Barra de filtros + toggle ─── */}
          <FiltrosCampos
            filtros={filtros}
            onFiltrosChange={setFiltros}
            vista={vista}
            onVistaChange={setVista}
          />

          {/* ─── Contenido ─── */}
          {cargando ? (
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <Card
                  key={i}
                  className="overflow-hidden animate-pulse border-slate-100"
                >
                  <div className="aspect-video bg-slate-200" />
                  <CardContent className="pt-4 space-y-3">
                    <div className="h-5 bg-slate-200 rounded w-1/3" />
                    <div className="h-5 bg-slate-200 rounded w-3/4" />
                    <div className="h-4 bg-slate-200 rounded w-full" />
                    <div className="h-4 bg-slate-200 rounded w-2/3" />
                    <div className="h-10 bg-slate-200 rounded-full mt-4" />
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : vista === 'lista' ? (
            campos.length > 0 ? (
              <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                {campos.map((campo) => (
                  <CampoCard key={campo.id} campo={campo} />
                ))}
              </div>
            ) : (
              <div className="text-center py-24 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <div className="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-5">
                  <SearchX className="w-7 h-7 text-slate-400" />
                </div>
                <h3 className="text-xl font-bold mb-2">
                  No encontramos canchas
                </h3>
                <p className="text-slate-500 max-w-md mx-auto mb-6">
                  No hay canchas que coincidan con tu búsqueda. Probá limpiar
                  los filtros o buscar con otros términos.
                </p>
                <button
                  onClick={() => setFiltros(FILTROS_INICIALES)}
                  className="text-teal-600 font-semibold hover:text-teal-700 underline underline-offset-4"
                >
                  Limpiar todos los filtros
                </button>
              </div>
            )
          ) : (
            <div className="rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
              <MapaCampos campos={campos} />
            </div>
          )}

          {/* ─── Footer con información de origen de precios ─── */}
          {meta && (
            <div className="mt-8 p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
              {meta.aviso ? (
                <div className="flex items-start gap-3 text-amber-700">
                  <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
                  <div>
                    <p className="font-semibold text-sm">{meta.aviso}</p>
                    <p className="text-xs text-slate-500 mt-1">
                      Fuente: {meta.fuente_precios}
                    </p>
                  </div>
                </div>
              ) : (
                <div className="flex items-center gap-2 text-sm text-slate-600">
                  <RefreshCw className="w-4 h-4 text-teal-600" />
                  <p>
                    Precios oficiales sincronizados desde Paitití / SIREB.
                    {meta.sincronizado_en && (
                      <span className="ml-2 text-slate-500">
                        Última actualización: {new Date(meta.sincronizado_en).toLocaleString('es-BO')}
                      </span>
                    )}
                  </p>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </>
  );
}
