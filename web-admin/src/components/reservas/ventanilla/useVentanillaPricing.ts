import { useMemo } from 'react';
import type {
  VarianteArancelaria,
  VarianteArancelariaTarifa,
} from '@/services/catalogoSirebService';
import type { Bloque, SlotSel } from './useVentanillaSlots';

// [FIX B79] parsea "HH:MM[:SS]" a segundos, espejo de DisponibilidadService::aSegundos.
function aSegundosHora(h: string): number {
  const [hh, mm, ss] = (h || '').split(':').map(Number);
  return (hh || 0) * 3600 + (mm || 0) * 60 + (ss || 0);
}

function resolverTarifaServicio(
  v: VarianteArancelaria,
  clasif: string | null,
): VarianteArancelariaTarifa | null {
  const t = v.tarifas;
  const unica = t.find((x) => x.clasificacion === 'unica');
  if (unica) return unica;
  if (t.length === 1) return t[0];
  if (clasif) {
    const por = t.find((x) => x.clasificacion === clasif);
    if (por) return por;
  }
  return null;
}

export function useVentanillaPricing(params: {
  slots: SlotSel[];
  bloques: Bloque[];
  horaInicioNocheGrilla: string | null;
  varianteSeleccionada: VarianteArancelaria | null;
  serviciosSeleccionados: Record<string, number>;
  serviciosAdicionalesValidos: VarianteArancelaria[];
}) {
  const {
    slots,
    bloques,
    horaInicioNocheGrilla,
    varianteSeleccionada,
    serviciosSeleccionados,
    serviciosAdicionalesValidos,
  } = params;

  // [FIX B79] segundos del corte nocturno, con default 18:00:00 si la grilla no lo manda.
  const corteNocheSeg = useMemo(
    () => aSegundosHora(horaInicioNocheGrilla ?? '18:00:00'),
    [horaInicioNocheGrilla],
  );

  const clasificacionAplicable = useMemo(() => {
    if (slots.length === 0) return null;
    return aSegundosHora(slots[0].inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
  }, [slots, corteNocheSeg]);

  const totalServicios = useMemo(() => {
    return Object.entries(serviciosSeleccionados).reduce((acc, [id, cantidad]) => {
      const variante = serviciosAdicionalesValidos.find((v) => v.id === id);
      if (!variante) return acc;
      const tarifa = resolverTarifaServicio(variante, clasificacionAplicable);
      if (!tarifa) return acc;
      return acc + Number(tarifa.monto) * Math.max(1, Number(cantidad) || 1);
    }, 0);
  }, [serviciosSeleccionados, serviciosAdicionalesValidos, clasificacionAplicable]);

  const subtotalFranja = useMemo(() => {
    if (slots.length === 0) return 0;

    if (varianteSeleccionada) {
      const tarifas = varianteSeleccionada.tarifas;
      const unica = tarifas.find((t) => t.clasificacion === 'unica');

      const precioDe = (inicio: string): number => {
        if (unica) return Number(unica.monto);
        const tipo = aSegundosHora(inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
        const t = tarifas.find((x) => x.clasificacion === tipo);
        return t ? Number(t.monto) : 0;
      };

      return slots.reduce((acc, o) => acc + precioDe(o.inicio), 0);
    }

    const precioTramo = (inicio: string): number => {
      const enGrilla = bloques.find((b) => b.hora_inicio === inicio);
      if (enGrilla?.precio != null) return Number(enGrilla.precio);
      const tipo = aSegundosHora(inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
      const ref = bloques.find((b) => {
        const bt = aSegundosHora(b.hora_inicio) < corteNocheSeg ? 'diurna' : 'nocturna';
        return bt === tipo && b.precio != null;
      });
      return ref?.precio != null ? Number(ref.precio) : 0;
    };
    return slots.reduce((acc, o) => acc + precioTramo(o.inicio), 0);
  }, [varianteSeleccionada, slots, clasificacionAplicable, bloques, corteNocheSeg]);

  const totalEstimado = subtotalFranja + totalServicios;

  return {
    clasificacionAplicable,
    totalServicios,
    subtotalFranja,
    totalEstimado,
    resolverTarifaServicio,
  };
}
