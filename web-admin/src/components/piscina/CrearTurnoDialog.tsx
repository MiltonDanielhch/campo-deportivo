import { useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, Clock, Users, ShieldCheck } from 'lucide-react';
import { piscinaService } from '@/services/piscinaService';
import type { PiscinaTurno, PiscinaTurnoRequest, PiscinaTarifaPerfil } from '@/types/piscina';

interface CrearTurnoPiscinaDialogProps {
  abierto: boolean;
  onCerrar: () => void;
  campoId: string;
  /** [editar] si viene un turno, el dialog pasa a modo edición. */
  turno?: PiscinaTurno | null;
  onCreado: () => void;
}

interface PerfilTurno {
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  cupo: number;
  // [B99] en edición trae el espejado de SIREB (read-only); en crear es null y el
  // precio se muestra desde el map vivo (preciosSireb), no desde acá.
  precio_unitario: number | null;
  // [editar] cupo ya tomado; pa' el piso del input y pa' no quitar el perfil.
  reservado: number;
}

const PERFILES = ['mayor_12', 'menor_12', 'institucion'] as const;

export default function CrearTurnoPiscinaDialog({
  abierto,
  onCerrar,
  campoId,
  turno,
  onCreado,
}: CrearTurnoPiscinaDialogProps) {
  const esEdicion = turno != null;

  const [guardando, setGuardando] = useState(false);
  const [fecha, setFecha] = useState('');
  const [horaInicio, setHoraInicio] = useState('');
  const [horaFin, setHoraFin] = useState('');
  const [cupoTotal, setCupoTotal] = useState<number | null>(null);
  const [estado, setEstado] = useState<'activo' | 'inactivo'>('activo');
  const [observacion, setObservacion] = useState('');
  const [perfiles, setPerfiles] = useState<PerfilTurno[]>([]);

  // [B99-precio] map perfil -> tarifa SIREB resuelta para la hora, + estado del fetch
  // pa' no mostrar "sin tarifa" mientras carga ni mentir si el fetch falló.
  const [preciosSireb, setPreciosSireb] = useState<Record<string, PiscinaTarifaPerfil>>({});
  const [preciosEstado, setPreciosEstado] = useState<'idle' | 'cargando' | 'listo' | 'error'>('idle');

  // [editar] precarga al abrir (y reset pa' no heredar estado de una apertura
  // anterior, bug latente que el dialog de crear también tenía).
  useEffect(() => {
    if (!abierto) return;
    if (turno) {
      setFecha(turno.fecha);
      setHoraInicio(String(turno.hora_inicio).slice(0, 5));
      setHoraFin(String(turno.hora_fin).slice(0, 5));
      setCupoTotal(turno.cupo_total);
      setEstado(turno.estado);
      setObservacion(turno.observacion ?? '');
      setPerfiles(
        turno.perfiles.map((p) => ({
          perfil: p.perfil,
          cupo: p.cupo,
          // [B99] traigo el precio espejado pa' mostrarlo read-only en edición (el
          // backend lo re-resuelve al guardar, así que el dato final es SIREB).
          precio_unitario: Number(p.precio_unitario),
          reservado: p.reservado,
        })),
      );
    } else {
      setFecha('');
      setHoraInicio('');
      setHoraFin('');
      setCupoTotal(null);
      setEstado('activo');
      setObservacion('');
      setPerfiles([]);
    }
  }, [abierto, turno]);

  // [B99-precio] en modo crear, al tener hora válida, pido el precio SIREB por perfil
  // vía el endpoint que reusa el resolver (fuente única; el front no duplica el corte
  // diurno/nocturno). En edición NO fetcheo: la hora es inmutable y el precio espejado
  // ya viene con el turno (la deriva de 1 sync sigue anotada, no regresa).
  useEffect(() => {
    if (!abierto || esEdicion || !campoId || !/^\d{2}:\d{2}$/.test(horaInicio)) {
      setPreciosEstado('idle');
      return;
    }
    let cancelado = false;
    setPreciosEstado('cargando');
    piscinaService
      .tarifasPerfil(campoId, `${horaInicio}:00`)
      .then((lista) => {
        if (cancelado) return;
        const map: Record<string, PiscinaTarifaPerfil> = {};
        for (const t of lista) map[t.perfil] = t;
        setPreciosSireb(map);
        setPreciosEstado('listo');
      })
      .catch(() => {
        if (cancelado) return;
        // fetch falló -> degradamos a "Se espeja al guardar" (sigue siendo verdad:
        // el backend resuelve al guardar). No afirmamos "sin tarifa" por un error de red.
        setPreciosEstado('error');
      });
    return () => {
      cancelado = true;
    };
  }, [abierto, esEdicion, campoId, horaInicio]);

  const agregarPerfil = () => {
    // [dedupe] elige el primer perfil aún no usado; si están los 3, no agrega
    // (el botón igual está disabled en perfiles.length >= 3).
    const usados = new Set(perfiles.map((p) => p.perfil));
    const siguiente = PERFILES.find((x) => !usados.has(x));
    if (!siguiente) return;
    setPerfiles([...perfiles, { perfil: siguiente, cupo: 10, precio_unitario: null, reservado: 0 }]);
  };

  const actualizarPerfil = (index: number, campo: 'perfil' | 'cupo', valor: any) => {
    // [dedupe] no dejar cambiar un perfil a uno que ya ocupa otra fila del turno.
    if (campo === 'perfil' && perfiles.some((p, i) => i !== index && p.perfil === valor)) {
      toast.warning('Ese perfil ya está agregado en este turno.');
      return;
    }
    const nuevos = [...perfiles];
    nuevos[index] = { ...nuevos[index], [campo]: valor };
    setPerfiles(nuevos);
  };

  const eliminarPerfil = (index: number) => {
    setPerfiles(perfiles.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setGuardando(true);

    try {
      // [B99] el payload manda SOLO perfil + cupo; precio_unitario y tarifa_sireb_id
      // los resuelve el backend vía PiscinaTarifaResolver (SIREB es la fuente).
      const perfilesPayload = perfiles.map((p) => ({
        perfil: p.perfil,
        cupo: p.cupo,
      }));

      if (esEdicion && turno) {
        const payload = {
          estado,
          observacion: observacion || null,
          perfiles: perfilesPayload,
        };
        await piscinaService.actualizarTurno(turno.id, payload);
        toast.success('Turno actualizado');
      } else {
        const datos: PiscinaTurnoRequest = {
          campo_id: campoId,
          fecha,
          hora_inicio: horaInicio + ':00',
          hora_fin: horaFin + ':00',
          cupo_total: cupoTotal,
          estado,
          observacion: observacion || null,
          perfiles: perfilesPayload,
        };
        await piscinaService.crearTurno(datos);
        toast.success('Turno creado exitosamente');
      }
      onCreado();
      onCerrar();
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'No se pudo guardar el turno');
    } finally {
      setGuardando(false);
    }
  };

  const cupoTotalCalculado = perfiles.reduce((sum, p) => sum + p.cupo, 0);

  return (
    <Dialog open={abierto} onOpenChange={onCerrar}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{esEdicion ? 'Editar Turno de Piscina' : 'Crear Turno de Piscina'}</DialogTitle>
          <DialogDescription>
            {esEdicion
              ? 'Modificá cupos, estado u observación. El precio lo define SIREB y el intervalo (fecha y hora) no se cambia acá.'
              : 'Configurá los perfiles y cupos. El precio de cada perfil lo define SIREB y se muestra acá según la hora.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="fecha">Fecha</Label>
              <Input
                id="fecha"
                type="date"
                value={fecha}
                onChange={(e) => setFecha(e.target.value)}
                disabled={esEdicion}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="cupo_total">Cupo Total (opcional)</Label>
              <Input
                id="cupo_total"
                type="number"
                value={cupoTotal ?? ''}
                onChange={(e) => setCupoTotal(e.target.value ? Number(e.target.value) : null)}
                placeholder="Se calcula de perfiles"
                disabled={esEdicion}
              />
              {esEdicion && (
                <p className="text-xs text-muted-foreground">No editable acá; se define al crear.</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="hora_inicio">Hora Inicio</Label>
              <Input
                id="hora_inicio"
                type="time"
                value={horaInicio}
                onChange={(e) => setHoraInicio(e.target.value)}
                disabled={esEdicion}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="hora_fin">Hora Fin</Label>
              <Input
                id="hora_fin"
                type="time"
                value={horaFin}
                onChange={(e) => setHoraFin(e.target.value)}
                disabled={esEdicion}
                required
              />
            </div>
          </div>

          {esEdicion && (
            <p className="text-xs text-muted-foreground">
              El intervalo no se modifica. Si necesitás otro horario, eliminá el turno y creá uno nuevo.
            </p>
          )}

          <div className="space-y-2">
            <Label htmlFor="estado">Estado</Label>
            <Select value={estado} onValueChange={(v: any) => setEstado(v)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="activo">Activo</SelectItem>
                <SelectItem value="inactivo">Inactivo</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="observacion">Observación (opcional)</Label>
            <Input
              id="observacion"
              value={observacion}
              onChange={(e) => setObservacion(e.target.value)}
              placeholder="Notas adicionales..."
            />
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label>Perfiles del Turno</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={agregarPerfil}
                // [dedupe] los 3 perfiles ya están agregados.
                disabled={perfiles.length >= PERFILES.length}
              >
                <Plus className="w-4 h-4 mr-2" />
                Agregar Perfil
              </Button>
            </div>

            {perfiles.length === 0 ? (
              <p className="text-sm text-slate-500 text-center py-4">
                No hay perfiles configurados. Agrega al menos uno.
              </p>
            ) : (
              <div className="space-y-3">
                {perfiles.map((perfil, index) => {
                  // [B99-precio] texto/clase de la caja según modo y estado del fetch.
                  let precioTexto: string;
                  let precioCls = 'text-sm font-medium text-slate-700';
                  if (esEdicion) {
                    precioTexto =
                      perfil.precio_unitario != null ? `Bs ${Number(perfil.precio_unitario).toFixed(2)}` : '—';
                  } else if (preciosEstado === 'cargando') {
                    precioTexto = '…';
                    precioCls = 'text-sm text-slate-400';
                  } else if (preciosEstado === 'listo') {
                    const p = preciosSireb[perfil.perfil]?.precio_unitario;
                    if (p != null) {
                      precioTexto = `Bs ${p.toFixed(2)}`;
                    } else {
                      precioTexto = 'Sin tarifa SIREB';
                      precioCls = 'text-sm italic text-rose-500';
                    }
                  } else {
                    // idle (sin hora aún) o error de fetch -> verdad de fallback.
                    precioTexto = 'Se espeja al guardar';
                    precioCls = 'text-sm italic text-slate-400';
                  }

                  return (
                    <div key={index} className="border rounded-lg p-4 space-y-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Users className="w-4 h-4 text-slate-400" />
                          <Select
                            value={perfil.perfil}
                            onValueChange={(v: any) => actualizarPerfil(index, 'perfil', v)}
                          >
                            <SelectTrigger className="w-48">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="mayor_12">Mayores de 12</SelectItem>
                              <SelectItem value="menor_12">Menores de 12</SelectItem>
                              <SelectItem value="institucion">Institución</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => eliminarPerfil(index)}
                          disabled={esEdicion && perfil.reservado > 0}
                          title={esEdicion && perfil.reservado > 0 ? `Tiene ${perfil.reservado} reservado(s)` : undefined}
                        >
                          <Trash2 className="w-4 h-4 text-red-500" />
                        </Button>
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                          <Label className="text-xs">Cupo</Label>
                          <Input
                            type="number"
                            value={perfil.cupo}
                            onChange={(e) => {
                              const v = Number(e.target.value);
                              actualizarPerfil(index, 'cupo', esEdicion ? Math.max(perfil.reservado, v) : v);
                            }}
                            min={1}
                            required
                          />
                          {esEdicion && perfil.reservado > 0 && (
                            <p className="text-[10px] text-amber-600">{perfil.reservado} ya reservado</p>
                          )}
                        </div>

                        {/* [B99] precio read-only: lo define SIREB (en crear, en vivo por hora). */}
                        <div className="space-y-1">
                          <Label className="text-xs">Precio (SIREB)</Label>
                          <div className={`flex h-11 items-center rounded-md border border-slate-200 bg-slate-50 px-3 ${precioCls}`}>
                            {precioTexto}
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}

            {/* [B99] hint lock, mismo texto que la pantalla de Tarifas (coherencia). */}
            {perfiles.length > 0 && (
              <div className="flex items-start gap-2 rounded-lg border border-teal-200 bg-teal-50 p-3 text-xs text-teal-900">
                <ShieldCheck className="w-4 h-4 flex-shrink-0 mt-0.5 text-teal-600" />
                <p>
                  El precio de cada perfil lo define <strong>SIREB</strong> y este sistema solo lo
                  refleja. Para cambiarlo hay que hacerlo en el panel de recaudaciones; acá podés
                  volver a sincronizar cuando lo actualicen.
                </p>
              </div>
            )}

            {cupoTotalCalculado > 0 && (
              <div className="flex items-center gap-2 text-sm text-slate-600">
                <Clock className="w-4 h-4" />
                <span>Cupo total calculado: {cupoTotalCalculado}</span>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onCerrar}>
              Cancelar
            </Button>
            <Button type="submit" disabled={guardando || perfiles.length === 0}>
              {guardando ? 'Guardando...' : esEdicion ? 'Guardar cambios' : 'Crear Turno'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
