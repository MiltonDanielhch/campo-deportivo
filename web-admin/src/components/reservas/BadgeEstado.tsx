import type { ElementType } from 'react';
import {
  AlertTriangle,
  Ban,
  CheckCircle2,
  CircleDot,
  Clock3,
  XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { EstadoReserva } from '@/types/reservas';

const estadoConfig: Record<
  EstadoReserva,
  { label: string; className: string; Icon: ElementType }
> = {
  pendiente: {
    label: 'Pendiente',
    className: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    Icon: Clock3,
  },
  confirmada: {
    label: 'Confirmada',
    className: 'bg-green-100 text-green-800 border-green-200',
    Icon: CheckCircle2,
  },
  expirada: {
    label: 'Expirada',
    className: 'bg-slate-100 text-slate-800 border-slate-200',
    Icon: XCircle,
  },
  cancelada: {
    label: 'Cancelada',
    className: 'bg-zinc-100 text-zinc-800 border-zinc-200',
    Icon: Ban,
  },
  rechazada: {
    label: 'Rechazada',
    className: 'bg-orange-100 text-orange-800 border-orange-200',
    Icon: AlertTriangle,
  },
};

export function BadgeEstado({ estado }: { estado: EstadoReserva }) {
  const config =
    estadoConfig[estado] ?? {
      label: estado,
      className: 'bg-slate-100 text-slate-800 border-slate-200',
      Icon: CircleDot,
    };

  const Icon = config.Icon;

  return (
    <Badge className={config.className}>
      <Icon className="mr-1 size-3" />
      {config.label}
    </Badge>
  );
}
