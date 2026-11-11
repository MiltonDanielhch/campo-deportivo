import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  catalogoSirebService,
  type VarianteArancelaria,
} from '@/services/catalogoSirebService';

export function useVentanillaVariantes(campoId: string, open: boolean) {
  const [variantes, setVariantes] = useState<VarianteArancelaria[]>([]);
  const [cargandoVariantes, setCargandoVariantes] = useState(false);
  const [varianteId, setVarianteId] = useState('');
  const [varianteNombre, setVarianteNombre] = useState('');

  useEffect(() => {
    if (!open || !campoId) {
      setVariantes([]);
      setVarianteId('');
      setVarianteNombre('');
      return;
    }
    let activo = true;
    setCargandoVariantes(true);
    setVarianteId('');
    setVarianteNombre('');
    catalogoSirebService
      .listarVariantesPorCampo(campoId)
      .then((lista) => {
        if (!activo) return;
        setVariantes(lista);
        const horarias = lista.filter((v) => v.unidad_medida === 'hora');
        if (horarias.length === 1) {
          setVarianteId(horarias[0].id);
          setVarianteNombre(horarias[0].nombre);
        }
      })
      .catch(() => {
        if (!activo) return;
        setVariantes([]);
        toast.error('No se pudieron cargar las variantes arancelarias.');
      })
      .finally(() => {
        if (activo) setCargandoVariantes(false);
      });
    return () => {
      activo = false;
    };
  }, [open, campoId]);

  const variantesHorarias = useMemo(
    () => variantes.filter((v) => v.unidad_medida === 'hora'),
    [variantes],
  );

  const tieneVariantes = variantes.length > 0;
  const requiereVariante = tieneVariantes;
  const bloqueadoPorVariantesNoHorarias =
    tieneVariantes && variantesHorarias.length === 0;

  const varianteSeleccionada = useMemo(
    () => variantesHorarias.find((v) => v.id === varianteId) ?? null,
    [variantesHorarias, varianteId],
  );

  const handleVarianteChange = useCallback((nuevoId: string) => {
    setVarianteId(nuevoId);
    const variante = variantesHorarias.find((v) => v.id === nuevoId);
    setVarianteNombre(variante?.nombre ?? '');
  }, [variantesHorarias]);

  // Setter estable para la preselección de convenio (efecto en el componente).
  const setVarianteConvenio = useCallback((id: string, nombre: string) => {
    setVarianteId(id);
    setVarianteNombre(nombre);
  }, []);

  const reset = () => {
    setVariantes([]);
    setVarianteId('');
    setVarianteNombre('');
  };

  return {
    variantes,
    cargandoVariantes,
    varianteId,
    varianteNombre,
    variantesHorarias,
    tieneVariantes,
    requiereVariante,
    bloqueadoPorVariantesNoHorarias,
    varianteSeleccionada,
    handleVarianteChange,
    setVarianteConvenio,
    reset,
  };
}
