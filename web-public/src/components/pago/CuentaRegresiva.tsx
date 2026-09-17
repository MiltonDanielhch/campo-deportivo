import { useEffect, useState } from 'react';

interface CuentaRegresivaProps {
  expiraEn: string; // ISO 8601 del servidor
  onExpirar: () => void;
}

export default function CuentaRegresiva({ expiraEn, onExpirar }: CuentaRegresivaProps) {
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
  const urgente = segundos <= 60;

  return (
    <div className="text-center mb-6">
      <p className="text-sm text-gray-600 mb-1">Tiempo restante para pagar</p>
      <p
        className={`text-5xl font-bold tabular-nums ${
          urgente ? 'text-red-600 animate-pulse' : 'text-teal-700'
        }`}
      >
        {mm}:{ss}
      </p>
    </div>
  );
}