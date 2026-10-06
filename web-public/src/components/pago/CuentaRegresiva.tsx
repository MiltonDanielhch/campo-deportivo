import { useEffect, useState } from 'react';
import { Clock, AlertCircle } from 'lucide-react';

interface CuentaRegresivaProps {
  expiraEn: string; // ISO 8601 del servidor
  onExpirar: () => void;
}

export default function CuentaRegresiva({
  expiraEn,
  onExpirar,
}: CuentaRegresivaProps) {
  const [segundos, setSegundos] = useState<number>(() => {
    const restante = new Date(expiraEn).getTime() - Date.now();
    return Math.max(0, Math.floor(restante / 1000));
  });

  useEffect(() => {
    const interval = setInterval(() => {
      const restante = new Date(expiraEn).getTime() - Date.now();
      const s = Math.max(0, Math.floor(restante / 1000));
      setSegundos(s);
      if (s <= 0) {
        clearInterval(interval);
        onExpirar();
      }
    }, 1000);
    return () => clearInterval(interval);
  }, [expiraEn, onExpirar]);

  const mm = String(Math.floor(segundos / 60)).padStart(2, '0');
  const ss = String(segundos % 60).padStart(2, '0');

  // Niveles de urgencia
  const critico = segundos <= 60;       // < 1 min
  const precaucion = segundos <= 300;   // < 5 min

  const colorTexto = critico
    ? 'text-red-600'
    : precaucion
      ? 'text-amber-600'
      : 'text-teal-700';

  const colorIcono = critico
    ? 'bg-red-100 text-red-600'
    : precaucion
      ? 'bg-amber-100 text-amber-600'
      : 'bg-teal-100 text-teal-700';

  const colorFondo = critico
    ? 'bg-red-50 border-red-200'
    : precaucion
      ? 'bg-amber-50 border-amber-200'
      : 'bg-white border-slate-200';

  const mensaje = critico
    ? '¡Último minuto para pagar!'
    : precaucion
      ? 'Te quedan pocos minutos'
      : 'Tiempo restante para pagar';

  return (
    <div
      className={`rounded-2xl border-2 p-5 text-center transition-all ${colorFondo} ${
        critico ? 'animate-pulse' : ''
      }`}
    >
      <div className="flex items-center justify-center gap-2 mb-2">
        <div className={`w-7 h-7 rounded-lg flex items-center justify-center ${colorIcono}`}>
          {critico ? (
            <AlertCircle className="w-4 h-4" />
          ) : (
            <Clock className="w-4 h-4" />
          )}
        </div>
        <p className={`text-xs font-semibold uppercase tracking-widest ${colorTexto}`}>
          {mensaje}
        </p>
      </div>

      <p
        className={`text-5xl md:text-6xl font-bold tabular-nums tracking-tight ${colorTexto}`}
      >
        {mm}:{ss}
      </p>

      {critico && (
        <p className="text-xs text-red-700 mt-2 font-medium">
          El QR dejará de funcionar en breve
        </p>
      )}
    </div>
  );
}
