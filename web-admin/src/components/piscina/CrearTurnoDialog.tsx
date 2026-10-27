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
import { Plus, Trash2, Clock, Users } from 'lucide-react';
import { piscinaService } from '@/services/piscinaService';
import type { PiscinaTurno, PiscinaTurnoRequest } from '@/types/piscina';

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
  precio_unitario: number;
  tarifa_sireb_id: string | null;
  // [editar] cupo ya tomado; pa' el piso del input y pa' no quitar el perfil.
  reservado: number;
}

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
          precio_unitario: Number(p.precio_unitario),
          tarifa_sireb_id: p.tarifa_sireb_id,
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

  const agregarPerfil = () => {
    setPerfiles([...perfiles, { perfil: 'mayor_12', cupo: 10, precio_unitario: 15, tarifa_sireb_id: null, reservado: 0 }]);
  };

  const actualizarPerfil = (index: number, campo: keyof PerfilTurno, valor: any) => {
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
      if (esEdicion && turno) {
        // [editar] el intervalo (fecha/hora) es inmutable en el backend; el payload
        // de update solo lleva estado/observacion/perfiles (campo_id viene por route).
        const payload = {
          estado,
          observacion: observacion || null,
          perfiles: perfiles.map((p) => ({
            perfil: p.perfil,
            cupo: p.cupo,
            precio_unitario: p.precio_unitario,
            tarifa_sireb_id: p.tarifa_sireb_id,
          })),
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
          perfiles,
        };
        await piscinaService.crearTurno(datos);
        toast.success('Turno creado exitosamente');
      }
      onCreado();
      onCerrar();
    } catch (error: any) {
      // [editar] muestro el message del backend (422 de B70-store / Fix3-real),
      // no un "no se pudo" genérico que deja al funcionario sin saber por qué.
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
              ? 'Modificá cupos, precios, estado u observación. El intervalo (fecha y hora) no se cambia acá.'
              : 'Configura un nuevo turno con sus perfiles y cupos.'}
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
              <Button type="button" variant="outline" size="sm" onClick={agregarPerfil}>
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
                {perfiles.map((perfil, index) => (
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
                        // [editar] no se puede quitar un perfil con gente reservada
                        // (el backend Fix3-real lo bloquea; el front no ofrece lo que falla).
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
                          // [editar] piso en lo ya reservado: el cupo no puede bajar de ahí.
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

                      <div className="space-y-1">
                        <Label className="text-xs">Precio Unitario</Label>
                        <Input
                          type="number"
                          step="0.01"
                          value={perfil.precio_unitario}
                          onChange={(e) => actualizarPerfil(index, 'precio_unitario', Number(e.target.value))}
                          min={0}
                          required
                        />
                      </div>
                    </div>
                  </div>
                ))}
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
