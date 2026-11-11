import {
  useEffect,
  type Dispatch,
  type RefObject,
  type SetStateAction,
} from 'react';
import { Check, ChevronDown, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CampoFiltrado {
  id: string;
  nombre?: string;
}

interface Props {
  campoId: string;
  campoNombre: string;
  busquedaCampo: string;
  setBusquedaCampo: (v: string) => void;
  campoAbierto: boolean;
  setCampoAbierto: Dispatch<SetStateAction<boolean>>;
  campoWrapRef: RefObject<HTMLDivElement | null>;
  campoInputRef: RefObject<HTMLInputElement | null>;
  camposFiltrados: CampoFiltrado[];
  onSelect: (id: string) => void;
}

export function VentanillaCampoCombobox({
  campoId,
  campoNombre,
  busquedaCampo,
  setBusquedaCampo,
  campoAbierto,
  setCampoAbierto,
  campoWrapRef,
  campoInputRef,
  camposFiltrados,
  onSelect,
}: Props) {
  useEffect(() => {
    if (!campoAbierto) return;

    campoInputRef.current?.focus();

    const onPointerDown = (e: MouseEvent) => {
      if (campoWrapRef.current && !campoWrapRef.current.contains(e.target as Node)) {
        setCampoAbierto(false);
      }
    };

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setCampoAbierto(false);
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [campoAbierto, campoInputRef, campoWrapRef, setCampoAbierto]);

  return (
    <div className="relative min-w-0 space-y-2" ref={campoWrapRef}>
      <Label>Campo *</Label>
      <Button
        type="button"
        variant="outline"
        role="combobox"
        aria-expanded={campoAbierto}
        aria-label="Campo deportivo"
        className="w-full justify-between font-normal"
        onClick={() => setCampoAbierto((v) => !v)}
      >
        <span className="flex min-w-0 items-center gap-2 text-left">
          <MapPin className="size-4 shrink-0 text-muted-foreground" />
          <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
            {campoNombre || 'Elegí el campo'}
          </span>
        </span>
        <ChevronDown className="size-4 shrink-0 opacity-50" />
      </Button>

      {campoAbierto && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 rounded-md border bg-background p-1 shadow-md">
          <Input
            ref={campoInputRef}
            value={busquedaCampo}
            onChange={(e) => setBusquedaCampo(e.target.value)}
            placeholder="Buscar campo…"
            className="mb-1 h-8 text-sm"
            aria-label="Buscar campo"
          />
          <div className="max-h-56 overflow-y-auto">
            {camposFiltrados.length === 0 ? (
              <p className="px-2 py-1.5 text-sm text-muted-foreground">
                Sin campos que coincidan.
              </p>
            ) : (
              camposFiltrados.map((c) => {
                const activo = c.id === campoId;
                return (
                  <button
                    key={c.id}
                    type="button"
                    onClick={() => onSelect(c.id)}
                    className={`flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted ${
                      activo ? 'bg-muted font-medium' : ''
                    }`}
                  >
                    <span className="block truncate" title={c.nombre || c.id}>
                      {c.nombre || c.id}
                    </span>
                    {activo && <Check className="size-4 shrink-0" />}
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}
