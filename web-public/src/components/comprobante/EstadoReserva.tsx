import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  TimerOff,
  XCircle,
  ArrowRight,
  Printer,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

interface EstadoReservaProps {
  estado: string;
  montoTotal?: number;
  expiraEn?: string | null;
  codigoSeguimiento?: string;
  /** Si es true, muestra CTAs contextuales (ir a pagar / ver comprobante) */
  mostrarAcciones?: boolean;
}

const CONFIG: Record<
  string,
  {
    icono: typeof Clock;
    iconBg: string;
    iconColor: string;
    cardBg: string;
    cardBorder: string;
    textColor: string;
    titulo: string;
    detalle: string;
  }
> = {
  pendiente: {
    icono: Clock,
    iconBg: 'bg-amber-100',
    iconColor: 'text-amber-600',
    cardBg: 'bg-gradient-to-br from-amber-50 to-orange-50',
    cardBorder: 'border-amber-200',
    textColor: 'text-amber-900',
    titulo: 'Pendiente de pago',
    detalle:
      'Tu solicitud está esperando el pago. Completalo antes de que venza el tiempo para no perder las franjas reservadas.',
  },
  confirmada: {
    icono: CheckCircle2,
    iconBg: 'bg-emerald-100',
    iconColor: 'text-emerald-600',
    cardBg: 'bg-gradient-to-br from-emerald-50 to-teal-50',
    cardBorder: 'border-emerald-200',
    textColor: 'text-emerald-900',
    titulo: 'Reserva confirmada',
    detalle:
      'Tu pago fue confirmado. Presentá los códigos de reserva al llegar a la cancha.',
  },
  expirada: {
    icono: TimerOff,
    iconBg: 'bg-red-100',
    iconColor: 'text-red-600',
    cardBg: 'bg-gradient-to-br from-red-50 to-rose-50',
    cardBorder: 'border-red-200',
    textColor: 'text-red-900',
    titulo: 'Solicitud expirada',
    detalle:
      'El tiempo para pagar venció. Las franjas volvieron a estar disponibles: podés reservar de nuevo las que quieras.',
  },
  rechazada: {
    icono: AlertTriangle,
    iconBg: 'bg-orange-100',
    iconColor: 'text-orange-600',
    cardBg: 'bg-gradient-to-br from-orange-50 to-amber-50',
    cardBorder: 'border-orange-200',
    textColor: 'text-orange-900',
    titulo: 'Pago rechazado',
    detalle:
      'El sistema de recaudaciones rechazó el intento de cobro. Verificá los datos con tu banco e intentá nuevamente.',
  },
  cancelada: {
    icono: XCircle,
    iconBg: 'bg-slate-100',
    iconColor: 'text-slate-600',
    cardBg: 'bg-gradient-to-br from-slate-50 to-gray-50',
    cardBorder: 'border-slate-200',
    textColor: 'text-slate-900',
    titulo: 'Solicitud cancelada',
    detalle: 'Esta solicitud fue cancelada.',
  },
};

export default function EstadoReserva({
  estado,
  montoTotal,
  expiraEn,
  codigoSeguimiento,
  mostrarAcciones = false,
}: EstadoReservaProps) {
  const config = CONFIG[estado] ?? CONFIG.cancelada;
  const Icono = config.icono;

  return (
    <div
      className={`relative overflow-hidden rounded-2xl border-2 p-6 md:p-8 ${config.cardBg} ${config.cardBorder}`}
    >
      {/* Icono grande con halo */}
      <div className="flex flex-col md:flex-row md:items-center gap-5">
        <div
          className={`w-16 h-16 md:w-20 md:h-16 rounded-2xl ${config.iconBg} flex items-center justify-center flex-shrink-0 shadow-sm`}
        >
          <Icono className={`w-8 h-8 md:w-10 md:h-8 ${config.iconColor}`} />
        </div>

        <div className="flex-1 min-w-0">
          <h2 className={`text-xl md:text-2xl font-bold mb-1 ${config.textColor}`}>
            {config.titulo}
          </h2>
          <p className="text-sm text-slate-600 leading-relaxed">
            {config.detalle}
          </p>

          {/* Datos rápidos */}
          <div className="flex flex-wrap gap-4 mt-3 text-sm">
            {montoTotal !== undefined && (
              <div className="flex items-center gap-1.5">
                <span className="text-slate-500">Monto:</span>
                <span className="font-bold text-slate-900 tabular-nums">
                  Bs {montoTotal.toFixed(2)}
                </span>
              </div>
            )}
            {expiraEn && estado === 'pendiente' && (
              <div className="flex items-center gap-1.5">
                <span className="text-slate-500">Vence:</span>
                <span className="font-semibold text-slate-900">
                  {new Date(expiraEn).toLocaleString('es-BO', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                  })}
                </span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* CTAs contextuales */}
      {mostrarAcciones && (
        <div className="mt-6 pt-6 border-t border-white/60 flex flex-col sm:flex-row gap-2">
          {estado === 'pendiente' && codigoSeguimiento && (
            <Button asChild className="rounded-full">
              <Link
                to={`/pago/${codigoSeguimiento}`}
                className="flex items-center justify-center gap-2"
              >
                Ir a pagar ahora
                <ArrowRight className="w-4 h-4" />
              </Link>
            </Button>
          )}
          {estado === 'confirmada' && codigoSeguimiento && (
            <Button asChild className="rounded-full">
              <Link
                to={`/comprobante/${codigoSeguimiento}`}
                className="flex items-center justify-center gap-2"
              >
                <Printer className="w-4 h-4" />
                Ver comprobante
              </Link>
            </Button>
          )}
          {(estado === 'expirada' || estado === 'rechazada') && (
            <Button asChild className="rounded-full">
              <Link
                to="/campos"
                className="flex items-center justify-center gap-2"
              >
                Reservar de nuevo
                <ArrowRight className="w-4 h-4" />
              </Link>
            </Button>
          )}
        </div>
      )}
    </div>
  );
}
