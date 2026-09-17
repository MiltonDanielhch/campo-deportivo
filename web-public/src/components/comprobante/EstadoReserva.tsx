import { AlertTriangle, CheckCircle, Clock, TimerOff, XCircle } from 'lucide-react';

interface EstadoReservaProps {
  estado: string;
  montoTotal?: number;
  expiraEn?: string | null;
}

const CONFIG: Record<
  string,
  { icono: typeof Clock; clase: string; titulo: string; detalle: string }
> = {
  pendiente: {
    icono: Clock,
    clase: 'text-teal-700 bg-teal-50 border-teal-200',
    titulo: 'Pendiente de pago',
    detalle:
      'Tu solicitud está esperando el pago. Completalo antes de que venza el tiempo.',
  },
  confirmada: {
    icono: CheckCircle,
    clase: 'text-green-700 bg-green-50 border-green-200',
    titulo: 'Reserva confirmada',
    detalle: 'Tu pago fue confirmado. Acá están tus códigos de reserva.',
  },
  expirada: {
    icono: TimerOff,
    clase: 'text-red-700 bg-red-50 border-red-200',
    titulo: 'Solicitud expirada',
    detalle:
      'El tiempo para pagar venció. Podés volver a reservar las franjas que sigan libres.',
  },
  rechazada: {
    icono: AlertTriangle,
    clase: 'text-orange-700 bg-orange-50 border-orange-200',
    titulo: 'Pago rechazado',
    detalle:
      'El sistema de recaudaciones rechazó el intento de cobro. Intentá nuevamente.',
  },
  cancelada: {
    icono: XCircle,
    clase: 'text-gray-700 bg-gray-50 border-gray-200',
    titulo: 'Solicitud cancelada',
    detalle: 'Esta solicitud fue cancelada.',
  },
};

/**
 * Bloque de estado reutilizable (Comprobante y ConsultarEstado).
 */
export default function EstadoReserva({ estado, montoTotal, expiraEn }: EstadoReservaProps) {
  const config = CONFIG[estado] ?? CONFIG.cancelada;
  const Icono = config.icono;

  return (
    <div className={`border rounded-lg p-6 text-center ${config.clase}`}>
      <Icono className="w-12 h-12 mx-auto mb-3" />
      <h2 className="text-xl font-bold mb-1">{config.titulo}</h2>
      <p className="text-sm opacity-80">{config.detalle}</p>
      {montoTotal !== undefined && (
        <p className="mt-3 font-semibold">Monto: Bs {montoTotal.toFixed(2)}</p>
      )}
      {expiraEn && estado === 'pendiente' && (
        <p className="mt-1 text-xs opacity-70">
          Vence el: {new Date(expiraEn).toLocaleString('es-BO')}
        </p>
      )}
    </div>
  );
}
