import { Lock } from 'lucide-react';
import { useCarritoStore, type FranjaCarrito } from '@/store/carritoStore';

interface Bloque {
  hora_inicio: string;
  hora_fin: string;
  estado: 'libre' | 'ocupada' | 'bloqueada_temporal';
}

interface GrillaDisponibilidadProps {
  bloques: Bloque[];
  campoId: string;
  campoNombre: string;
  fecha: string;
  precioPorHora: number | null;
}

export default function GrillaDisponibilidad({
  bloques,
  campoId,
  campoNombre,
  fecha,
  precioPorHora,
}: GrillaDisponibilidadProps) {
  const { franjas, agregarFranja, quitarFranja } = useCarritoStore();

  const estaSeleccionada = (bloque: Bloque) => {
    return franjas.some(
      (f) =>
        f.campoId === campoId &&
        f.fecha === fecha &&
        f.horaInicio === bloque.hora_inicio,
    );
  };

  const toggleBloque = (bloque: Bloque) => {
    if (bloque.estado !== 'libre') return;

    const franja: FranjaCarrito = {
      campoId,
      campoNombre,
      fecha,
      horaInicio: bloque.hora_inicio,
      horaFin: bloque.hora_fin,
      precio: precioPorHora,
    };

    if (estaSeleccionada(bloque)) {
      quitarFranja(franja);
    } else {
      agregarFranja(franja);
    }
  };

  const getClase = (bloque: Bloque) => {
    if (bloque.estado === 'ocupada') {
      return 'bg-red-100 border-red-300 cursor-not-allowed opacity-60';
    }
    if (bloque.estado === 'bloqueada_temporal') {
      return 'bg-orange-100 border-orange-300 cursor-not-allowed opacity-70';
    }
    // libre
    if (estaSeleccionada(bloque)) {
      return 'bg-teal-600 text-white border-teal-700 hover:bg-teal-700';
    }
    return 'bg-green-50 border-green-300 hover:bg-green-100 cursor-pointer';
  };

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
      {bloques.map((bloque) => (
        <button
          key={`${bloque.hora_inicio}-${bloque.hora_fin}`}
          onClick={() => toggleBloque(bloque)}
          disabled={bloque.estado !== 'libre'}
          className={`p-3 border rounded-lg text-center transition-colors ${getClase(bloque)}`}
        >
          <div className="font-semibold text-sm">
            {bloque.hora_inicio.slice(0, 5)} - {bloque.hora_fin.slice(0, 5)}
          </div>
          <div className="text-xs mt-1">
            {bloque.estado === 'ocupada' && (
              <span className="flex items-center justify-center gap-1">
                <Lock className="w-3 h-3" />
                Ocupada
              </span>
            )}
            {bloque.estado === 'bloqueada_temporal' && (
              <span className="text-orange-700">En proceso de cobro</span>
            )}
            {bloque.estado === 'libre' && (
              <span>{estaSeleccionada(bloque) ? 'Seleccionada' : 'Libre'}</span>
            )}
          </div>
        </button>
      ))}
    </div>
  );
}
