import { useEffect, useMemo, useRef, useState } from 'react';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { CampoOption } from '@/types/reservas';

type CampoConModalidad = CampoOption & { modalidad_reserva?: string };

export function useVentanillaCampos(open: boolean) {
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [campoId, setCampoId] = useState('');
  const [campoNombre, setCampoNombre] = useState('');
  const [busquedaCampo, setBusquedaCampo] = useState('');
  const [campoAbierto, setCampoAbierto] = useState(false);

  const campoWrapRef = useRef<HTMLDivElement>(null);
  const campoInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open) return;
    solicitudesReservaService
      .listarCampos({ estado: 'activo' })
      .then((lista) => {
        setCampos(lista);
        const sinNombre = lista.filter((c) => !c.nombre || c.nombre === c.id);
        if (sinNombre.length > 0) {
          console.warn('[ventanilla] campos sin nombre legible:', sinNombre);
        }
      })
      .catch(() => setCampos([]));
  }, [open]);

  const camposCancha = useMemo(
    () =>
      (campos as CampoConModalidad[]).filter(
        (c) => c.modalidad_reserva !== 'turno_por_perfil',
      ),
    [campos],
  );

  const camposFiltrados = useMemo(
    () =>
      camposCancha.filter((c) =>
        (c.nombre || c.id).toLowerCase().includes(busquedaCampo.toLowerCase().trim()),
      ),
    [camposCancha, busquedaCampo],
  );

  const handleCampoChange = (nuevoId: string) => {
    setCampoId(nuevoId);
    const campo = campos.find((c) => c.id === nuevoId);
    setCampoNombre(campo?.nombre && campo.nombre !== campo.id ? campo.nombre : '');
    setBusquedaCampo('');
    setCampoAbierto(false);
  };

  const reset = () => {
    setCampoId('');
    setCampoNombre('');
    setBusquedaCampo('');
    setCampoAbierto(false);
  };

  return {
    campos,
    campoId,
    setCampoId,
    campoNombre,
    busquedaCampo,
    setBusquedaCampo,
    campoAbierto,
    setCampoAbierto,
    campoWrapRef,
    campoInputRef,
    camposFiltrados,
    handleCampoChange,
    reset,
  };
}
