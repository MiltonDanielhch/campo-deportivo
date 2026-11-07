import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Loader2, Pencil, Plus, Undo2, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

import { asociacionesService } from '@/services/asociacionesService';
import type { Alumno, AlumnoPayload, Asociacion } from '@/types/asociaciones';

interface Props {
  asociacion: Asociacion | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const vacio = (): AlumnoPayload => ({
  ci: '',
  nombre_completo: '',
  fecha_nacimiento: null,
});

type Errores = Partial<Record<keyof AlumnoPayload, string>>;

/**
 * Fase 5: admin-fallback de alumnos. Respaldo operativo cuando el presidente
 * no cargó la lista. Mismas reglas que el autogestión del ciudadano (CI único
 * por asociación, no mueve entre asociaciones, desactivar sin borrar), pero la
 * asociación sale de la fila que se abrió, no de un token.
 */
export default function DialogAlumnosAsociacion({ asociacion, open, onOpenChange }: Props) {
  const [alumnos, setAlumnos] = useState<Alumno[]>([]);
  const [cargando, setCargando] = useState(false);
  const [modo, setModo] = useState<'lista' | 'form'>('lista');
  const [editando, setEditando] = useState<Alumno | null>(null);
  const [form, setForm] = useState<AlumnoPayload>(vacio());
  const [errores, setErrores] = useState<Errores>({});
  const [guardando, setGuardando] = useState(false);
  const [procesandoId, setProcesandoId] = useState<string | null>(null);

  const cargar = useCallback(async () => {
    if (!asociacion) return;
    setCargando(true);
    try {
      const res = await asociacionesService.listarAlumnosGestion(asociacion.id);
      setAlumnos(res);
    } catch {
      toast.error('No se pudo cargar la lista de alumnos.');
      setAlumnos([]);
    } finally {
      setCargando(false);
    }
  }, [asociacion]);

  useEffect(() => {
    if (open && asociacion) {
      setModo('lista');
      setEditando(null);
      setForm(vacio());
      setErrores({});
      cargar();
    }
  }, [open, asociacion, cargar]);

  const abrirNuevo = () => {
    setEditando(null);
    setForm(vacio());
    setErrores({});
    setModo('form');
  };

  const abrirEditar = (a: Alumno) => {
    setEditando(a);
    setForm({
      ci: a.ci,
      nombre_completo: a.nombre_completo,
      fecha_nacimiento: a.fecha_nacimiento,
    });
    setErrores({});
    setModo('form');
  };

  const set = <K extends keyof AlumnoPayload>(k: K, v: AlumnoPayload[K]) =>
    setForm((f) => ({ ...f, [k]: v }));

  const validar = (): boolean => {
    const e: Errores = {};
    if (!form.ci.trim()) e.ci = 'CI obligatorio.';
    else if (!/^[0-9]+$/.test(form.ci.trim())) e.ci = 'El CI debe ser solo dígitos.';
    if (!form.nombre_completo.trim()) e.nombre_completo = 'Nombre obligatorio.';
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    if (!asociacion || !validar()) return;
    setGuardando(true);
    try {
      if (editando) {
        await asociacionesService.actualizarAlumno(asociacion.id, editando.id, form);
        toast.success('Alumno actualizado.');
      } else {
        await asociacionesService.crearAlumno(asociacion.id, form);
        toast.success('Alumno agregado.');
      }
      setModo('lista');
      setEditando(null);
      setForm(vacio());
      await cargar();
    } catch (err: any) {
      const fields = err?.response?.data?.errors as Record<string, string[]> | undefined;
      if (fields) {
        const e: Errores = {};
        (Object.keys(fields) as (keyof AlumnoPayload)[]).forEach((k) => {
          if (fields[k]?.[0]) e[k] = fields[k][0];
        });
        setErrores(e);
        toast.error('Revisá los campos marcados.');
      } else {
        toast.error(err?.response?.data?.message ?? 'No se pudo guardar el alumno.');
      }
    } finally {
      setGuardando(false);
    }
  };

  const toggleActivo = async (a: Alumno) => {
    if (!asociacion) return;
    setProcesandoId(a.id);
    try {
      await asociacionesService.cambiarEstadoAlumno(asociacion.id, a.id, !a.activo);
      toast.success(a.activo ? 'Alumno desactivado.' : 'Alumno reactivado.');
      await cargar();
    } catch {
      toast.error('No se pudo cambiar el estado del alumno.');
    } finally {
      setProcesandoId(null);
    }
  };

  if (!asociacion) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] w-full overflow-y-auto" style={{ maxWidth: '44rem' }}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Users className="size-5 text-teal-600" />
            Alumnos de {asociacion.nombre}
          </DialogTitle>
          <DialogDescription>
            Respaldo operativo: cargá o corregí la lista cuando el presidente no lo hizo.
            El CI es único por asociación y desactivar no borra el historial de reservas.
          </DialogDescription>
        </DialogHeader>

        {modo === 'lista' ? (
          <div className="space-y-3">
            <div className="flex justify-end">
              <Button size="sm" onClick={abrirNuevo}>
                <Plus className="mr-2 size-4" /> Agregar alumno
              </Button>
            </div>

            {cargando ? (
              <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" /> Cargando…
              </div>
            ) : alumnos.length === 0 ? (
              <p className="py-10 text-center text-sm text-muted-foreground">
                Sin alumnos cargados. Agregá el primero con «Agregar alumno».
              </p>
            ) : (
              <div className="rounded-xl border bg-card">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Nombre</TableHead>
                      <TableHead>CI</TableHead>
                      <TableHead>Nacimiento</TableHead>
                      <TableHead>Estado</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {alumnos.map((a) => (
                      <TableRow key={a.id} className={!a.activo ? 'opacity-60' : ''}>
                        <TableCell className="font-medium">{a.nombre_completo}</TableCell>
                        <TableCell className="font-mono text-xs">{a.ci}</TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {a.fecha_nacimiento ?? '—'}
                        </TableCell>
                        <TableCell>
                          <Badge variant={a.activo ? 'default' : 'secondary'}>
                            {a.activo ? 'activo' : 'inactivo'}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-1">
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => abrirEditar(a)}
                              aria-label="Editar"
                            >
                              <Pencil className="size-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={procesandoId === a.id}
                              onClick={() => toggleActivo(a)}
                            >
                              {procesandoId === a.id ? (
                                <Loader2 className="mr-1 size-3.5 animate-spin" />
                              ) : (
                                <Undo2 className="mr-1 size-3.5" />
                              )}
                              {a.activo ? 'Desactivar' : 'Reactivar'}
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>
        ) : (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Nombre completo *</Label>
              <Input
                value={form.nombre_completo}
                onChange={(e) => set('nombre_completo', e.target.value)}
              />
              {errores.nombre_completo && (
                <p className="text-xs text-destructive">{errores.nombre_completo}</p>
              )}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>CI *</Label>
                <Input
                  value={form.ci}
                  onChange={(e) => set('ci', e.target.value)}
                  inputMode="numeric"
                />
                {errores.ci && <p className="text-xs text-destructive">{errores.ci}</p>}
              </div>
              <div className="space-y-1.5">
                <Label>Fecha de nacimiento</Label>
                <Input
                  type="date"
                  value={form.fecha_nacimiento ?? ''}
                  onChange={(e) => set('fecha_nacimiento', e.target.value || null)}
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button
                variant="outline"
                onClick={() => {
                  setModo('lista');
                  setEditando(null);
                  setForm(vacio());
                  setErrores({});
                }}
                disabled={guardando}
              >
                Cancelar
              </Button>
              <Button onClick={guardar} disabled={guardando}>
                {guardando && <Loader2 className="mr-2 size-4 animate-spin" />}
                {editando ? 'Guardar cambios' : 'Agregar'}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
