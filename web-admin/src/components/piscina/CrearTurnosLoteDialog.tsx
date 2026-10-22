import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Loader2, Copy, CalendarRange } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { piscinaService } from '@/services/piscinaService';
import type { PiscinaTurno } from '@/types/piscina';

interface Props {
  abierto: boolean;
  onCerrar: () => void;
  campoId: string;
  /** recibe el rango aplicado para que la lista filtre hacia allá. */
  onCreado: (rango: { desde: string; hasta: string }) => void;
}

const iso = (d: Date) => d.toISOString().substring(0, 10);
const addDays = (base: string, n: number) => {
  const d = new Date(base + 'T00:00:00');
  d.setDate(d.getDate() + n);
  return iso(d);
};
const hoy = () => iso(new Date());
const hhmm = (h: string) => (h || '').substring(0, 5);

const perfilLabel: Record<string, string> = {
  mayor_12: 'Mayores de 12',
  menor_12: 'Menores de 12',
  institucion: 'Institución',
};

export default function CrearTurnosLoteDialog({ abierto, onCerrar, campoId, onCreado }: Props) {
  const [origen, setOrigen] = useState(hoy());
  const [desde, setDesde] = useState(addDays(hoy(), 1));
  const [hasta, setHasta] = useState(addDays(hoy(), 30));
  const [turnosOrigen, setTurnosOrigen] = useState<PiscinaTurno[]>([]);
  const [cargandoPreview, setCargandoPreview] = useState(false);
  const [enviando, setEnviando] = useState(false);

  // clamp hasta a hoy+60 (ventana reservable del service de disponibilidad).
  useEffect(() => {
    const max = addDays(hoy(), 60);
    if (hasta > max) setHasta(max);
    if (desde > hasta) setDesde(hasta);
  }, [hasta, desde]);

  useEffect(() => {
    if (!abierto || !campoId) return;
    let activo = true;
    setCargandoPreview(true);
    piscinaService
      .obtenerTurnos(campoId, { fecha_desde: origen, fecha_hasta: origen })
      .then((t) => { if (activo) setTurnosOrigen(t); })
      .catch(() => { if (activo) setTurnosOrigen([]); })
      .finally(() => { if (activo) setCargandoPreview(false); });
    return () => { activo = false; };
  }, [abierto, campoId, origen]);

  const rangoValido = !!desde && !!hasta && desde <= hasta;
  const puedeEnviar = rangoValido && turnosOrigen.length > 0 && !cargandoPreview && !enviando;

  const enviar = async () => {
    if (!puedeEnviar) return;
    setEnviando(true);
    try {
      const res = await piscinaService.crearTurnosLote({
        campo_id: campoId,
        fecha_origen: origen,
        desde,
        hasta,
      });
      const omit = res.omitidas?.length ?? 0;
      toast.success(
        `Lote listo: ${res.creados} turnos creados` +
          (omit ? `, ${omit} fechas omitidas por solape.` : '.'),
      );
      onCreado({ desde, hasta });
      onCerrar();
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'No se pudo generar el lote.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Dialog open={abierto} onOpenChange={(v) => (v ? null : onCerrar())}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Copy className="size-5 text-teal-600" />
            Generar turnos en lote
          </DialogTitle>
          <DialogDescription>
            Clona los turnos activos de un día de referencia a un rango de fechas.
            Es idempotente: volver a correrlo no duplica ni pisa reservas ya tomadas.
            Las fechas con un turno activo que se solape se omiten (alineado con cancha).
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-1">
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="space-y-1.5">
              <Label>Día de referencia</Label>
              <Input type="date" value={origen} onChange={(e) => setOrigen(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Desde</Label>
              <Input type="date" value={desde} min={hoy()} onChange={(e) => setDesde(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Hasta (máx +60 días)</Label>
              <Input type="date" value={hasta} min={desde} onChange={(e) => setHasta(e.target.value)} />
            </div>
          </div>

          <div className="rounded-lg border bg-muted/40 p-3">
            <p className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              <CalendarRange className="size-3.5" />
              Plantilla a clonar ({turnosOrigen.length} turnos el {origen})
            </p>
            {cargandoPreview ? (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" /> Leyendo día de referencia…
              </div>
            ) : turnosOrigen.length === 0 ? (
              <p className="text-sm text-amber-700 dark:text-amber-400">
                El día de referencia no tiene turnos activos. Crealos primero con
                «Crear Turno» (o elegí otra fecha que sí tenga) y volvé.
              </p>
            ) : (
              <div className="space-y-2">
                {turnosOrigen.map((t) => (
                  <div key={t.id} className="flex items-center justify-between gap-2 text-sm">
                    <span className="font-mono">{hhmm(t.hora_inicio)}–{hhmm(t.hora_fin)}</span>
                    <div className="flex flex-wrap gap-1">
                      {t.perfiles.map((p) => (
                        <Badge key={p.id} variant="secondary" className="text-[10px]">
                          {perfilLabel[p.perfil] ?? p.perfil} · {p.cupo} · Bs {Number(p.precio_unitario).toFixed(2)}
                        </Badge>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onCerrar} disabled={enviando}>Cancelar</Button>
          <Button onClick={enviar} disabled={!puedeEnviar}>
            {enviando && <Loader2 className="mr-2 size-4 animate-spin" />}
            Generar lote
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
