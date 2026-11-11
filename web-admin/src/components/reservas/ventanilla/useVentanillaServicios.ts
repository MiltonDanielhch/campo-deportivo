import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  catalogoSirebService,
  type VarianteArancelaria,
} from '@/services/catalogoSirebService';

export function useVentanillaServicios(campoId: string, open: boolean) {
  const [serviciosAdicionales, setServiciosAdicionales] = useState<VarianteArancelaria[]>([]);
  const [cargandoServicios, setCargandoServicios] = useState(false);
  const [serviciosSeleccionados, setServiciosSeleccionados] = useState<Record<string, number>>({});

  useEffect(() => {
    if (!open || !campoId) {
      setServiciosAdicionales([]);
      setServiciosSeleccionados({});
      return;
    }
    let activo = true;
    setCargandoServicios(true);
    catalogoSirebService
      .listarServiciosAdicionalesPorCampo(campoId)
      .then((lista) => {
        if (!activo) return;
        setServiciosAdicionales(lista);
        setServiciosSeleccionados((prev) => {
          const validos = new Set(
            lista
              .filter((v) => Boolean(v.servicio_sireb_id) && v.tarifas.length === 1)
              .map((v) => v.id),
          );
          const next: Record<string, number> = {};
          for (const [id, cantidad] of Object.entries(prev)) {
            if (validos.has(id)) next[id] = cantidad;
          }
          return next;
        });
      })
      .catch(() => {
        if (!activo) return;
        setServiciosAdicionales([]);
        setServiciosSeleccionados({});
        toast.error('No se pudieron cargar los servicios adicionales.');
      })
      .finally(() => {
        if (activo) setCargandoServicios(false);
      });
    return () => {
      activo = false;
    };
  }, [open, campoId]);

  const serviciosAdicionalesValidos = useMemo(
    () =>
      serviciosAdicionales.filter((v) => {
        if (!v.servicio_sireb_id) return false;
        const t = v.tarifas;
        if (t.length === 0) return false;
        if (t.length === 1) return true;
        return t.some(
          (x) =>
            x.clasificacion === 'unica' ||
            x.clasificacion === 'diurna' ||
            x.clasificacion === 'nocturna',
        );
      }),
    [serviciosAdicionales],
  );

  const toggleServicio = useCallback((vid: string, checked: boolean | 'indeterminate') => {
    if (checked === true) {
      setServiciosSeleccionados((prev) => ({ ...prev, [vid]: prev[vid] ?? 1 }));
    } else {
      setServiciosSeleccionados((prev) => {
        const next = { ...prev };
        delete next[vid];
        return next;
      });
    }
  }, []);

  const setCantidadServicio = useCallback((vid: string, valor: number) => {
    const cantidad = Number.isFinite(valor)
      ? Math.max(1, Math.min(100, Math.floor(valor)))
      : 1;
    setServiciosSeleccionados((prev) => ({ ...prev, [vid]: cantidad }));
  }, []);

  const reset = () => {
    setServiciosAdicionales([]);
    setServiciosSeleccionados({});
  };

  return {
    serviciosAdicionales,
    cargandoServicios,
    serviciosSeleccionados,
    serviciosAdicionalesValidos,
    toggleServicio,
    setCantidadServicio,
    reset,
  };
}
