import { Check, Loader2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
} from '@/components/ui/select';
import type { VarianteArancelaria } from '@/services/catalogoSirebService';
import type { Asociacion } from '@/types/asociaciones';

interface Props {
  cargandoVariantes: boolean;
  tieneVariantes: boolean;
  requiereVariante: boolean;
  bloqueadoPorVariantesNoHorarias: boolean;
  varianteId: string;
  varianteNombre: string;
  variantesHorarias: VarianteArancelaria[];
  varianteSeleccionada: VarianteArancelaria | null;
  asociacionDetectada: Asociacion | null;
  clasificacionAplicable: string | null;
  haySlots: boolean;
  onChange: (id: string) => void;
}

export function VentanillaVarianteSelector({
  cargandoVariantes,
  tieneVariantes,
  requiereVariante,
  bloqueadoPorVariantesNoHorarias,
  varianteId,
  varianteNombre,
  variantesHorarias,
  varianteSeleccionada,
  asociacionDetectada,
  clasificacionAplicable,
  haySlots,
  onChange,
}: Props) {
  if (!cargandoVariantes && !tieneVariantes) return null;

  return (
    <div className="space-y-2">
      <Label className="flex items-center gap-2">
        Variante arancelaria
        {requiereVariante && <span className="text-destructive">*</span>}
      </Label>

      {cargandoVariantes ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="size-4 animate-spin" />
          Cargando variantes del campo…
        </div>
      ) : bloqueadoPorVariantesNoHorarias ? (
        <p className="rounded-lg border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
          Este campo tiene variantes arancelarias, pero ninguna es por hora.
          Esta ventanilla por ahora solo reserva franjas horarias.
        </p>
      ) : (
        <>
          <Select
            value={varianteId}
            onValueChange={onChange}
            disabled={!requiereVariante}
          >
            <SelectTrigger className="w-full" aria-label="Variante arancelaria">
              <span className="flex min-w-0 items-center gap-2 text-left">
                <span className={`truncate ${varianteNombre ? '' : 'text-muted-foreground'}`}>
                  {varianteNombre || 'Elegí la variante arancelaria'}
                </span>
              </span>
            </SelectTrigger>
            <SelectContent>
              {variantesHorarias.length === 0 ? (
                <div className="px-2 py-1.5 text-sm text-muted-foreground">
                  Sin variantes horarias disponibles
                </div>
              ) : (
                variantesHorarias.map((v) => {
                  const full = `${v.nombre}${v.servicio_sireb_codigo ? ` · ${v.servicio_sireb_codigo}` : ''}`;
                  return (
                    <SelectItem key={v.id} value={v.id}>
                      <span className="block truncate" title={full}>
                        {full}
                      </span>
                    </SelectItem>
                  );
                })
              )}
            </SelectContent>
          </Select>

          {asociacionDetectada?.vigente && varianteSeleccionada?.tipo_asociacion && (
            <p className="flex items-center gap-1.5 pt-1 text-xs text-emerald-700 dark:text-emerald-400">
              <Check className="size-3.5 shrink-0" />
              Convenio {varianteSeleccionada.tipo_asociacion} · {asociacionDetectada.nombre}
            </p>
          )}

          {varianteSeleccionada?.tarifas?.length ? (
            <div className="flex flex-wrap gap-2 pt-1">
              {varianteSeleccionada.tarifas.map((t) => {
                const aplicable =
                  haySlots &&
                  (t.clasificacion === 'unica' ||
                    t.clasificacion === clasificacionAplicable);
                return (
                  <Badge
                    key={t.tarifa_sireb_id}
                    variant={aplicable ? 'default' : 'secondary'}
                    className="gap-1.5"
                  >
                    <span className="text-xs">
                      {t.etiqueta ?? t.clasificacion ?? 'Tarifa'}
                    </span>
                    <span className="text-xs font-semibold tabular-nums">
                      Bs {Number(t.monto).toFixed(2)}
                    </span>
                  </Badge>
                );
              })}
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}
