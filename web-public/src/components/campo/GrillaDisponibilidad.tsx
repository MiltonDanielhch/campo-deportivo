import { Lock, Lightbulb, Sun, AlertCircle, Clock, Hourglass } from 'lucide-react';
import { useCarritoStore, type FranjaCarrito } from '@/store/carritoStore';

interface InfoTarifa {
  precio_por_hora: number;
  vigente_desde: string;
  etiqueta?: string;
  tipo?: 'diurna' | 'nocturna' | 'unica';
  sireb_tarifa_id?: string | null;
}

interface Bloque {
  hora_inicio: string;
  hora_fin: string;
  // 'pasada' = estado derivado del tiempo (hoy + hora Bolivia), no de solicitud.
  estado: 'libre' | 'ocupada' | 'bloqueada_temporal' | 'pasada';
}

interface GrillaDisponibilidadProps {
  bloques: Bloque[];
  campoId: string;
  campoNombre: string;
  fecha: string;
  tarifas: {
    diurna: InfoTarifa | null;
    nocturna: InfoTarifa | null;
    unica?: InfoTarifa | null;
  };
  horaInicioNoche: string;
}

const minutosDesdeMedianoche = (hhmm: string): number => {
  const [h, m = 0] = hhmm.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
};

type TipoTarifaVisible = 'diurna' | 'nocturna' | 'unica';

function determinarTipoHorario(
  horaInicioBloque: string,
  horaCorte: string,
): 'diurna' | 'nocturna' {
  return minutosDesdeMedianoche(horaInicioBloque) >=
    minutosDesdeMedianoche(horaCorte)
    ? 'nocturna'
    : 'diurna';
}

/**
 * Prioridad:
 * 1. Si existe tarifa horaria concreta para el bloque (diurna/nocturna), usarla.
 * 2. Si no, pero existe tarifa única, usarla para cualquier horario.
 * 3. Si no hay ninguna, bloque no seleccionable.
 */
function tarifaParaBloque(
  tarifas: GrillaDisponibilidadProps['tarifas'],
  horaInicioBloque: string,
  horaCorte: string,
): { tarifa: InfoTarifa | null; tipo: TipoTarifaVisible } {
  const tipoHorario = determinarTipoHorario(horaInicioBloque, horaCorte);
  const tarifaHoraria = tarifas[tipoHorario];

  if (tarifaHoraria) {
    return { tarifa: tarifaHoraria, tipo: tipoHorario };
  }

  if (tarifas.unica) {
    return { tarifa: tarifas.unica, tipo: 'unica' };
  }

  return { tarifa: null, tipo: tipoHorario };
}

// [estandar R/Y/G] el color del bloque libre YA NO codifica diurna/nocturna: todo
// bloque disponible es verde. El tipo de tarifa se comunica con el ICONO (sol/
// lampara/reloj) y la etiqueta ("Regular"/"Con luces"/"Única"), no con el color.
function clasesLibre(): string {
  return 'bg-emerald-50 border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300 cursor-pointer hover:-translate-y-0.5 hover:shadow-sm';
}

const LABEL_ESTADO: Record<Bloque['estado'], string> = {
  libre: 'Disponible',
  ocupada: 'Ocupada',
  bloqueada_temporal: 'En cobro',
  pasada: 'Pasada',
};

// [etiqueta-estado] el tipo de tarifa sale del TEXTO de la celda (donde colisionaba
// con el estado) y vive solo en el ICONO + este tooltip. Así la línea inferior de la
// celda comunica ESTADO (alineada 1:1 con la leyenda y con el ariaLabel), y el tipo
// se descubre al hover sin ruido visual.
const LABEL_TIPO: Record<TipoTarifaVisible, string> = {
  diurna: 'Tarifa regular (sin iluminación)',
  nocturna: 'Tarifa con iluminación',
  unica: 'Tarifa única',
};

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
    // 'pasada', 'ocupada' y 'bloqueada_temporal' caen acá: no se seleccionan.
    if (bloque.estado !== 'libre') return;

    const { tarifa } = tarifaParaBloque(
      tarifas,
      bloque.hora_inicio,
      horaInicioNoche,
    );

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

  const getClase = (bloque: Bloque, tarifa: InfoTarifa | null) => {
    // 'pasada' tiene prioridad: gris neutro, no rojo/amarillo.
    if (bloque.estado === 'pasada') {
      return 'bg-slate-100 border-slate-200 cursor-not-allowed opacity-60';
    }
    if (bloque.estado === 'ocupada') {
      return 'bg-red-50 border-red-200 cursor-not-allowed opacity-70';
    }
    if (bloque.estado === 'bloqueada_temporal') {
      // [estandar R/Y/G] 'en cobro' = amarillo (antes naranja).
      return 'bg-yellow-100 border-yellow-400 cursor-not-allowed opacity-90';
    }
    if (!tarifa) {
      return 'bg-slate-50 border-slate-200 cursor-not-allowed opacity-60';
    }
    if (estaSeleccionada(bloque)) {
      // [estandar R/Y/G] seleccionada = verde sólido (misma familia que 'disponible').
      return 'bg-emerald-600 text-white border-emerald-600 shadow-md scale-[1.02]';
    }

    // Libre sin seleccionar: verde (disponible) + hover. El tipo ya no pinta color.
    return clasesLibre();
  };

  const getTitle = (
    bloque: Bloque,
    tarifa: InfoTarifa | null,
    tipo: TipoTarifaVisible,
    seleccionado: boolean,
  ) => {
    if (bloque.estado === 'pasada') return 'Esta franja ya comenzó';
    if (bloque.estado === 'ocupada') return 'Esta franja ya está reservada';
    if (bloque.estado === 'bloqueada_temporal')
      return 'Alguien está completando el pago de esta franja. Si no confirma en unos minutos, vuelve a estar disponible.';
    // Libre CON tarifa: el tipo (que ya no ocupa texto en la celda) se descubre acá.
    if (bloque.estado === 'libre' && tarifa) {
      return `${LABEL_TIPO[tipo]} · ${seleccionado ? 'Seleccionada' : LABEL_ESTADO[bloque.estado]}`;
    }
    if (!tarifa && bloque.estado === 'libre') {
      return tipo === 'nocturna'
        ? 'Sin tarifa con iluminación definida'
        : tipo === 'unica'
          ? 'Sin tarifa única definida'
          : 'Sin tarifa regular definida';
    }
    return undefined;
  };

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5">
      {bloques.map((bloque) => {
        const { tarifa, tipo } = tarifaParaBloque(
          tarifas,
          bloque.hora_inicio,
          horaInicioNoche,
        );

        const Icono =
          tipo === 'nocturna' ? Lightbulb : tipo === 'unica' ? Clock : Sun;

        const seleccionado = estaSeleccionada(bloque);
        const horario = `${bloque.hora_inicio.slice(0, 5)} a ${bloque.hora_fin.slice(0, 5)}`;
        const ariaLabel = seleccionado
          ? `${horario}, seleccionada, Bs ${tarifa?.precio_por_hora.toFixed(0) ?? ''}. Tocá para quitar.`
          : `${horario}, ${LABEL_ESTADO[bloque.estado]}${tarifa ? `, Bs ${tarifa.precio_por_hora.toFixed(0)}` : ''}`;

        return (
          <button
            key={`${bloque.hora_inicio}-${bloque.hora_fin}`}
            type="button"
            onClick={() => toggleBloque(bloque)}
            disabled={bloque.estado !== 'libre' || !tarifa}
            aria-pressed={seleccionado}
            aria-label={ariaLabel}
            className={`relative p-3 border-2 rounded-xl text-center transition-all duration-200 ${getClase(
              bloque,
              tarifa,
            )}`}
            title={getTitle(bloque, tarifa, tipo, seleccionado)}
          >
            {/* Franja lateral de color: verde = disponible (el tipo ya no pinta color) */}
            {bloque.estado === 'libre' && tarifa && !seleccionado && (
              <span className="absolute top-2 left-2 w-1 h-6 rounded-full bg-emerald-400" />
            )}

            <div className="flex items-center justify-center gap-1.5 font-bold text-sm">
              {bloque.estado === 'libre' && (
                <Icono
                  className={`w-3.5 h-3.5 ${
                    seleccionado ? 'text-white' : 'text-emerald-600'
                  }`}
                />
              )}
              <span className={bloque.estado === 'pasada' ? 'text-slate-400' : ''}>
                {bloque.hora_inicio.slice(0, 5)}–{bloque.hora_fin.slice(0, 5)}
              </span>
            </div>

            <div className="text-xs mt-1.5">
              {bloque.estado === 'pasada' && (
                <span className="flex items-center justify-center gap-1 text-slate-500 font-medium">
                  <Clock className="w-3 h-3" />
                  Pasada
                </span>
              )}
              {bloque.estado === 'ocupada' && (
                <span className="flex items-center justify-center gap-1 text-red-700 font-medium">
                  <Lock className="w-3 h-3" />
                  Ocupada
                </span>
              )}
              {bloque.estado === 'bloqueada_temporal' && (
                <span className="flex items-center justify-center gap-1 text-yellow-800 font-medium">
                  <Hourglass className="w-3 h-3" />
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
                      seleccionado ? 'text-white/80' : 'text-emerald-700'
                    }`}
                  >
                    {seleccionado ? 'Seleccionada' : LABEL_ESTADO[bloque.estado]}
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
