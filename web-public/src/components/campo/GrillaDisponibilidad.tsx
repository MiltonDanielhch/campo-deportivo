import { Lock, Lightbulb, Sun, AlertCircle } from 'lucide-react';
import { useCarritoStore, type FranjaCarrito } from '@/store/carritoStore';

interface InfoTarifa {
  precio_por_hora: number;
  vigente_desde: string;
}

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
  tarifas: {
    diurna: InfoTarifa | null;
    nocturna: InfoTarifa | null;
  };
  horaInicioNoche: string;
}

const minutosDesdeMedianoche = (hhmm: string): number => {
  const [h, m = 0] = hhmm.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
};

type TipoTarifa = 'diurna' | 'nocturna';

function determinarTipo(
  horaInicioBloque: string,
  horaCorte: string,
): TipoTarifa {
  return minutosDesdeMedianoche(horaInicioBloque) >=
    minutosDesdeMedianoche(horaCorte)
    ? 'nocturna'
    : 'diurna';
}

export default function GrillaDisponibilidad({
  bloques,
  campoId,
  campoNombre,
  fecha,
  tarifas,
  horaInicioNoche,
}: GrillaDisponibilidadProps) {
  const { franjas, agregarFranja, quitarFranja } = useCarritoStore();

  const estaSeleccionada = (bloque: Bloque) =>
    franjas.some(
      (f) =>
        f.campoId === campoId &&
        f.fecha === fecha &&
        f.horaInicio === bloque.hora_inicio,
    );

  const toggleBloque = (bloque: Bloque) => {
    if (bloque.estado !== 'libre') return;

    const tipo = determinarTipo(bloque.hora_inicio, horaInicioNoche);
    const tarifa = tarifas[tipo];
    if (!tarifa) return;

    const franja: FranjaCarrito = {
      campoId,
      campoNombre,
      fecha,
      horaInicio: bloque.hora_inicio,
      horaFin: bloque.hora_fin,
      precio: tarifa.precio_por_hora,
    };

    if (estaSeleccionada(bloque)) {
      quitarFranja(franja);
    } else {
      agregarFranja(franja);
    }
  };

  const getClase = (
    bloque: Bloque,
    tarifa: InfoTarifa | null,
    tipo: TipoTarifa,
  ) => {
    if (bloque.estado === 'ocupada') {
      return 'bg-red-50 border-red-200 cursor-not-allowed opacity-70';
    }
    if (bloque.estado === 'bloqueada_temporal') {
      return 'bg-orange-50 border-orange-200 cursor-not-allowed opacity-80';
    }
    if (!tarifa) {
      return 'bg-slate-50 border-slate-200 cursor-not-allowed opacity-60';
    }
    if (estaSeleccionada(bloque)) {
      return 'bg-teal-600 text-white border-teal-600 shadow-md scale-[1.02]';
    }
    // Libre sin seleccionar: color por tipo + hover
    return tipo === 'nocturna'
      ? 'bg-indigo-50/60 border-indigo-200 hover:bg-indigo-100 hover:border-indigo-300 cursor-pointer hover:-translate-y-0.5 hover:shadow-sm'
      : 'bg-amber-50/60 border-amber-200 hover:bg-amber-100 hover:border-amber-300 cursor-pointer hover:-translate-y-0.5 hover:shadow-sm';
  };

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
      {bloques.map((bloque) => {
        const tipo = determinarTipo(bloque.hora_inicio, horaInicioNoche);
        const tarifa = tarifas[tipo];
        const Icono = tipo === 'nocturna' ? Lightbulb : Sun;
        const seleccionado = estaSeleccionada(bloque);

        return (
          <button
            key={`${bloque.hora_inicio}-${bloque.hora_fin}`}
            onClick={() => toggleBloque(bloque)}
            disabled={bloque.estado !== 'libre' || !tarifa}
            className={`relative p-3 border-2 rounded-xl text-center transition-all duration-200 ${getClase(
              bloque,
              tarifa,
              tipo,
            )}`}
            title={
              !tarifa && bloque.estado === 'libre'
                ? tipo === 'nocturna'
                  ? 'Sin tarifa con iluminación definida'
                  : 'Sin tarifa regular definida'
                : undefined
            }
          >
            {/* Franja lateral de color por tipo (solo si es libre y tiene tarifa) */}
            {bloque.estado === 'libre' && tarifa && !seleccionado && (
              <span
                className={`absolute top-2 left-2 w-1 h-6 rounded-full ${
                  tipo === 'nocturna' ? 'bg-indigo-400' : 'bg-amber-400'
                }`}
              />
            )}

            <div className="flex items-center justify-center gap-1.5 font-bold text-sm">
              {bloque.estado === 'libre' && (
                <Icono
                  className={`w-3.5 h-3.5 ${
                    seleccionado
                      ? 'text-white'
                      : tipo === 'nocturna'
                        ? 'text-indigo-500'
                        : 'text-amber-500'
                  }`}
                />
              )}
              <span>
                {bloque.hora_inicio.slice(0, 5)}–{bloque.hora_fin.slice(0, 5)}
              </span>
            </div>

            <div className="text-xs mt-1.5">
              {bloque.estado === 'ocupada' && (
                <span className="flex items-center justify-center gap-1 text-red-700 font-medium">
                  <Lock className="w-3 h-3" />
                  Ocupada
                </span>
              )}
              {bloque.estado === 'bloqueada_temporal' && (
                <span className="text-orange-700 font-medium">
                  En cobro
                </span>
              )}
              {bloque.estado === 'libre' && tarifa && (
                <>
                  <span className="block font-bold text-sm">
                    Bs {tarifa.precio_por_hora.toFixed(0)}
                  </span>
                  <span
                    className={`block text-[10px] uppercase tracking-wider font-semibold ${
                      seleccionado ? 'text-white/80' : 'opacity-70'
                    }`}
                  >
                    {seleccionado
                      ? 'Seleccionada'
                      : tipo === 'nocturna'
                        ? 'Con luces'
                        : 'Regular'}
                  </span>
                </>
              )}
              {bloque.estado === 'libre' && !tarifa && (
                <span className="flex items-center justify-center gap-1 text-slate-500 font-medium">
                  <AlertCircle className="w-3 h-3" />
                  Sin tarifa
                </span>
              )}
            </div>
          </button>
        );
      })}
    </div>
  );
}
