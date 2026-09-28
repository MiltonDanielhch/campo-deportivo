import { useState, useEffect, useRef } from 'react';
import { Search, X, List, Map as MapIcon, Filter } from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { api } from '@/lib/api';

interface TipoCampo {
  id: string;
  nombre: string;
}

export interface FiltrosEstado {
  tipoCampoId: string | null;
  estado: string | null;
  buscar: string;
}

type VistaModo = 'lista' | 'mapa';

interface FiltrosCamposProps {
  filtros: FiltrosEstado;
  onFiltrosChange: (filtros: FiltrosEstado) => void;
  vista: VistaModo;
  onVistaChange: (vista: VistaModo) => void;
}

const DEBOUNCE_MS = 300;

export default function FiltrosCampos({
  filtros,
  onFiltrosChange,
  vista,
  onVistaChange,
}: FiltrosCamposProps) {
  const [tiposCampo, setTiposCampo] = useState<TipoCampo[]>([]);
  const [textoBusqueda, setTextoBusqueda] = useState(filtros.buscar);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    api
      .get('/public/tipos-campo')
      .then((res) => setTiposCampo(res.data.data))
      .catch((err) => console.error('Error al cargar tipos de campo:', err));
  }, []);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      if (textoBusqueda !== filtros.buscar) {
        onFiltrosChange({ ...filtros, buscar: textoBusqueda });
      }
    }, DEBOUNCE_MS);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [textoBusqueda, filtros, onFiltrosChange]);

  // Nombre legible del tipo seleccionado (para el chip)
  const tipoSeleccionadoNombre = tiposCampo.find(
    (t) => t.id === filtros.tipoCampoId,
  )?.nombre;

  const tieneFiltros =
    filtros.tipoCampoId !== null || filtros.estado !== null || filtros.buscar.trim() !== '';

  const limpiarTodo = () => {
    setTextoBusqueda('');
    onFiltrosChange({ tipoCampoId: null, estado: null, buscar: '' });
  };

  return (
    <div className="mb-8 bg-white rounded-2xl border border-slate-200 shadow-sm p-4 md:p-5">
      {/* ─── Fila principal: buscador + selects + toggle ─── */}
      <div className="flex flex-col lg:flex-row gap-3">
        {/* Buscador (ocupa más espacio) */}
        <div className="relative flex-1 min-w-0">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <Input
            type="text"
            placeholder="Buscar cancha por nombre o dirección..."
            value={textoBusqueda}
            onChange={(e) => setTextoBusqueda(e.target.value)}
            className="pl-10 pr-10 h-11 bg-slate-50 border-slate-200 focus:bg-white"
          />
          {textoBusqueda && (
            <button
              type="button"
              onClick={() => {
                setTextoBusqueda('');
                onFiltrosChange({ ...filtros, buscar: '' });
              }}
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
              aria-label="Limpiar búsqueda"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        {/* Selects */}
        <div className="flex gap-2">
          <Select
            value={filtros.tipoCampoId ?? 'todos'}
            onValueChange={(v) =>
              onFiltrosChange({
                ...filtros,
                tipoCampoId: v === 'todos' ? null : v,
              })
            }
          >
            <SelectTrigger className="w-full sm:w-44 h-11 bg-slate-50 border-slate-200">
              <SelectValue placeholder="Tipo" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos los tipos</SelectItem>
              {tiposCampo.map((tipo) => (
                <SelectItem key={tipo.id} value={tipo.id}>
                  {tipo.nombre}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={filtros.estado ?? 'todos'}
            onValueChange={(v) =>
              onFiltrosChange({
                ...filtros,
                estado: v === 'todos' ? null : v,
              })
            }
          >
            <SelectTrigger className="w-full sm:w-44 h-11 bg-slate-50 border-slate-200">
              <SelectValue placeholder="Estado" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos</SelectItem>
              <SelectItem value="activo">Activos</SelectItem>
              <SelectItem value="mantenimiento">En mantenimiento</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Toggle Lista / Mapa */}
        <div className="flex rounded-lg border border-slate-200 p-1 bg-slate-50">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onVistaChange('lista')}
            className={`flex-1 h-9 px-3 ${
              vista === 'lista'
                ? 'bg-white shadow-sm text-slate-900'
                : 'text-slate-500 hover:text-slate-700 hover:bg-transparent'
            }`}
          >
            <List className="w-4 h-4 mr-1.5" />
            Lista
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onVistaChange('mapa')}
            className={`flex-1 h-9 px-3 ${
              vista === 'mapa'
                ? 'bg-white shadow-sm text-slate-900'
                : 'text-slate-500 hover:text-slate-700 hover:bg-transparent'
            }`}
          >
            <MapIcon className="w-4 h-4 mr-1.5" />
            Mapa
          </Button>
        </div>
      </div>

      {/* ─── Chips de filtros activos ─── */}
      {tieneFiltros && (
        <div className="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
          <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">
            <Filter className="w-3 h-3" />
            Filtros activos
          </span>

          {filtros.buscar.trim() !== '' && (
            <Badge
              variant="outline"
              className="gap-1 pl-2.5 pr-1 py-1 bg-teal-50 border-teal-200 text-teal-700"
            >
              Búsqueda: "{filtros.buscar}"
              <button
                onClick={() => {
                  setTextoBusqueda('');
                  onFiltrosChange({ ...filtros, buscar: '' });
                }}
                className="ml-1 rounded-full p-0.5 hover:bg-teal-200/60 transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}

          {tipoSeleccionadoNombre && (
            <Badge
              variant="outline"
              className="gap-1 pl-2.5 pr-1 py-1 bg-teal-50 border-teal-200 text-teal-700"
            >
              {tipoSeleccionadoNombre}
              <button
                onClick={() =>
                  onFiltrosChange({ ...filtros, tipoCampoId: null })
                }
                className="ml-1 rounded-full p-0.5 hover:bg-teal-200/60 transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}

          {filtros.estado && (
            <Badge
              variant="outline"
              className="gap-1 pl-2.5 pr-1 py-1 bg-teal-50 border-teal-200 text-teal-700"
            >
              {filtros.estado === 'activo' ? 'Activos' : 'En mantenimiento'}
              <button
                onClick={() => onFiltrosChange({ ...filtros, estado: null })}
                className="ml-1 rounded-full p-0.5 hover:bg-teal-200/60 transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}

          <button
            onClick={limpiarTodo}
            className="text-xs font-medium text-slate-500 hover:text-slate-900 underline underline-offset-4 ml-1"
          >
            Limpiar todo
          </button>
        </div>
      )}
    </div>
  );
}
