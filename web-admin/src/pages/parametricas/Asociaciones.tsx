import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Building2, Loader2, Pencil, Plus, Search, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger } from '@/components/ui/select';
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
import type { Asociacion, AsociacionPayload, TipoAsociacion } from '@/types/asociaciones';
import DialogAlumnosAsociacion from '@/components/parametricas/DialogAlumnosAsociacion';

const TIPOS: { value: TipoAsociacion; label: string }[] = [
  { value: 'departamental', label: 'Departamental' },
  { value: 'municipal', label: 'Municipal' },
];

const vacio = (): AsociacionPayload => ({
  nit: '',
  nombre: '',
  tipo: null,
  convenio_numero: null,
  convenio_desde: null,
  convenio_hasta: null,
  responsable_nombre: '',
  responsable_ci: '',
  responsable_telefono: null,
});

type ErroresForm = Partial<Record<keyof AsociacionPayload, string>>;

export default function Asociaciones() {
  const [lista, setLista] = useState<Asociacion[]>([]);
  const [cargando, setCargando] = useState(false);
  const [buscar, setBuscar] = useState('');
  const [filtroTipo, setFiltroTipo] = useState<string>('todos');

  const [open, setOpen] = useState(false);
  const [editando, setEditando] = useState<Asociacion | null>(null);
  const [form, setForm] = useState<AsociacionPayload>(vacio());
  const [errores, setErrores] = useState<ErroresForm>({});
  const [guardando, setGuardando] = useState(false);

  const [alumnosOpen, setAlumnosOpen] = useState(false);
  const [alumnosAsociacion, setAlumnosAsociacion] = useState<Asociacion | null>(null);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const res = await asociacionesService.listar({
        buscar: buscar.trim() || undefined,
        tipo: filtroTipo !== 'todos' ? filtroTipo : undefined,
        per_page: 50,
      });
      setLista(res.data ?? []);
    } catch {
      toast.error('No se pudo cargar el listado de asociaciones.');
      setLista([]);
    } finally {
      setCargando(false);
    }
  }, [buscar, filtroTipo]);

  useEffect(() => {
    const t = setTimeout(cargar, 300); // debounce barato del buscador
    return () => clearTimeout(t);
  }, [cargar]);

  const abrirNueva = () => {
    setEditando(null);
    setForm(vacio());
    setErrores({});
    setOpen(true);
  };

  const abrirEditar = (a: Asociacion) => {
    setEditando(a);
    setForm({
      nit: a.nit,
      nombre: a.nombre,
      tipo: a.tipo,
      convenio_numero: a.convenio_numero,
      convenio_desde: a.convenio_desde,
      convenio_hasta: a.convenio_hasta,
      responsable_nombre: a.responsable_nombre,
      responsable_ci: a.responsable_ci,
      responsable_telefono: a.responsable_telefono,
    });
    setErrores({});
    setOpen(true);
  };

  const set = <K extends keyof AsociacionPayload>(k: K, v: AsociacionPayload[K]) =>
    setForm((f) => ({ ...f, [k]: v }));

  const validar = (): boolean => {
    const e: ErroresForm = {};
    if (!form.nit.trim()) e.nit = 'NIT obligatorio.';
    if (!form.nombre.trim()) e.nombre = 'Nombre obligatorio.';
    if (!form.responsable_nombre.trim()) e.responsable_nombre = 'Responsable obligatorio.';
    if (!form.responsable_ci.trim()) e.responsable_ci = 'CI del responsable obligatorio.';
    if (form.convenio_desde && form.convenio_hasta && form.convenio_hasta < form.convenio_desde)
      e.convenio_hasta = 'El fin no puede ser antes del inicio.';
    setErrores(e);
    return Object.keys(e).length === 0;
  };

  const guardar = async () => {
    if (!validar()) return;
    setGuardando(true);
    try {
      if (editando) {
        await asociacionesService.actualizar(editando.id, form);
        toast.success('Asociación actualizada.');
      } else {
        await asociacionesService.crear(form);
        toast.success('Asociación dada de alta.');
      }
      setOpen(false);
      await cargar();
    } catch (err: any) {
      // El backend manda 422 con errors.{campo} (Laravel FormRequest).
      const fields = err?.response?.data?.errors as Record<string, string[]> | undefined;
      if (fields) {
        const e: ErroresForm = {};
        (Object.keys(fields) as (keyof AsociacionPayload)[]).forEach((k) => {
          if (fields[k]?.[0]) e[k] = fields[k][0];
        });
        setErrores(e);
        toast.error('Revisá los campos marcados.');
      } else {
        toast.error(err?.response?.data?.message ?? 'No se pudo guardar la asociación.');
      }
    } finally {
      setGuardando(false);
    }
  };

  const toggleActivo = async (a: Asociacion) => {
    try {
      await asociacionesService.cambiarEstado(a.id, !a.activo);
      toast.success(a.activo ? 'Asociación inhabilitada.' : 'Asociación reactivada.');
      await cargar();
    } catch {
      toast.error('No se pudo cambiar el estado.');
    }
  };

  return (
    <div className="space-y-4 p-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
            <Building2 className="size-6 text-teal-600" />
            Asociaciones con convenio
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Alta de asociaciones, su tipo (departamental / municipal), vigencia de convenio y
            responsable que reserva. El tipo define qué tarifa de convenio se aplica en ventanilla.
          </p>
        </div>
        <Button onClick={abrirNueva}>
          <Plus className="mr-2 size-4" /> Nueva asociación
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-0 flex-1 sm:max-w-xs">
          <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={buscar}
            onChange={(e) => setBuscar(e.target.value)}
            placeholder="Buscar por nombre, NIT o responsable…"
            className="pl-8"
          />
        </div>
        <Select value={filtroTipo} onValueChange={setFiltroTipo}>
          <SelectTrigger className="w-44" aria-label="Filtrar por tipo">
            <span className="text-left">
              {filtroTipo === 'todos'
                ? 'Todos los tipos'
                : TIPOS.find((t) => t.value === filtroTipo)?.label ?? filtroTipo}
            </span>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="todos">Todos los tipos</SelectItem>
            {TIPOS.map((t) => (
              <SelectItem key={t.value} value={t.value}>
                {t.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="rounded-xl border bg-card">
        {cargando ? (
          <div className="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
            <Loader2 className="size-4 animate-spin" /> Cargando…
          </div>
        ) : lista.length === 0 ? (
          <p className="py-12 text-center text-sm text-muted-foreground">
            Sin asociaciones que mostrar. Dad de alta la primera con «Nueva asociación».
          </p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Asociación</TableHead>
                <TableHead>NIT</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Responsable</TableHead>
                <TableHead>Convenio</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {lista.map((a) => (
                <TableRow key={a.id} className={!a.activo ? 'opacity-60' : ''}>
                  <TableCell className="font-medium">{a.nombre}</TableCell>
                  <TableCell className="font-mono text-xs">{a.nit}</TableCell>
                  <TableCell>
                    {a.tipo ? (
                      <Badge variant="secondary" className="capitalize">
                        {a.tipo}
                      </Badge>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <span className="block text-sm">{a.responsable_nombre}</span>
                    <span className="block font-mono text-xs text-muted-foreground">
                      CI {a.responsable_ci}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="block text-xs">
                      {a.convenio_numero ? `N.º ${a.convenio_numero}` : 'sin n.º'}
                    </span>
                    <span className="block text-xs text-muted-foreground">
                      {a.convenio_desde ?? '—'} → {a.convenio_hasta ?? '—'}
                    </span>
                    <Badge
                      variant={a.vigente ? 'default' : 'outline'}
                      className="mt-1 text-[10px]"
                    >
                      {a.vigente ? 'vigente' : 'vencido/sin fecha'}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={a.activo ? 'default' : 'secondary'}>
                      {a.activo ? 'activa' : 'inhabilitada'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => {
                          setAlumnosAsociacion(a);
                          setAlumnosOpen(true);
                        }}
                        aria-label="Gestionar alumnos"
                      >
                        <Users className="size-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => abrirEditar(a)} aria-label="Editar">
                        <Pencil className="size-4" />
                      </Button>
                      <Button variant="ghost" size="sm" onClick={() => toggleActivo(a)}>
                        {a.activo ? 'Inhabilitar' : 'Reactivar'}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[92vh] w-full overflow-y-auto" style={{ maxWidth: '40rem' }}>
          <DialogHeader>
            <DialogTitle>{editando ? 'Editar asociación' : 'Nueva asociación'}</DialogTitle>
            <DialogDescription>
              El tipo (departamental / municipal) es lo que después empareja la variante de convenio
              en ventanilla. Dejalo vacío si la asociación está registrada pero aún sin convenio.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Nombre de la asociación *</Label>
              <Input value={form.nombre} onChange={(e) => set('nombre', e.target.value)} />
              {errores.nombre && <p className="text-xs text-destructive">{errores.nombre}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>NIT *</Label>
              <Input value={form.nit} onChange={(e) => set('nit', e.target.value)} />
              {errores.nit && <p className="text-xs text-destructive">{errores.nit}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Tipo de convenio</Label>
              <Select
                value={form.tipo ?? 'ninguno'}
                onValueChange={(v) => set('tipo', v === 'ninguno' ? null : (v as TipoAsociacion))}
              >
                <SelectTrigger aria-label="Tipo">
                  <span className="text-left">
                    {form.tipo ? TIPOS.find((t) => t.value === form.tipo)?.label : 'Sin tipo'}
                  </span>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="ninguno">Sin tipo</SelectItem>
                  {TIPOS.map((t) => (
                    <SelectItem key={t.value} value={t.value}>
                      {t.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <p className="text-sm font-semibold">Convenio</p>
            </div>
            <div className="space-y-1.5">
              <Label>N.º de convenio</Label>
              <Input
                value={form.convenio_numero ?? ''}
                onChange={(e) => set('convenio_numero', e.target.value || null)}
              />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-1.5">
                <Label>Desde</Label>
                <Input
                  type="date"
                  value={form.convenio_desde ?? ''}
                  onChange={(e) => set('convenio_desde', e.target.value || null)}
                />
              </div>
              <div className="space-y-1.5">
                <Label>Hasta</Label>
                <Input
                  type="date"
                  value={form.convenio_hasta ?? ''}
                  onChange={(e) => set('convenio_hasta', e.target.value || null)}
                />
                {errores.convenio_hasta && (
                  <p className="text-xs text-destructive">{errores.convenio_hasta}</p>
                )}
              </div>
            </div>

            <div className="space-y-1.5 sm:col-span-2">
              <p className="text-sm font-semibold">Responsable que reserva</p>
            </div>
            <div className="space-y-1.5">
              <Label>Nombre *</Label>
              <Input
                value={form.responsable_nombre}
                onChange={(e) => set('responsable_nombre', e.target.value)}
              />
              {errores.responsable_nombre && (
                <p className="text-xs text-destructive">{errores.responsable_nombre}</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label>CI *</Label>
              <Input value={form.responsable_ci} onChange={(e) => set('responsable_ci', e.target.value)} />
              {errores.responsable_ci && (
                <p className="text-xs text-destructive">{errores.responsable_ci}</p>
              )}
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Teléfono</Label>
              <Input
                value={form.responsable_telefono ?? ''}
                onChange={(e) => set('responsable_telefono', e.target.value || null)}
              />
              <p className="text-[11px] text-muted-foreground">
                El CI del responsable es la identidad con la que el sistema reconoce el convenio al
                crear la reserva (gate por CI + variante de convenio en ventanilla).
              </p>
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => setOpen(false)} disabled={guardando}>
              Cancelar
            </Button>
            <Button onClick={guardar} disabled={guardando}>
              {guardando && <Loader2 className="mr-2 size-4 animate-spin" />}
              {editando ? 'Guardar cambios' : 'Dar de alta'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      <DialogAlumnosAsociacion
        asociacion={alumnosAsociacion}
        open={alumnosOpen}
        onOpenChange={setAlumnosOpen}
      />
    </div>
  );
}
