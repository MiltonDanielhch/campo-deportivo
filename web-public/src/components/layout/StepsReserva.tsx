import { Check, MapPin, User, CreditCard } from 'lucide-react';

export type PasoReserva = 1 | 2 | 3;

interface StepsReservaProps {
  pasoActual: PasoReserva;
}

const PASOS = [
  { num: 1, label: 'Elegir canchas', icon: MapPin },
  { num: 2, label: 'Tus datos', icon: User },
  { num: 3, label: 'Pago', icon: CreditCard },
] as const;

export default function StepsReserva({ pasoActual }: StepsReservaProps) {
  return (
    <div className="mb-8">
      <div className="flex items-center justify-between max-w-2xl mx-auto">
        {PASOS.map((paso, i) => {
          const completado = paso.num < pasoActual;
          const activo = paso.num === pasoActual;
          const Icono = paso.icon;

          return (
            <div key={paso.num} className="flex items-center flex-1 last:flex-none">
              {/* Nodo */}
              <div className="flex flex-col items-center flex-shrink-0">
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all ${
                    completado
                      ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/30'
                      : activo
                        ? 'bg-teal-600 text-white shadow-lg shadow-teal-500/30 ring-4 ring-teal-100'
                        : 'bg-slate-100 text-slate-400 border-2 border-slate-200'
                  }`}
                >
                  {completado ? <Check className="w-5 h-5" /> : <Icono className="w-4 h-4" />}
                </div>
                <span
                  className={`mt-2 text-xs font-medium text-center whitespace-nowrap ${
                    completado
                      ? 'text-emerald-700'
                      : activo
                        ? 'text-teal-700 font-semibold'
                        : 'text-slate-400'
                  }`}
                >
                  {paso.label}
                </span>
              </div>

              {/* Línea conectora */}
              {i < PASOS.length - 1 && (
                <div
                  className={`h-0.5 flex-1 mx-2 mt-[-1rem] transition-colors ${
                    paso.num < pasoActual ? 'bg-emerald-500' : 'bg-slate-200'
                  }`}
                />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
