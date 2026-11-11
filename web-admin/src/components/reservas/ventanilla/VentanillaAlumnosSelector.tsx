import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { hhmm } from './utils';
import type { SlotSel } from './useVentanillaSlots';
import type { AlumnoVentanilla } from '@/services/asociacionesService';
import type { Asociacion } from '@/types/asociaciones';

interface Props {
  asociacionDetectada: Asociacion | null;
  slots: SlotSel[];
  cargandoAlumnos: boolean;
  alumnos: AlumnoVentanilla[];
  alumnosPorFranja: Record<string, string[]>;
  toggleAlumnoFranja: (inicio: string, alumnoId: string) => void;
}

export function VentanillaAlumnosSelector({
  asociacionDetectada,
  slots,
  cargandoAlumnos,
  alumnos,
  alumnosPorFranja,
  toggleAlumnoFranja,
}: Props) {
  if (!asociacionDetectada?.vigente || slots.length === 0) return null;
  if (!cargandoAlumnos && alumnos.length === 0) return null;

  return (
    <div className="space-y-3 rounded-xl border bg-muted/30 p-3">
      <Label className="text-sm font-semibold">Alumnos por franja</Label>
      {cargandoAlumnos ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="size-4 animate-spin" />
          Cargando alumnos de la asociación…
        </div>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">
            Marcá quiénes asisten a cada franja. La lista la cargó el presidente de la asociación.
          </p>
          {slots.map((o) => (
            <div key={o.inicio} className="space-y-1.5">
              <p className="text-xs font-medium tabular-nums">
                {hhmm(o.inicio)}–{hhmm(o.fin)}
              </p>
              <div className="flex flex-wrap gap-1.5">
                {alumnos.map((al) => {
                  const activo = (alumnosPorFranja[o.inicio] ?? []).includes(al.id);
                  return (
                    <Button
                      key={al.id}
                      type="button"
                      variant={activo ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => toggleAlumnoFranja(o.inicio, al.id)}
                      title={`CI ${al.ci}`}
                    >
                      {al.nombre_completo}
                    </Button>
                  );
                })}
              </div>
            </div>
          ))}
        </>
      )}
    </div>
  );
}
