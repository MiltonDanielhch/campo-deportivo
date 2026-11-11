import { Loader2, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { hhmm } from './utils';
import type { Bloque, SlotSel } from './useVentanillaSlots';

interface Props {
  campoId: string;
  fecha: string;
  bloques: Bloque[];
  cargandoSlots: boolean;
  slots: SlotSel[];
  toggleSlot: (b: Bloque) => void;
  horaAmedida: string;
  setHoraAmedida: (v: string) => void;
  toggleAmedida: (hora: string) => void;
  slotsAmedida: SlotSel[];
}

export function VentanillaFranjasSelector({
  campoId,
  fecha,
  bloques,
  cargandoSlots,
  slots,
  toggleSlot,
  horaAmedida,
  setHoraAmedida,
  toggleAmedida,
  slotsAmedida,
}: Props) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <Label>Franjas libres *</Label>
        {cargandoSlots && <Loader2 className="size-4 animate-spin text-muted-foreground" />}
      </div>

      {!campoId || !fecha ? (
        <p className="text-sm text-muted-foreground">Elegí campo y fecha para ver horarios.</p>
      ) : (
        <>
          {bloques.length === 0 && !cargandoSlots && (
            <p className="text-sm text-muted-foreground">Sin franjas libres ese día.</p>
          )}

          {bloques.length > 0 && (
            <div className="flex flex-wrap gap-2">
              {bloques.map((b) => {
                const activo = slots.some((s) => s.inicio === b.hora_inicio);
                const seleccionable = b.estado === 'libre';
                const etiqueta =
                  b.estado === 'ocupada'
                    ? 'Ocupada'
                    : b.estado === 'bloqueada_temporal'
                      ? 'En cobro'
                      : b.estado === 'pasada'
                        ? 'Pasada'
                        : b.estado;
                return (
                  <Button
                    key={b.hora_inicio}
                    type="button"
                    variant={activo ? 'default' : seleccionable ? 'outline' : 'ghost'}
                    size="sm"
                    disabled={!seleccionable}
                    title={
                      seleccionable
                        ? undefined
                        : `${etiqueta} — no disponible para vender`
                    }
                    className={
                      seleccionable
                        ? ''
                        : 'pointer-events-none opacity-50 line-through decoration-muted-foreground/40'
                    }
                    onClick={() => toggleSlot(b)}
                  >
                    {hhmm(b.hora_inicio)}–{hhmm(b.hora_fin)}
                    {!seleccionable && (
                      <span className="ml-1.5 text-[10px] font-normal">
                        {etiqueta}
                      </span>
                    )}
                  </Button>
                );
              })}
            </div>
          )}

          {/* ── Opción B: franja a medida (inicio libre + 1 hora) ── */}
          <div className="space-y-2 rounded-lg border border-dashed bg-muted/30 p-3">
            <Label className="text-xs font-semibold text-muted-foreground">
              Otra franja a medida (inicio + 1 hora)
            </Label>
            <div className="flex items-center gap-2">
              <Input
                type="time"
                step={1800}
                value={horaAmedida}
                onChange={(e) => setHoraAmedida(e.target.value)}
                className="w-32"
                disabled={!campoId || !fecha}
                aria-label="Hora de inicio a medida"
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={!horaAmedida || !campoId || !fecha}
                onClick={() => {
                  toggleAmedida(horaAmedida);
                  setHoraAmedida('');
                }}
              >
                <Plus className="mr-1 size-4" /> Agregar
              </Button>
            </div>

            {slotsAmedida.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {slotsAmedida.map((o) => (
                  <Button
                    key={o.inicio}
                    type="button"
                    variant="default"
                    size="sm"
                    onClick={() => toggleAmedida(o.inicio.slice(0, 5))}
                  >
                    {hhmm(o.inicio)}–{hhmm(o.fin)}
                  </Button>
                ))}
              </div>
            )}

            <p className="text-[11px] leading-snug text-muted-foreground">
              Para horarios que no están en la grilla (ej. 08:30). Se cobra como 1 hora,
              con el tramo (diurno/nocturno) de su hora de inicio.
              El solape con otras franjas lo valida el sistema al crear.
            </p>
            {/* [D42] el funcionario no busca el botón de cobro atrasado acá. */}
            <p className="text-[11px] leading-snug text-amber-700 dark:text-amber-400">
              Las franjas ya pasadas no se venden desde acá. Si alguien usó la cancha y no
              pudo pagar (caída del sistema), cobralo desde <strong>Regularizar uso</strong>{' '}
              en el listado de Reservas.
            </p>
          </div>
        </>
      )}
    </div>
  );
}
