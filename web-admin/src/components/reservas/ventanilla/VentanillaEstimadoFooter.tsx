import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
  totalEstimado: number;
  cantidadFranjas: number;
  subtotalFranja: number;
  totalServicios: number;
  puedeEnviar: boolean;
  motivoDeshabilitado: string | null;
  enviando: boolean;
  onCancel: () => void;
  onSubmit: () => void | Promise<void>;
}

export function VentanillaEstimadoFooter({
  totalEstimado,
  cantidadFranjas,
  subtotalFranja,
  totalServicios,
  puedeEnviar,
  motivoDeshabilitado,
  enviando,
  onCancel,
  onSubmit,
}: Props) {
  return (
    <>
      <div className="rounded-xl border bg-card p-3">
        <div className="flex items-center justify-between gap-3">
          <span className="min-w-0 truncate text-xs text-muted-foreground">
            Estimado · el monto final lo confirma SIREB
          </span>
          <span className="shrink-0 text-lg font-bold tabular-nums">
            Bs {totalEstimado.toFixed(2)}
          </span>
        </div>

        <p className="mt-1 truncate text-xs text-muted-foreground">
          {cantidadFranjas > 0
            ? `${cantidadFranjas} franja${cantidadFranjas === 1 ? '' : 's'} · Bs ${subtotalFranja.toFixed(2)}`
            : 'Sin franja'}
          {' · '}
          Servicios Bs {totalServicios.toFixed(2)}
        </p>

        {!puedeEnviar && motivoDeshabilitado && (
          <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
            {motivoDeshabilitado}
          </p>
        )}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
        <Button variant="outline" onClick={onCancel} disabled={enviando}>
          Cancelar
        </Button>
        <Button onClick={onSubmit} disabled={!puedeEnviar || enviando}>
          {enviando && <Loader2 className="mr-2 size-4 animate-spin" />}
          Crear reserva y código de pago
        </Button>
      </div>
    </>
  );
}
