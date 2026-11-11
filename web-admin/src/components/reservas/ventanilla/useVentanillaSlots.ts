import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';

export interface Bloque {
  hora_inicio: string;
  hora_fin: string;
  estado: string;
  precio?: number | null;
}

export interface SlotSel {
  inicio: string;
  fin: string;
  origen: 'grilla' | 'amedida';
}

function sumarHora(hhmmStr: string): string {
  const [h, m] = hhmmStr.split(':').map(Number);
  const total = (h || 0) * 60 + (m || 0) + 60;
  const nh = Math.floor(total / 60) % 24;
  const nm = total % 60;
  return `${String(nh).padStart(2, '0')}:${String(nm).padStart(2, '0')}`;
}

export function useVentanillaSlots(open: boolean, campoId: string) {
  const [fecha, setFecha] = useState('');
  const [bloques, setBloques] = useState<Bloque[]>([]);
  const [cargandoSlots, setCargandoSlots] = useState(false);
  const [slots, setSlots] = useState<SlotSel[]>([]);
  const [horaAmedida, setHoraAmedida] = useState('');
  const [horaInicioNocheGrilla, setHoraInicioNocheGrilla] = useState<string | null>(null);

  const cargarSlots = useCallback(async () => {
    if (!open || !campoId || !fecha) return;

    setCargandoSlots(true);
    setSlots([]);

    try {
      const disp = await solicitudesReservaService.disponibilidad(campoId, fecha);

      // [FIX B79] leo el corte real de la grilla.
      setHoraInicioNocheGrilla((disp as any).hora_inicio_noche ?? null);
      setBloques(disp.abierto ? disp.bloques : []);
    } catch {
      setBloques([]);
      setHoraInicioNocheGrilla(null);
      toast.error('No se pudo consultar la disponibilidad del campo.');
    } finally {
      setCargandoSlots(false);
    }
  }, [open, campoId, fecha]);

  useEffect(() => {
    cargarSlots();
  }, [cargarSlots]);

  const toggleSlot = useCallback((b: Bloque) => {
    setSlots((prev) =>
      prev.some((s) => s.inicio === b.hora_inicio)
        ? prev.filter((s) => s.inicio !== b.hora_inicio)
        : [
            ...prev,
            {
              inicio: b.hora_inicio,
              fin: b.hora_fin,
              origen: 'grilla' as const,
            },
          ].sort((a, z) => a.inicio.localeCompare(z.inicio)),
    );
  }, []);

  const toggleAmedida = useCallback((hora: string) => {
    const inicio = `${hora}:00`;

    setSlots((prev) => {
      if (prev.some((s) => s.inicio === inicio)) {
        return prev.filter((s) => s.inicio !== inicio);
      }

      const fin = `${sumarHora(hora)}:00`;

      return [
        ...prev,
        {
          inicio,
          fin,
          origen: 'amedida' as const,
        },
      ].sort((a, z) => a.inicio.localeCompare(z.inicio));
    });
  }, []);

  const slotsAmedida = useMemo(
    () => slots.filter((s) => s.origen === 'amedida'),
    [slots],
  );

  const limpiarSlots = useCallback(() => {
    setSlots([]);
    setHoraAmedida('');
    setBloques([]);
    setHoraInicioNocheGrilla(null);
  }, []);

  const reset = useCallback(() => {
    setFecha('');
    limpiarSlots();
  }, [limpiarSlots]);

  return {
    fecha,
    setFecha,
    bloques,
    cargandoSlots,
    slots,
    toggleSlot,
    horaAmedida,
    setHoraAmedida,
    toggleAmedida,
    slotsAmedida,
    horaInicioNocheGrilla,
    limpiarSlots,
    reset,
  };
}
