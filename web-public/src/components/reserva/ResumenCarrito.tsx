import { Calendar, Clock, Trash2, MapPin } from 'lucide-react';
import { useCarritoStore } from '@/store/carritoStore';

const DIAS_SEMANA = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const MESES = [
  'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
  'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic',
];

function formatearFecha(fechaISO: string): string {
  const [y, m, d] = fechaISO.split('-').map(Number);
  const fecha = new Date(y, m - 1, d);
  const dia = DIAS_SEMANA[fecha.getDay()];
  return `${dia} ${d} ${MESES[m - 1]}`;
}

interface ResumenCarritoProps {
  compacto?: boolean;
}

export default function ResumenCarrito({ compacto = false }: ResumenCarritoProps) {
  const { franjas, quitarFranja, calcularTotal } = useCarritoStore();

  if (franjas.length === 0) return null;

  // Agrupar franjas por campo
  const agrupadas = franjas.reduce<Record<string, typeof franjas>>((acc, f) => {
    (acc[f.campoId] = acc[f.campoId] || []).push(f);
    return acc;
  }, {});

  const total = calcularTotal();

  return (
    <div className="space-y-3">
      {Object.entries(agrupadas).map(([campoId, items]) => (
        <div
          key={campoId}
          className="bg-slate-50 rounded-xl p-4 border border-slate-100"
        >
          {/* Header del grupo */}
          <div className="flex items-start gap-2 mb-3">
            <div className="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0">
              <MapPin className="w-4 h-4 text-teal-700" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="font-bold text-slate-900 leading-tight">
                {items[0].campoNombre}
              </p>
              <p className="text-xs text-slate-500">
                {items.length} {items.length === 1 ? 'franja' : 'franjas'}
              </p>
            </div>
          </div>

          {/* Lista de franjas del grupo */}
          <div className="space-y-2 pl-10">
            {items.map((franja) => (
              <div
                key={`${franja.campoId}-${franja.fecha}-${franja.horaInicio}`}
                className="flex items-center justify-between gap-2 bg-white rounded-lg p-2.5 border border-slate-100"
              >
                <div className="flex items-center gap-3 min-w-0 flex-1">
                  <div className="flex flex-col flex-shrink-0">
                    <div className="flex items-center gap-1 text-xs text-slate-500">
                      <Calendar className="w-3 h-3" />
                      <span>{formatearFecha(franja.fecha)}</span>
                    </div>
                    <div className="flex items-center gap-1 text-sm font-semibold text-slate-900 mt-0.5">
                      <Clock className="w-3.5 h-3.5 text-slate-400" />
                      <span>
                        {franja.horaInicio.slice(0, 5)}–{franja.horaFin.slice(0, 5)}
                      </span>
                    </div>
                  </div>
                  <div className="flex-1 min-w-0 text-right">
                    <p className="font-bold text-slate-900">
                      Bs {franja.precio?.toFixed(2) ?? '—'}
                    </p>
                  </div>
                </div>

                {!compacto && (
                  <button
                    onClick={() => quitarFranja(franja)}
                    className="p-1.5 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors flex-shrink-0"
                    aria-label="Quitar franja"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* Total */}
      <div className="pt-4 mt-4 border-t-2 border-dashed border-slate-200">
        <div className="flex justify-between items-center">
          <span className="text-sm text-slate-600">
            {franjas.length} {franjas.length === 1 ? 'franja' : 'franjas'}
          </span>
          <div className="text-right">
            <p className="text-xs text-slate-500 uppercase tracking-wide">Total</p>
            <p className="text-2xl font-bold text-slate-900 tabular-nums">
              Bs {total.toFixed(2)}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
