import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Banknote,
  CheckCircle2,
  Filter,
  ImageIcon,
  LayoutGrid,
  MapPin,
  MoreVertical,
  Pencil,
  Plus,
  Power,
  Search,
  XCircle,
} from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import CampoFormDialog from '@/components/parametricas/CampoFormDialog';
import { camposService } from '@/services/camposService';
import type { CampoDeportivo, EstadoCampo } from '@/types/parametricas';

const TODOS = 'todos';

const ESTADO_CONFIG: Record<
  EstadoCampo,
  {
    variant: 'default' | 'secondary' | 'destructive';
    className: string;
    icon: typeof CheckCircle2;
    descripcion: string;
  }
> = {
  activo: {
    variant: 'default',
    className:
      'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30',
    icon: CheckCircle2,
    descripcion: 'El campo puede recibir reservas normalmente.',
  },
  mantenimiento: {
    variant: 'secondary',
    className:
      'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30',
    icon: AlertTriangle,
    descripcion: 'El campo queda bloqueado para reservas futuras hasta reactivarlo.',
  },
  inactivo: {
    variant: 'destructive',
    className:
      'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30',
    icon: XCircle,
    descripcion: 'El campo queda fuera de operación de forma indefinida.',
  },
};

export default function CamposDeportivos() {
  const navigate = useNavigate();

  // ─── Lista ───
  const [campos, setCampos] = useState<CampoDeportivo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [busqueda, setBusqueda] = useState('');
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Dialog crear/editar (delegado a CampoFormDialog) ───
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [modo, setModo] = useState<'crear' | 'editar'>('crear');
  const [campoEditando, setCampoEditando] = useState<CampoDeportivo | null>(null);

  // ─── Cambio de estado ───
  const [campoEstado, setCampoEstado] = useState<CampoDeportivo | null>(null);
  const [nuevoEstado, setNuevoEstado] = useState<EstadoCampo>('activo');
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const params: { estado?: string; page: number } = { page: pagina };
      if (filtroEstado !== TODOS) params.estado = filtroEstado;

      const respuesta = await camposService.listar(params);
      setCampos(respuesta.data);
      setTotalPaginas(respuesta.last_page);
    } catch {
      toast.error('No se pudieron cargar los campos deportivos');
    } finally {
      setCargando(false);
    }
  }, [filtroEstado, pagina]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const cambiarFiltro = (valor: string) => {
    setFiltroEstado(valor);
    setPagina(1);
  };

  const abrirNuevo = () => {
    setModo('crear');
    setCampoEditando(null);
    setDialogAbierto(true);
  };

  const abrirEditar = (campo: CampoDeportivo) => {
    setModo('editar');
    setCampoEditando(campo);
    setDialogAbierto(true);
  };

  const abrirCambioEstado = (campo: CampoDeportivo) => {
    setCampoEstado(campo);
    setNuevoEstado(campo.estado === 'activo' ? 'mantenimiento' : 'activo');
  };

  const confirmarCambioEstado = async () => {
    if (!campoEstado) return;
    setCambiandoEstado(true);
    try {
      await camposService.cambiarEstado(campoEstado.id, { estado: nuevoEstado });
      toast.success(`"${campoEstado.nombre}" ahora está en estado ${nuevoEstado}`);
      setCampoEstado(null);
      cargar();
    } catch {
      toast.error('No se pudo cambiar el estado del campo');
    } finally {
      setCambiandoEstado(false);
    }
  };

  // ─── Filtro local por búsqueda ───
  const camposFiltrados = campos.filter((c) => {
    if (!busqueda.trim()) return true;
    const q = busqueda.toLowerCase();
    return (
      c.nombre.toLowerCase().includes(q) ||
      c.codigo.toLowerCase().includes(q) ||
      c.direccion.toLowerCase().includes(q) ||
      (c.tipo_campo?.nombre ?? '').toLowerCase().includes(q)
    );
  });

  const stats = {
    total: campos.length,
    activos: campos.filter((c) => c.estado === 'activo').length,
    mantenimiento: campos.filter((c) => c.estado === 'mantenimiento').length,
  };

  return (
    <div className="space-y-6 p-6 max-w-[1600px] mx-auto">
      {/* ─── Header ─── */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-widest text-primary mb-2">
            Paramétricas
          </p>
          <h1 className="text-2xl md:text-3xl font-bold tracking-tight">
            Campos Deportivos
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Alta, edición y control de estado de los campos del sistema
          </p>
        </div>
        <Button onClick={abrirNuevo} className="rounded-full shadow-md">
          <Plus className="mr-2 h-4 w-4" />
          Nuevo campo
        </Button>
      </div>

      {/* ─── Stats ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <LayoutGrid className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Total</p>
            <p className="text-xl font-bold">{stats.total}</p>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Activos</p>
            <p className="text-xl font-bold">{stats.activos}</p>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
            <AlertTriangle className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">En mantenimiento</p>
            <p className="text-xl font-bold">{stats.mantenimiento}</p>
          </div>
        </div>
      </div>

      {/* ─── Toolbar ─── */}
      <div className="flex flex-col sm:flex-row gap-3 rounded-xl border bg-card p-3">
        <div className="relative flex-1 min-w-0">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Buscar por nombre, código o dirección..."
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
            className="pl-9 bg-muted/50"
          />
        </div>
        <div className="flex items-center gap-2">
          <Filter className="size-4 text-muted-foreground" />
          <Select value={filtroEstado} onValueChange={cambiarFiltro}>
            <SelectTrigger className="w-48">
              <SelectValue placeholder="Estado" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos los estados</SelectItem>
              <SelectItem value="activo">Activos</SelectItem>
              <SelectItem value="mantenimiento">En mantenimiento</SelectItem>
              <SelectItem value="inactivo">Inactivos</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* ─── Tabla ─── */}
      <div className="rounded-xl border bg-card overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/30 hover:bg-muted/30">
              <TableHead className="w-20">Foto</TableHead>
              <TableHead>Código</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead className="hidden lg:table-cell">Dirección</TableHead>
              <TableHead className="w-36">Estado</TableHead>
              <TableHead className="w-14 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                  Cargando…
                </TableCell>
              </TableRow>
            ) : camposFiltrados.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center">
                  <div className="flex flex-col items-center gap-2 text-muted-foreground">
                    <MapPin className="size-8" />
                    <p className="font-medium">
                      {campos.length === 0
                        ? 'No hay campos registrados'
                        : 'Ningún campo coincide con tu búsqueda'}
                    </p>
                  </div>
                </TableCell>
              </TableRow>
            ) : (
              camposFiltrados.map((campo) => {
                const config = ESTADO_CONFIG[campo.estado];
                const IconoEstado = config.icon;
                return (
                  <TableRow key={campo.id} className="hover:bg-muted/20 transition-colors">
                    <TableCell>
                      {campo.imagen_url ? (
                        <img
                          src={campo.imagen_url}
                          alt={campo.nombre}
                          className="h-12 w-12 rounded-lg object-cover border"
                        />
                      ) : (
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                          <ImageIcon className="h-5 w-5" />
                        </div>
                      )}
                    </TableCell>
                    <TableCell className="font-mono text-xs text-muted-foreground">
                      {campo.codigo}
                    </TableCell>
                    <TableCell className="font-semibold">{campo.nombre}</TableCell>
                    <TableCell className="text-sm">
                      {campo.tipo_campo?.nombre ?? '—'}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground hidden lg:table-cell max-w-xs truncate">
                      {campo.direccion}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={config.variant}
                        className={`gap-1.5 px-2.5 py-1 ${config.className}`}
                      >
                        <IconoEstado className="size-3" />
                        <span className="capitalize">{campo.estado}</span>
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="size-8">
                            <MoreVertical className="size-4" />
                            <span className="sr-only">Acciones</span>
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                          <DropdownMenuItem onClick={() => abrirEditar(campo)}>
                            <Pencil className="mr-2 size-4" />
                            Editar campo
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            onClick={() =>
                              navigate(`/panel/parametricas/campos/${campo.id}/tarifas`)
                            }
                          >
                            <Banknote className="mr-2 size-4" />
                            Gestionar tarifas
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem onClick={() => abrirCambioEstado(campo)}>
                            <Power className="mr-2 size-4" />
                            Cambiar estado
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {/* ─── Paginación ─── */}
      {totalPaginas > 1 && (
        <div className="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={pagina === 1}
            onClick={() => setPagina((p) => p - 1)}
          >
            Anterior
          </Button>
          <span className="text-sm text-muted-foreground px-2">
            Página <strong className="text-foreground">{pagina}</strong> de{' '}
            <strong className="text-foreground">{totalPaginas}</strong>
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={pagina === totalPaginas}
            onClick={() => setPagina((p) => p + 1)}
          >
            Siguiente
          </Button>
        </div>
      )}

      {/* ─── Dialog crear/editar (componente extraído) ─── */}
      <CampoFormDialog
        open={dialogAbierto}
        onOpenChange={setDialogAbierto}
        modo={modo}
        campo={campoEditando}
        onGuardado={cargar}
      />

      {/* ─── Alert dialog cambio de estado ─── */}
      <AlertDialog open={campoEstado !== null} onOpenChange={() => setCampoEstado(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              Cambiar estado de "{campoEstado?.nombre}"
            </AlertDialogTitle>
            <AlertDialogDescription>
              Estado actual:{' '}
              <Badge
                variant={ESTADO_CONFIG[campoEstado?.estado ?? 'activo'].variant}
                className="ml-1"
              >
                {campoEstado?.estado}
              </Badge>
              <p className="mt-3">{ESTADO_CONFIG[nuevoEstado].descripcion}</p>
            </AlertDialogDescription>
          </AlertDialogHeader>

          <div className="py-4">
            <Select
              value={nuevoEstado}
              onValueChange={(v) => setNuevoEstado(v as EstadoCampo)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="activo">Activo</SelectItem>
                <SelectItem value="mantenimiento">En mantenimiento</SelectItem>
                <SelectItem value="inactivo">Inactivo</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <AlertDialogFooter>
            <AlertDialogCancel disabled={cambiandoEstado}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmarCambioEstado}
              disabled={cambiandoEstado || nuevoEstado === campoEstado?.estado}
            >
              {cambiandoEstado ? 'Aplicando…' : 'Confirmar cambio'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
