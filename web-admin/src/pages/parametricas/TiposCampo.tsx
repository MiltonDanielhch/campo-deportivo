import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
  Plus,
  Search,
  Filter,
  MoreVertical,
  Pencil,
  Power,
  PowerOff,
  Layers,
  Trophy,
  AlertCircle,
  CheckCircle2,
  XCircle,
  Waves,
  Target,
  CircleDot,
  Bike,
  MapPin,
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
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { tiposCampoService } from '@/services/tiposCampoService';
import type { EstadoTipoCampo, TipoCampo } from '@/types/parametricas';

const TODOS = 'todos';

// ─── Iconos y colores por tipo de deporte (mapeo por palabras clave) ────

type IconoConfig = {
  Icon: typeof Trophy;
  bg: string;
  fg: string;
  emoji: string;
};

const ICONOS_DEPORTE: Record<string, IconoConfig> = {
  futbol: {
    Icon: Trophy,
    bg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    fg: 'text-emerald-600 dark:text-emerald-400',
    emoji: '⚽',
  },
  voley: {
    Icon: Waves,
    bg: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    fg: 'text-sky-600 dark:text-sky-400',
    emoji: '🏐',
  },
  tenis: {
    Icon: Target,
    bg: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400',
    fg: 'text-yellow-600 dark:text-yellow-400',
    emoji: '🎾',
  },
  basquet: {
    Icon: CircleDot,
    bg: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    fg: 'text-orange-600 dark:text-orange-400',
    emoji: '🏀',
  },
  paddle: {
    Icon: Target,
    bg: 'bg-pink-500/10 text-pink-600 dark:text-pink-400',
    fg: 'text-pink-600 dark:text-pink-400',
    emoji: '🏓',
  },
  ciclismo: {
    Icon: Bike,
    bg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
    fg: 'text-purple-600 dark:text-purple-400',
    emoji: '🚴',
  },
};

const FALLBACK_ICONO: IconoConfig = {
  Icon: Layers,
  bg: 'bg-slate-500/10 text-slate-600 dark:text-slate-400',
  fg: 'text-slate-600 dark:text-slate-400',
  emoji: '🏟️',
};

/** Mapea el nombre del tipo de campo a un icono temático */
function iconoDeTipo(nombre: string): IconoConfig {
  const n = nombre
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, ''); // sin tildes

  if (n.includes('futbol') || n.includes('soccer') || n.includes('cancha'))
    return ICONOS_DEPORTE.futbol;
  if (n.includes('voley') || n.includes('volley')) return ICONOS_DEPORTE.voley;
  if (n.includes('tenis')) return ICONOS_DEPORTE.tenis;
  if (n.includes('basquet') || n.includes('basket')) return ICONOS_DEPORTE.basquet;
  if (n.includes('paddl') || n.includes('padel')) return ICONOS_DEPORTE.paddle;
  if (n.includes('cicl')) return ICONOS_DEPORTE.ciclismo;
  return FALLBACK_ICONO;
}

// ─── Helpers ─────────────────────────────────────────────────────────────

const fechaRelativa = (iso: string): string => {
  const diff = Date.now() - new Date(iso).getTime();
  const minutos = Math.floor(diff / 60_000);
  const horas = Math.floor(minutos / 60);
  const dias = Math.floor(horas / 24);
  const meses = Math.floor(dias / 30);

  if (minutos < 1) return 'recién';
  if (minutos < 60) return `hace ${minutos} min`;
  if (horas < 24) return `hace ${horas} h`;
  if (dias < 30) return `hace ${dias} d`;
  if (meses < 12) return `hace ${meses} mes${meses === 1 ? '' : 'es'}`;
  return new Date(iso).toLocaleDateString('es-BO', { dateStyle: 'medium' });
};

const ESTADO_CONFIG: Record<
  EstadoTipoCampo,
  {
    variant: 'default' | 'secondary';
    className: string;
    Icon: typeof CheckCircle2;
  }
> = {
  activo: {
    variant: 'default',
    className:
      'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30',
    Icon: CheckCircle2,
  },
  inactivo: {
    variant: 'secondary',
    className:
      'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30',
    Icon: XCircle,
  },
};

export default function TiposCampo() {
  // ─── Lista ───
  const [tipos, setTipos] = useState<TipoCampo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [busqueda, setBusqueda] = useState('');
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Dialog alta/edición ───
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [tipoEnEdicion, setTipoEnEdicion] = useState<TipoCampo | null>(null);
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

  // ─── AlertDialog de inhabilitación ───
  const [tipoAInhabilitar, setTipoAInhabilitar] = useState<TipoCampo | null>(null);
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const params: { estado?: string; page: number } = { page: pagina };
      if (filtroEstado !== TODOS) params.estado = filtroEstado;

      const respuesta = await tiposCampoService.listar(params);
      setTipos(respuesta.data);
      setTotalPaginas(respuesta.last_page);
    } catch {
      toast.error('No se pudieron cargar los tipos de campo');
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

  // ─── Dialog abrir ───
  const abrirNuevo = () => {
    setTipoEnEdicion(null);
    setNombre('');
    setDescripcion('');
    setErrores({});
    setDialogAbierto(true);
  };

  const abrirEditar = (tipo: TipoCampo) => {
    setTipoEnEdicion(tipo);
    setNombre(tipo.nombre);
    setDescripcion(tipo.descripcion ?? '');
    setErrores({});
    setDialogAbierto(true);
  };

  // ─── Submit ───
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setGuardando(true);
    setErrores({});

    const payload = {
      nombre: nombre.trim(),
      descripcion: descripcion.trim() || null,
    };

    try {
      if (tipoEnEdicion) {
        await tiposCampoService.actualizar(tipoEnEdicion.id, payload);
        toast.success('Tipo de campo actualizado');
      } else {
        await tiposCampoService.crear(payload);
        toast.success('Tipo de campo creado');
      }
      setDialogAbierto(false);
      cargar();
    } catch (error: any) {
      if (error.response?.status === 422) {
        setErrores(error.response.data.errors ?? {});
      } else {
        toast.error('Ocurrió un error al guardar');
      }
    } finally {
      setGuardando(false);
    }
  };

  // ─── Inhabilitar (con confirmación) / Reactivar (sin confirmación) ───
  const confirmarInhabilitar = async () => {
    if (!tipoAInhabilitar) return;
    setCambiandoEstado(true);
    try {
      await tiposCampoService.inhabilitar(tipoAInhabilitar.id);
      toast.success(`"${tipoAInhabilitar.nombre}" inhabilitado`);
      setTipoAInhabilitar(null);
      cargar();
    } catch {
      toast.error('No se pudo inhabilitar el tipo de campo');
    } finally {
      setCambiandoEstado(false);
    }
  };

  const reactivar = async (tipo: TipoCampo) => {
    try {
      await tiposCampoService.reactivar(tipo.id);
      toast.success(`"${tipo.nombre}" reactivado`);
      cargar();
    } catch {
      toast.error('No se pudo reactivar el tipo de campo');
    }
  };

  // ─── Filtro local por búsqueda ───
  const tiposFiltrados = tipos.filter((t) => {
    if (!busqueda.trim()) return true;
    const q = busqueda.toLowerCase();
    return (
      t.nombre.toLowerCase().includes(q) ||
      (t.descripcion ?? '').toLowerCase().includes(q)
    );
  });

  // ─── Stats ───
  const stats = {
    total: tipos.length,
    activos: tipos.filter((t) => t.estado === 'activo').length,
    inactivos: tipos.filter((t) => t.estado === 'inactivo').length,
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
            Tipos de Campo
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Categorías de campos deportivos del sistema (fútbol, tenis, etc.)
          </p>
        </div>
        <Button onClick={abrirNuevo} className="rounded-full shadow-md">
          <Plus className="mr-2 size-4" />
          Nuevo tipo
        </Button>
      </div>

      {/* ─── Stats cards ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Layers className="size-5" />
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
          <div className="flex size-10 items-center justify-center rounded-lg bg-slate-500/10 text-slate-600 dark:text-slate-400">
            <XCircle className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Inactivos</p>
            <p className="text-xl font-bold">{stats.inactivos}</p>
          </div>
        </div>
      </div>

      {/* ─── Toolbar unificada ─── */}
      <div className="flex flex-col sm:flex-row gap-3 rounded-xl border bg-card p-3">
        <div className="relative flex-1 min-w-0">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Buscar por nombre o descripción..."
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
              <TableHead className="w-14">Icono</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead className="hidden md:table-cell">Descripción</TableHead>
              <TableHead className="w-32">Estado</TableHead>
              <TableHead className="w-32 hidden lg:table-cell">Creado</TableHead>
              <TableHead className="w-14 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell
                  colSpan={6}
                  className="h-32 text-center text-muted-foreground"
                >
                  Cargando…
                </TableCell>
              </TableRow>
            ) : tiposFiltrados.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="h-40 text-center">
                  <div className="flex flex-col items-center gap-2 text-muted-foreground">
                    <MapPin className="size-8" />
                    <p className="font-medium">
                      {tipos.length === 0
                        ? 'No hay tipos de campo registrados'
                        : 'Ningún tipo coincide con tu búsqueda'}
                    </p>
                    <p className="text-xs">
                      {tipos.length === 0
                        ? 'Creá el primero con el botón "Nuevo tipo"'
                        : 'Probá ajustar los filtros o la búsqueda'}
                    </p>
                  </div>
                </TableCell>
              </TableRow>
            ) : (
              tiposFiltrados.map((tipo) => {
                const config = ESTADO_CONFIG[tipo.estado];
                const iconoConfig = iconoDeTipo(tipo.nombre);
                const IconoEstado = config.Icon;
                return (
                  <TableRow
                    key={tipo.id}
                    className="hover:bg-muted/20 transition-colors"
                  >
                    <TableCell>
                      <Avatar
                        className={`size-10 rounded-lg ${iconoConfig.bg}`}
                      >
                        <AvatarFallback
                          className={`rounded-lg bg-transparent text-lg ${iconoConfig.fg}`}
                        >
                          {iconoConfig.emoji}
                        </AvatarFallback>
                      </Avatar>
                    </TableCell>
                    <TableCell>
                      <div>
                        <p className="font-semibold">{tipo.nombre}</p>
                        <p className="text-xs text-muted-foreground md:hidden">
                          {tipo.descripcion ?? 'Sin descripción'}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground hidden md:table-cell">
                      {tipo.descripcion ?? (
                        <span className="italic text-muted-foreground/60">
                          Sin descripción
                        </span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={config.variant}
                        className={`gap-1.5 px-2.5 py-1 ${config.className}`}
                      >
                        <IconoEstado className="size-3" />
                        <span className="capitalize">{tipo.estado}</span>
                      </Badge>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground hidden lg:table-cell tabular-nums">
                      {fechaRelativa(tipo.creado_en)}
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="size-8">
                            <MoreVertical className="size-4" />
                            <span className="sr-only">Acciones</span>
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                          <DropdownMenuItem onClick={() => abrirEditar(tipo)}>
                            <Pencil className="mr-2 size-4" />
                            Editar
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          {tipo.estado === 'activo' ? (
                            <DropdownMenuItem
                              onClick={() => setTipoAInhabilitar(tipo)}
                              className="text-destructive focus:text-destructive"
                            >
                              <PowerOff className="mr-2 size-4" />
                              Inhabilitar
                            </DropdownMenuItem>
                          ) : (
                            <DropdownMenuItem onClick={() => reactivar(tipo)}>
                              <Power className="mr-2 size-4" />
                              Reactivar
                            </DropdownMenuItem>
                          )}
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

      {/* ─── Dialog alta/edición ─── */}
      <Dialog
        open={dialogAbierto}
        onOpenChange={(abierto) => {
          setDialogAbierto(abierto);
          if (!abierto) {
            setTipoEnEdicion(null);
            setNombre('');
            setDescripcion('');
            setErrores({});
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="text-lg">
              {tipoEnEdicion ? 'Editar tipo de campo' : 'Nuevo tipo de campo'}
            </DialogTitle>
            <DialogDescription>
              {tipoEnEdicion
                ? 'Modifica los datos del tipo de campo.'
                : 'Registra una nueva categoría de campo deportivo.'}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Preview del icono en vivo */}
            <div className="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
              <Avatar
                className={`size-12 rounded-lg ${iconoDeTipo(nombre).bg}`}
              >
                <AvatarFallback
                  className={`rounded-lg bg-transparent text-2xl ${iconoDeTipo(nombre).fg}`}
                >
                  {iconoDeTipo(nombre).emoji}
                </AvatarFallback>
              </Avatar>
              <div className="text-xs text-muted-foreground">
                <p className="font-medium text-foreground">Icono automático</p>
                <p>Se asigna según el nombre del tipo</p>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="nombre" className="text-sm font-semibold">
                Nombre <span className="text-destructive">*</span>
              </Label>
              <Input
                id="nombre"
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                placeholder="Ej: Fútbol 7"
                required
                autoFocus
              />
              {errores.nombre && (
                <p className="text-xs text-destructive flex items-center gap-1">
                  <AlertCircle className="size-3" />
                  {errores.nombre[0]}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="descripcion" className="text-sm font-semibold">
                Descripción{' '}
                <span className="text-muted-foreground font-normal">
                  (opcional)
                </span>
              </Label>
              <Input
                id="descripcion"
                value={descripcion}
                onChange={(e) => setDescripcion(e.target.value)}
                placeholder="Ej: Cancha profesional de fútbol 7"
              />
              {errores.descripcion && (
                <p className="text-xs text-destructive flex items-center gap-1">
                  <AlertCircle className="size-3" />
                  {errores.descripcion[0]}
                </p>
              )}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogAbierto(false)}
                disabled={guardando}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={guardando} className="rounded-full">
                {guardando
                  ? 'Guardando…'
                  : tipoEnEdicion
                    ? 'Guardar cambios'
                    : 'Crear tipo'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* ─── AlertDialog de inhabilitación (acción destructiva) ─── */}
      <AlertDialog
        open={tipoAInhabilitar !== null}
        onOpenChange={() => setTipoAInhabilitar(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertCircle className="size-5 text-destructive" />
              Inhabilitar "{tipoAInhabilitar?.nombre}"
            </AlertDialogTitle>
            <AlertDialogDescription asChild>
              <div className="space-y-3 pt-2">
                <p>
                  ¿Estás seguro de que querés inhabilitar este tipo de campo?
                </p>
                <div className="rounded-lg border bg-muted/40 p-3 text-sm space-y-1">
                  <p>
                    <strong className="text-foreground">
                      Los campos existentes no se ven afectados
                    </strong>
                    , pero no se podrán crear campos nuevos de este tipo.
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Podés reactivarlo más adelante desde el mismo menú.
                  </p>
                </div>
              </div>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cambiandoEstado}>
              Cancelar
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmarInhabilitar}
              disabled={cambiandoEstado}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {cambiandoEstado ? 'Inhabilitando…' : 'Confirmar inhabilitación'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
