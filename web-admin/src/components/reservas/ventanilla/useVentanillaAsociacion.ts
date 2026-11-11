import { useEffect, useState } from 'react';
import { asociacionesService, type AlumnoVentanilla } from '@/services/asociacionesService';
import type { Asociacion } from '@/types/asociaciones';

export function useVentanillaAsociacion(open: boolean, ciNit: string) {
  const [asociacionDetectada, setAsociacionDetectada] = useState<Asociacion | null>(null);
  const [asociacionCargando, setAsociacionCargando] = useState(false);
  const [alumnos, setAlumnos] = useState<AlumnoVentanilla[]>([]);
  const [cargandoAlumnos, setCargandoAlumnos] = useState(false);

  // P2b: al tipear el CI, preguntar si es asociación vigente (debounce 400ms).
  useEffect(() => {
    if (!open) {
      setAsociacionDetectada(null);
      return;
    }

    const ci = ciNit.trim();

    if (ci.length < 5) {
      setAsociacionDetectada(null);
      return;
    }

    let activo = true;
    setAsociacionCargando(true);

    const t = setTimeout(() => {
      asociacionesService
        .porResponsable(ci)
        .then(({ asociacion }) => {
          if (activo) setAsociacionDetectada(asociacion);
        })
        .catch(() => {
          if (activo) setAsociacionDetectada(null);
        })
        .finally(() => {
          if (activo) setAsociacionCargando(false);
        });
    }, 400);

    return () => {
      activo = false;
      clearTimeout(t);
    };
  }, [ciNit, open]);

  // [Fase 3] al detectar asociación VIGENTE, cargar su lista de alumnos.
  useEffect(() => {
    if (!open || !asociacionDetectada?.vigente || !asociacionDetectada.id) {
      setAlumnos([]);
      return;
    }

    let activo = true;
    setCargandoAlumnos(true);

    asociacionesService
      .listarAlumnos(asociacionDetectada.id)
      .then((lista) => {
        if (activo) setAlumnos(lista);
      })
      .catch(() => {
        if (activo) setAlumnos([]);
      })
      .finally(() => {
        if (activo) setCargandoAlumnos(false);
      });

    return () => {
      activo = false;
    };
  }, [open, asociacionDetectada]);

  const reset = () => {
    setAsociacionDetectada(null);
    setAsociacionCargando(false);
    setAlumnos([]);
    setCargandoAlumnos(false);
  };

  return {
    asociacionDetectada,
    asociacionCargando,
    alumnos,
    cargandoAlumnos,
    reset,
  };
}
