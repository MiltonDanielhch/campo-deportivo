import { useState, useEffect } from 'react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { api } from '@/lib/api';

interface TipoCampo {
  id: string;
  nombre: string;
}

interface FiltrosCamposProps {
  onFiltrosChange: (filtros: { tipoCampoId: string | null; estado: string | null }) => void;
}

export default function FiltrosCampos({ onFiltrosChange }: FiltrosCamposProps) {
  const [tiposCampo, setTiposCampo] = useState<TipoCampo[]>([]);
  const [tipoSeleccionado, setTipoSeleccionado] = useState<string>('todos');
  const [estadoSeleccionado, setEstadoSeleccionado] = useState<string>('todos');

  useEffect(() => {
    api
      .get('/public/tipos-campo')
      .then((res) => setTiposCampo(res.data.data))
      .catch((err) => console.error('Error al cargar tipos de campo:', err));
  }, []);

  const handleTipoChange = (valor: string) => {
    setTipoSeleccionado(valor);
    onFiltrosChange({
      tipoCampoId: valor === 'todos' ? null : valor,
      estado: estadoSeleccionado === 'todos' ? null : estadoSeleccionado,
    });
  };

  const handleEstadoChange = (valor: string) => {
    setEstadoSeleccionado(valor);
    onFiltrosChange({
      tipoCampoId: tipoSeleccionado === 'todos' ? null : tipoSeleccionado,
      estado: valor === 'todos' ? null : valor,
    });
  };

  return (
    <div className="flex flex-col sm:flex-row gap-4 mb-6">
      <div className="flex-1">
        <Label htmlFor="tipo-campo">Tipo de campo</Label>
        <Select value={tipoSeleccionado} onValueChange={handleTipoChange}>
          <SelectTrigger id="tipo-campo">
            <SelectValue placeholder="Todos los tipos" />
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
      </div>
      <div className="flex-1">
        <Label htmlFor="estado">Estado</Label>
        <Select value={estadoSeleccionado} onValueChange={handleEstadoChange}>
          <SelectTrigger id="estado">
            <SelectValue placeholder="Todos los estados" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="todos">Todos los estados</SelectItem>
            <SelectItem value="activo">Activo</SelectItem>
            <SelectItem value="mantenimiento">En mantenimiento</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}