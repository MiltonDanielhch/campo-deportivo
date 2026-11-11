import { Loader2 } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { StepperCantidad } from './StepperCantidad';
import type { VarianteArancelaria, VarianteArancelariaTarifa } from '@/services/catalogoSirebService';

interface Props {
  serviciosAdicionales: VarianteArancelaria[];
  cargandoServicios: boolean;
  serviciosAdicionalesValidos: VarianteArancelaria[];
  serviciosSeleccionados: Record<string, number>;
  clasificacionAplicable: string | null;
  resolverTarifaServicio: (
    v: VarianteArancelaria,
    clasif: string | null,
  ) => VarianteArancelariaTarifa | null;
  toggleServicio: (vid: string, checked: boolean | 'indeterminate') => void;
  setCantidadServicio: (vid: string, valor: number) => void;
}

export function VentanillaServiciosSelector({
  serviciosAdicionales,
  cargandoServicios,
  serviciosAdicionalesValidos,
  serviciosSeleccionados,
  clasificacionAplicable,
  resolverTarifaServicio,
  toggleServicio,
  setCantidadServicio,
}: Props) {
  if (!cargandoServicios && serviciosAdicionales.length === 0) return null;

  return (
    <div className="space-y-2">
      <Label className="text-sm font-semibold">Servicios adicionales</Label>

      {cargandoServicios ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="size-4 animate-spin" />
          Cargando servicios del campo…
        </div>
      ) : serviciosAdicionalesValidos.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {serviciosAdicionales.length === 0
            ? 'Este campo no tiene servicios adicionales disponibles.'
            : 'Los servicios disponibles no tienen una tarifa única vigente.'}
        </p>
      ) : (
        <div className="space-y-2">
          {serviciosAdicionalesValidos.map((v) => {
            const cantidad = serviciosSeleccionados[v.id] ?? 0;
            const seleccionado = cantidad > 0;
            const tarifa = resolverTarifaServicio(v, clasificacionAplicable);
            if (!tarifa) return null;
            const codigo = v.servicio_sireb_codigo ?? v.codigo;
            const esTramo = v.tarifas.length > 1;

            return (
              <div
                key={v.id}
                className={`flex items-center justify-between gap-3 rounded-lg border p-3 transition-colors ${
                  seleccionado ? 'border-primary/40 bg-primary/5' : 'hover:border-primary/30'
                }`}
              >
                <div className="flex min-w-0 flex-1 items-start gap-3">
                  <Checkbox
                    id={v.id}
                    checked={seleccionado}
                    onCheckedChange={(checked) => toggleServicio(v.id, checked)}
                    className="mt-0.5 shrink-0"
                  />
                  <Label htmlFor={v.id} className="min-w-0 flex-1 cursor-pointer">
                    <span className="block truncate text-sm font-medium" title={v.nombre}>
                      {v.nombre}
                    </span>
                    <span
                      className="block truncate text-xs text-muted-foreground"
                      title={codigo}
                    >
                      {codigo}
                    </span>
                  </Label>
                </div>

                <div className="flex shrink-0 items-center gap-3">
                  <div className="w-28 text-right">
                    <span className="block text-sm font-semibold tabular-nums">
                      Bs {Number(tarifa.monto).toFixed(2)}
                    </span>
                    {esTramo && (
                      <span className="block text-[10px] leading-tight text-muted-foreground">
                        Diurno Bs{' '}
                        {Number(
                          v.tarifas.find((t) => t.clasificacion === 'diurna')?.monto ?? 0,
                        ).toFixed(2)}{' '}
                        · Nocturno Bs{' '}
                        {Number(
                          v.tarifas.find((t) => t.clasificacion === 'nocturna')?.monto ?? 0,
                        ).toFixed(2)}
                      </span>
                    )}
                  </div>
                  {seleccionado && (
                    <StepperCantidad
                      valor={cantidad}
                      onChange={(val) => setCantidadServicio(v.id, val)}
                      ariaLabel={v.nombre}
                    />
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
