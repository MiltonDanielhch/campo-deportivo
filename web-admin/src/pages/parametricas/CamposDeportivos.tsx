import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import {
  AlertTriangle,
  BadgeCheck,
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
  RefreshCw,
  Link2,
  Unlink,
  Database,
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
import VincularSirebDialog from '@/components/parametricas/VincularSirebDialog';
import { camposService } from '@/services/camposService';
import { catalogoSirebService } from '@/services/catalogoSirebService';
import type { CampoDeportivo, EstadoCampo } from '@/types/parametricas';
import type { CatalogoSirebItem } from '@/services/catalogoSirebService';

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

  // ─── Catálogo SIREB ───
  const [catalogoSireb, setCatalogoSireb] = useState<CatalogoSirebItem[]>([]);
  const [cargandoSireb, setCargandoSireb] = useState(false);
  const [errorSireb, setErrorSireb] = useState<string | null>(null);
  const [vistaSireb, setVistaSireb] = useState(false); // true = catálogo SIREB, false = campos locales

  // ─── Dialog crear/editar (delegado a CampoFormDialog) ───
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [modo, setModo] = useState<'crear' | 'editar'>('crear');
  const [campoEditando, setCampoEditando] = useState<CampoDeportivo | null>(null);
  const [valoresIniciales, setValoresIniciales] = useState<{
    codigo?: string;
    nombre?: string;
  } | null>(null);

  // ─── Vinculación SIREB ───
  const [campoVinculando, setCampoVinculando] = useState<CampoDeportivo | null>(null);

  // ─── Cambio de estado ───
  const [campoEstado, setCampoEstado] = useState<CampoDeportivo | null>(null);
  const [nuevoEstado, setNuevoEstado] = useState<EstadoCampo>('activo');
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  // ─── Sincronización SIREB ───
  const [sincronizando, setSincronizando] = useState(false);

  const cargarCatalogoSireb = useCallback(async () => {
    setCargandoSireb(true);
    setErrorSireb(null);
    try {
      const respuesta = await catalogoSirebService.listarCatalogo();
      setCatalogoSireb(respuesta.data);
    } catch {
      setErrorSireb('No se pudo consultar el catálogo de SIREB.');
      toast.error('No se pudo consultar el catálogo de SIREB');
    } finally {
      setCargandoSireb(false);
    }
  }, []);

  const sincronizarTarifas = async () => {
    setSincronizando(true);
    try {
      await catalogoSirebService.sincronizarTarifas();
      toast.success('Tarifas sincronizadas desde SIREB');
      cargar();
      if (vistaSireb) cargarCatalogoSireb();
    } catch {
      toast.error('No se pudo sincronizar las tarifas');
    } finally {
      setSincronizando(false);
    }
  };

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

  // Precarga silenciosa del catálogo: la tabla de campos locales muestra el
  // nombre oficial del servicio de SIREB al que está vinculado cada campo.
  useEffect(() => {
    let activo = true;

    catalogoSirebService
      .listarCatalogo()
      .then((res) => {
        if (activo) setCatalogoSireb(res.data);
      })
      .catch(() => {
        /* la vista "Catálogo SIREB" muestra el error al abrirse */
      });

    return () => {
      activo = false;
    };
  }, []);

  const nombreServicioSireb = (campo: CampoDeportivo): string | null =>
    catalogoSireb.find((i) => i.sireb.id === campo.servicio_sireb_id)?.sireb.nombre ??
    null;

  const cambiarFiltro = (valor: string) => {
    setFiltroEstado(valor);
    setPagina(1);
  };

  const abrirNuevo = () => {
    setModo('crear');
    setCampoEditando(null);
    setValoresIniciales(null);
    setDialogAbierto(true);
  };

  /** Alta del campo local que falta para un servicio del catálogo SIREB. */
  const abrirNuevoDesdeSireb = (item: CatalogoSirebItem) => {
    setModo('crear');
    setCampoEditando(null);
    setValoresIniciales({
      codigo: `SIR-${item.sireb.codigo}`,
      nombre: item.sireb.nombre,
    });
    setDialogAbierto(true);
  };

  const abrirEditar = (campo: CampoDeportivo) => {
    setModo('editar');
    setCampoEditando(campo);
    setValoresIniciales(null);
    setDialogAbierto(true);
  };

  const abrirCambioEstado = (campo: CampoDeportivo) => {
    setCampoEstado(campo);
    setNuevoEstado(campo.estado === 'activo' ? 'mantenimiento' : 'activo');
  };

  // ─── Vinculación SIREB ───
  const abrirVincular = (campo: CampoDeportivo) => {
    setCampoVinculando(campo);
  };

  const desvincular = async (campo: CampoDeportivo) => {
    if (!campo.servicio_sireb_id) {
      toast.error('Este campo no está vinculado a SIREB');
      return;
    }

    try {
      await camposService.desvincularSireb(campo.id);
      toast.success('Campo desvinculado de SIREB');
      cargar();
    } catch {
      toast.error('No se pudo desvincular el campo');
    }
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

  const statsSireb = {
    total: catalogoSireb.length,
    vinculados: catalogoSireb.filter((i) => i.vinculacion === 'vinculado').length,
    sinCampoLocal: catalogoSireb.filter((i) => i.vinculacion === 'sin_vincular').length,
  };

  const catalogoSirebFiltrado = catalogoSireb.filter((i) => {
    if (!busqueda.trim()) return true;
    const q = busqueda.toLowerCase();
    return (
      i.sireb.codigo.toLowerCase().includes(q) ||
      i.sireb.nombre.toLowerCase().includes(q) ||
      (i.campo_local?.nombre ?? '').toLowerCase().includes(q)
    );
  });

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
            Vinculación y control operativo de servicios de Paitití / SIREB
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <div className="inline-flex rounded-full border bg-card p-1 shadow-sm">
            <Button
              size="sm"
              variant={vistaSireb ? 'ghost' : 'default'}
              className="rounded-full"
              onClick={() => setVistaSireb(false)}
            >
              <LayoutGrid className="mr-2 size-4" />
              Campos locales
            </Button>
            <Button
              size="sm"
              variant={vistaSireb ? 'default' : 'ghost'}
              className="rounded-full"
              onClick={() => {
                setVistaSireb(true);
                cargarCatalogoSireb();
              }}
            >
              <Database className="mr-2 size-4" />
              Catálogo SIREB
            </Button>
          </div>
          <Button
            onClick={sincronizarTarifas}
            disabled={sincronizando}
            variant="outline"
            className="rounded-full shadow-md"
          >
            <RefreshCw className={`mr-2 h-4 w-4 ${sincronizando ? 'animate-spin' : ''}`} />
            {sincronizando ? 'Sincronizando...' : 'Sincronizar tarifas'}
          </Button>
          <Button onClick={abrirNuevo} className="rounded-full shadow-md">
            <Plus className="mr-2 h-4 w-4" />
            Nuevo campo
          </Button>
        </div>
      </div>

      {/* ─── Stats ─── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <LayoutGrid className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">
              {vistaSireb ? 'Servicios SIREB' : 'Total'}
            </p>
            <p className="text-xl font-bold">
              {vistaSireb ? statsSireb.total : stats.total}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">
              {vistaSireb ? 'Con campo local' : 'Activos'}
            </p>
            <p className="text-xl font-bold">
              {vistaSireb ? statsSireb.vinculados : stats.activos}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
            <AlertTriangle className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">
              {vistaSireb ? 'Sin campo local' : 'En mantenimiento'}
            </p>
            <p className="text-xl font-bold">
              {vistaSireb ? statsSireb.sinCampoLocal : stats.mantenimiento}
            </p>
          </div>
        </div>
      </div>

      {/* ─── Toolbar ─── */}
      <div className="flex flex-col sm:flex-row gap-3 rounded-xl border bg-card p-3">
        <div className="relative flex-1 min-w-0">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder={
              vistaSireb
                ? 'Buscar servicio por código o nombre...'
                : 'Buscar por nombre, código o dirección...'
            }
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
            className="pl-9 bg-muted/50"
          />
        </div>
        {!vistaSireb && (
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
        )}
        {vistaSireb && (
          <Button
            variant="outline"
            className="rounded-full"
            onClick={cargarCatalogoSireb}
            disabled={cargandoSireb}
          >
            <RefreshCw className={`mr-2 h-4 w-4 ${cargandoSireb ? 'animate-spin' : ''}`} />
            Refrescar catálogo
          </Button>
        )}
      </div>

      {/* ─── Catálogo oficial de SIREB ─── */}
      {vistaSireb && (
        <div className="rounded-xl border bg-card overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/30 hover:bg-muted/30">
                <TableHead>Código</TableHead>
                <TableHead>Servicio SIREB</TableHead>
                <TableHead>Rubro / Tarifario</TableHead>
                <TableHead className="w-40">Precio</TableHead>
                <TableHead className="w-56">Campo local</TableHead>
                <TableHead className="w-32">Estado</TableHead>
                <TableHead className="w-32 text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {cargandoSireb ? (
                <TableRow>
                  <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                    Consultando el catálogo de SIREB…
                  </TableCell>
                </TableRow>
              ) : errorSireb ? (
                <TableRow>
                  <TableCell colSpan={7} className="h-32 text-center">
                    <div className="flex flex-col items-center gap-3 text-muted-foreground">
                      <AlertTriangle className="size-8 text-amber-500" />
                      <p className="font-medium">{errorSireb}</p>
                      <Button variant="outline" size="sm" onClick={cargarCatalogoSireb}>
                        Reintentar
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ) : catalogoSirebFiltrado.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                    {catalogoSireb.length === 0
                      ? 'SIREB no devolvió servicios liquidables.'
                      : 'Ningún servicio coincide con la búsqueda.'}
                  </TableCell>
                </TableRow>
              ) : (
                catalogoSirebFiltrado.map((item) => (
                  <TableRow key={item.sireb.id} className="hover:bg-muted/20 transition-colors">
                    <TableCell className="font-mono text-xs font-semibold">
                      {item.sireb.codigo}
                    </TableCell>
                    <TableCell className="font-semibold">{item.sireb.nombre}</TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {item.sireb.tarifario ?? 'liquidable'}
                    </TableCell>
                    <TableCell className="text-sm">
                      {item.sireb.precio_min != null && item.sireb.precio_max != null
                        ? item.sireb.precio_min === item.sireb.precio_max
                          ? `Bs. ${item.sireb.precio_min.toFixed(2)}`
                          : `Bs. ${item.sireb.precio_min.toFixed(2)} – Bs. ${item.sireb.precio_max.toFixed(2)}`
                        : '—'}
                    </TableCell>
                    <TableCell className="text-sm">
                      {item.campo_local ? (
                        <span>
                          {item.campo_local.nombre}
                          <span className="ml-1 font-mono text-xs text-muted-foreground">
                            {item.campo_local.codigo}
                          </span>
                        </span>
                      ) : (
                        <span className="text-amber-600 dark:text-amber-400">
                          Sin campo local
                        </span>
                      )}
                    </TableCell>
                    <TableCell>
                      {item.vinculacion === 'vinculado' ? (
                        <Badge
                          variant="default"
                          className="gap-1.5 border-emerald-200 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400"
                        >
                          <BadgeCheck className="size-3" />
                          <span className="text-xs">Vinculado</span>
                        </Badge>
                      ) : (
                        <Badge
                          variant="secondary"
                          className="gap-1.5 border-amber-200 bg-amber-500/10 px-2.5 py-1 text-amber-700 dark:border-amber-500/30 dark:text-amber-400"
                        >
                          <span className="text-xs">Sin vincular</span>
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      {item.campo_local ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => {
                            const campo = campos.find((c) => c.id === item.campo_local?.id);
                            if (campo) desvincular(campo);
                          }}
                        >
                          <Unlink className="mr-2 size-4" />
                          Desvincular
                        </Button>
                      ) : (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => abrirNuevoDesdeSireb(item)}
                        >
                          <Plus className="mr-2 size-4" />
                          Crear campo
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      )}

      {/* ─── Tabla de campos locales ─── */}
      {!vistaSireb && (
      <div className="rounded-xl border bg-card overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/30 hover:bg-muted/30">
              <TableHead className="w-20">Foto</TableHead>
              <TableHead>Código</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead className="hidden lg:table-cell">Dirección</TableHead>
              <TableHead className="w-40">Servicio SIREB</TableHead>
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
                      {campo.servicio_sireb_id ? (
                        <div className="space-y-1">
                          <Badge
                            variant="default"
                            className="gap-1.5 border-emerald-200 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400"
                          >
                            <BadgeCheck className="size-3" />
                            <span className="text-xs">
                              {campo.servicio_sireb_codigo ?? 'Vinculado'}
                            </span>
                          </Badge>
                          {nombreServicioSireb(campo) && (
                            <p className="max-w-[10rem] truncate text-xs text-muted-foreground">
                              {nombreServicioSireb(campo)}
                            </p>
                          )}
                        </div>
                      ) : (
                        <Badge
                          variant="secondary"
                          className="gap-1.5 border-slate-200 bg-slate-500/10 px-2.5 py-1 text-slate-700 dark:border-slate-500/30 dark:text-slate-400"
                        >
                          <span className="text-xs">Sin vincular</span>
                        </Badge>
                      )}
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
                          {campo.servicio_sireb_id ? (
                            <>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem onClick={() => desvincular(campo)}>
                                <Unlink className="mr-2 size-4" />
                                Desvincular de SIREB
                              </DropdownMenuItem>
                            </>
                          ) : (
                            <>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem onClick={() => abrirVincular(campo)}>
                                <Link2 className="mr-2 size-4" />
                                Vincular a SIREB
                              </DropdownMenuItem>
                            </>
                          )}
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
      )}

      {/* ─── Paginación ─── */}
      {!vistaSireb && totalPaginas > 1 && (
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
        valoresIniciales={valoresIniciales}
        onGuardado={() => {
          cargar();
          cargarCatalogoSireb();
        }}
      />

      {/* ─── Dialog de vinculación con el catálogo de SIREB ─── */}
      <VincularSirebDialog
        open={campoVinculando !== null}
        onOpenChange={(abierto) => {
          if (!abierto) setCampoVinculando(null);
        }}
        campo={campoVinculando}
        onVinculado={() => {
          cargar();
          cargarCatalogoSireb();
        }}
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
