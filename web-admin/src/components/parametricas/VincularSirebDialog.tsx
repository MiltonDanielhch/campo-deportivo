import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  BadgeCheck,
  Database,
  Link2,
  Loader2,
  RefreshCw,
  Search,
  TriangleAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { catalogoSirebService } from '@/services/catalogoSirebService';
import type { CatalogoSirebItem } from '@/services/catalogoSirebService';
import type { CampoDeportivo } from '@/types/parametricas';

interface VincularSirebDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  campo: CampoDeportivo | null;
  onVinculado: () => void;
}

function formatoPrecio(item: CatalogoSirebItem): string {
  const { precio_min: min, precio_max: max } = item.sireb;

  if (min == null && max == null) return '—';
  if (min != null && max != null) {
    return min === max
      ? `Bs. ${min.toFixed(2)}`
      : `Bs. ${min.toFixed(2)} – Bs. ${max.toFixed(2)}`;
  }

  const valor = (min ?? max) as number;
  return `Bs. ${valor.toFixed(2)}`;
}

/**
 * Vincula un campo local con un servicio del catálogo oficial de SIREB
 * (el mismo listado que se administra en /panel/servicios).
 */
export default function VincularSirebDialog({
  open,
  onOpenChange,
  campo,
  onVinculado,
}: VincularSirebDialogProps) {
  const [items, setItems] = useState<CatalogoSirebItem[]>([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busqueda, setBusqueda] = useState('');
  const [seleccionId, setSeleccionId] = useState<string | null>(null);
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    if (!open) return;

    setBusqueda('');
    setSeleccionId(null);
    setError(null);
    setCargando(true);

    catalogoSirebService
      .listarCatalogo()
      .then((res) => setItems(res.data))
      .catch(() => setError('No se pudo obtener el catálogo de SIREB.'))
      .finally(() => setCargando(false));
  }, [open]);

  const filtrados = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    if (!q) return items;

    return items.filter(
      (i) =>
        i.sireb.codigo.toLowerCase().includes(q) ||
        i.sireb.nombre.toLowerCase().includes(q),
    );
  }, [items, busqueda]);

  const confirmar = async () => {
    if (!campo || !seleccionId) return;

    setGuardando(true);
    try {
      const res = await catalogoSirebService.vincularCampo(campo.id, seleccionId);

      if (res.data?.warning) {
        toast.warning(res.data.warning);
      } else {
        toast.success(res.message ?? 'Campo vinculado a SIREB');
      }

      onOpenChange(false);
      onVinculado();
    } catch {
      toast.error('No se pudo vincular el campo al servicio de SIREB');
    } finally {
      setGuardando(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[88vh] flex-col gap-0 overflow-hidden p-0! sm:max-w-2xl">
        <DialogHeader className="border-b px-6 py-4 text-left">
          <DialogTitle className="text-lg">Vincular a SIREB</DialogTitle>
          <DialogDescription className="text-xs">
            {campo
              ? `Elegí el servicio oficial de SIREB que corresponde a "${campo.nombre}" (${campo.codigo}).`
              : 'Elegí el servicio oficial de SIREB.'}
          </DialogDescription>
        </DialogHeader>

        <div className="border-b bg-muted/30 px-6 py-3">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por código o nombre del servicio…"
              value={busqueda}
              onChange={(e) => setBusqueda(e.target.value)}
              className="pl-9 bg-background"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-4">
          {cargando ? (
            <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
              <Loader2 className="size-4 animate-spin" />
              Consultando el catálogo de SIREB…
            </div>
          ) : error ? (
            <div className="flex h-40 flex-col items-center justify-center gap-2 text-center">
              <TriangleAlert className="size-6 text-amber-500" />
              <p className="text-sm text-muted-foreground">{error}</p>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setError(null);
                  setCargando(true);
                  catalogoSirebService
                    .listarCatalogo()
                    .then((res) => setItems(res.data))
                    .catch(() => setError('No se pudo obtener el catálogo de SIREB.'))
                    .finally(() => setCargando(false));
                }}
              >
                <RefreshCw className="mr-2 size-4" />
                Reintentar
              </Button>
            </div>
          ) : filtrados.length === 0 ? (
            <p className="py-12 text-center text-sm text-muted-foreground">
              Ningún servicio de SIREB coincide con la búsqueda.
            </p>
          ) : (
            <ul className="space-y-2">
              {filtrados.map((item) => {
                const ocupado =
                  item.campo_local !== null && item.campo_local.id !== campo?.id;
                const seleccionado = seleccionId === item.sireb.id;

                return (
                  <li key={item.sireb.id}>
                    <button
                      type="button"
                      disabled={ocupado}
                      onClick={() => setSeleccionId(item.sireb.id)}
                      className={`w-full rounded-xl border p-3 text-left transition-colors ${
                        ocupado
                          ? 'cursor-not-allowed opacity-60'
                          : seleccionado
                            ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                            : 'hover:border-primary/40 hover:bg-muted/50'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="font-mono text-xs text-muted-foreground">
                            {item.sireb.codigo}
                          </p>
                          <p className="truncate font-semibold">
                            {item.sireb.nombre}
                          </p>
                        </div>
                        <div className="shrink-0 text-right">
                          <p className="font-semibold">{formatoPrecio(item)}</p>
                          <p className="text-xs text-muted-foreground">
                            {item.sireb.tarifario ?? 'liquidable'}
                          </p>
                        </div>
                      </div>

                      <div className="mt-2 flex flex-wrap items-center gap-2">
                        {ocupado ? (
                          <Badge
                            variant="secondary"
                            className="gap-1.5 bg-amber-500/10 text-amber-700 dark:text-amber-400"
                          >
                            <Database className="size-3" />
                            Ya vinculado a {item.campo_local?.nombre}
                          </Badge>
                        ) : seleccionado ? (
                          <Badge variant="default" className="gap-1.5">
                            <BadgeCheck className="size-3" />
                            Seleccionado
                          </Badge>
                        ) : (
                          <Badge
                            variant="secondary"
                            className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                          >
                            Disponible
                          </Badge>
                        )}
                        {!item.reservable_online && item.mensaje_no_reservable && (
                          <span className="text-xs text-muted-foreground">
                            {item.mensaje_no_reservable}
                          </span>
                        )}
                      </div>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>

        <DialogFooter className="border-t bg-muted/40 px-6 py-4">
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={guardando}
          >
            Cancelar
          </Button>
          <Button onClick={confirmar} disabled={!seleccionId || guardando}>
            {guardando ? (
              <Loader2 className="mr-2 size-4 animate-spin" />
            ) : (
              <Link2 className="mr-2 size-4" />
            )}
            {guardando ? 'Vinculando…' : 'Vincular servicio'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
