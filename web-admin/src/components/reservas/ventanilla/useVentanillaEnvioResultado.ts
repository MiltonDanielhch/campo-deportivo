import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { SlotSel } from './useVentanillaSlots';

export interface ResultadoVentanilla {
  codigo_seguimiento: string;
  monto_total: number;
  referencia_recaudaciones: string | null;
  qr_string: string | null;
}

type ApiError = {
  response?: {
    data?: {
      message?: string;
      errors?: {
        franjas?: string[];
        servicios?: string[];
      };
    };
  };
};

export function useVentanillaEnvioResultado(params: {
  campoId: string;
  varianteId: string;
  fecha: string;
  slots: SlotSel[];
  serviciosSeleccionados: Record<string, number>;
  alumnosPorFranja: Record<string, string[]>;
  nombre: string;
  ciNit: string;
  telefono: string;
  requiereVariante: boolean;
  bloqueadoPorVariantesNoHorarias: boolean;
  onCreada: () => void;
}) {
  const {
    campoId,
    varianteId,
    fecha,
    slots,
    serviciosSeleccionados,
    alumnosPorFranja,
    nombre,
    ciNit,
    telefono,
    requiereVariante,
    bloqueadoPorVariantesNoHorarias,
    onCreada,
  } = params;

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<ResultadoVentanilla | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);

  const puedeEnviar =
    !!campoId &&
    !!fecha &&
    slots.length > 0 &&
    !bloqueadoPorVariantesNoHorarias &&
    (!requiereVariante || !!varianteId) &&
    !!nombre.trim() &&
    !!ciNit.trim();

  const motivoDeshabilitado = !campoId
    ? 'Elegí el campo.'
    : !fecha
      ? 'Elegí la fecha.'
      : slots.length === 0
        ? 'Elegí al menos una franja libre.'
        : bloqueadoPorVariantesNoHorarias
          ? 'Este campo no tiene variantes horarias reservables.'
          : requiereVariante && !varianteId
            ? 'Elegí la variante arancelaria.'
            : !nombre.trim()
              ? 'Completá el nombre del pagador.'
              : !ciNit.trim()
                ? 'Completá el CI/NIT del pagador.'
                : null;

  const enviar = useCallback(async () => {
    if (!puedeEnviar) return;

    const serviciosPayload = Object.entries(serviciosSeleccionados)
      .filter(([, cantidad]) => cantidad > 0)
      .map(([vid, cantidad]) => ({
        campo_id: campoId,
        variante_id: vid,
        cantidad,
      }));

    setEnviando(true);

    try {
      const res = await solicitudesReservaService.crearDesdeVentanilla({
        franjas: slots.map((o) => ({
          campo_id: campoId,
          ...(varianteId ? { variante_id: varianteId } : {}),
          fecha,
          hora_inicio: o.inicio,
          hora_fin: o.fin,
          ...((alumnosPorFranja[o.inicio] ?? []).length > 0
            ? { alumnos_ids: alumnosPorFranja[o.inicio] }
            : {}),
        })),
        ...(serviciosPayload.length > 0 ? { servicios: serviciosPayload } : {}),
        nombre_pagador: nombre.trim(),
        ci_nit_pagador: ciNit.trim(),
        telefono_pagador: telefono.trim() || undefined,
      });

      setResultado(res.data as ResultadoVentanilla);
      onCreada();
    } catch (error) {
      const apiError = error as ApiError;
      const errs = apiError.response?.data?.errors;
      const mensaje =
        Array.isArray(errs?.franjas)
          ? errs.franjas[0]
          : Array.isArray(errs?.servicios)
            ? errs.servicios[0]
            : apiError.response?.data?.message ?? 'No se pudo crear la reserva.';
      toast.error(mensaje);
    } finally {
      setEnviando(false);
    }
  }, [
    puedeEnviar,
    serviciosSeleccionados,
    campoId,
    slots,
    varianteId,
    fecha,
    alumnosPorFranja,
    nombre,
    ciNit,
    telefono,
    onCreada,
  ]);

  const copiar = useCallback(async (texto: string, clave: string) => {
    await navigator.clipboard.writeText(texto);
    setCopiado(clave);
    setTimeout(() => setCopiado(null), 1500);
  }, []);

  const descargarQr = useCallback(() => {
    const canvas = document.getElementById('qr-ventanilla') as HTMLCanvasElement | null;
    if (!canvas) return;

    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `qr-${resultado?.codigo_seguimiento ?? 'reserva'}.png`;
    a.click();
  }, [resultado]);

  const enviarWhatsapp = useCallback(() => {
    if (!resultado) return;

    const texto = encodeURIComponent(
      `Reserva ${resultado.codigo_seguimiento}\nMonto: Bs ${resultado.monto_total.toFixed(2)}\n` +
        `Código de pago: ${resultado.referencia_recaudaciones ?? ''}\n` +
        `Pagá con el código en ventanilla de cualquier banco (presentá tu CI/NIT) o escaneá el QR para pagar por Síntesis.`,
    );

    window.open(`https://wa.me/?text=${texto}`, '_blank');
  }, [resultado]);

  const resetResultado = useCallback(() => {
    setResultado(null);
    setCopiado(null);
    setEnviando(false);
  }, []);

  return {
    enviando,
    resultado,
    copiado,
    puedeEnviar,
    motivoDeshabilitado,
    enviar,
    copiar,
    descargarQr,
    enviarWhatsapp,
    resetResultado,
  };
}
