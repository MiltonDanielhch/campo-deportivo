import { Loader2 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Asociacion } from '@/types/asociaciones';

interface Props {
  nombre: string;
  onNombreChange: (v: string) => void;
  ciNit: string;
  onCiNitChange: (v: string) => void;
  telefono: string;
  onTelefonoChange: (v: string) => void;
  asociacionCargando: boolean;
  asociacionDetectada: Asociacion | null;
}

export function VentanillaPagadorForm({
  nombre,
  onNombreChange,
  ciNit,
  onCiNitChange,
  telefono,
  onTelefonoChange,
  asociacionCargando,
  asociacionDetectada,
}: Props) {
  return (
    <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
      <p className="text-sm font-semibold">Datos del pagador</p>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label>Nombre completo *</Label>
          <Input value={nombre} onChange={(e) => onNombreChange(e.target.value)} />
        </div>

        <div className="space-y-1.5">
          <Label>CI / NIT *</Label>
          <div className="relative">
            <Input value={ciNit} onChange={(e) => onCiNitChange(e.target.value)} />
            {asociacionCargando && (
              <Loader2 className="absolute right-2 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
            )}
          </div>

          {asociacionDetectada?.vigente && (
            <p className="text-xs text-emerald-700 dark:text-emerald-400">
              Asociación vigente: {asociacionDetectada.nombre}
            </p>
          )}

          {!asociacionDetectada && !asociacionCargando && (
            <p className="text-xs text-muted-foreground">
              Tipeá el CI de un responsable con convenio: la variante se elige sola.
            </p>
          )}
        </div>

        <div className="space-y-1.5 sm:col-span-2">
          <Label>Teléfono (opcional)</Label>
          <Input value={telefono} onChange={(e) => onTelefonoChange(e.target.value)} />
        </div>
      </div>
    </div>
  );
}
