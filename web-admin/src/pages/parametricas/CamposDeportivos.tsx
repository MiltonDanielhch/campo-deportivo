import { useCallback, useEffect, useMemo, useState } from 'react';
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
  Clock,
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

// ── Invariante de catálogo (decisión de arquitectura) ──────────────────────
// SIREB/Paitití es la FUENTE DE VERDAD de servicios. La única vía legítima de
// alta es DESDE el Catálogo SIREB (botón "+ Crear campo" por fila): pre-pobló
// el código/servicio de recaudaciones y vos sumás solo lo local (coords, tipo,
// horarios, hora nocturna). Un "+ Nuevo campo" libre crearía un campo que puede
// no tener servicio en SIREB jamás → huérfano. Peor: hoy el path de reserva
// (SolicitudReservaService::verificarFranjas) valida contra tarifa LOCAL, así
// que un huérfano con tarifa local aparecería reservable con precio fantasma.
// Se oculta hasta que el resolvedor dependa del mirror de SIREB. Regla:
//   reservable ⟺ servicio_sireb_id con al menos una tarifa liquidable vigente.
const PERMITIR_ALTA_LIBRE_DE_CAMPO = false;

const TODOS = 'todos';

const CATEGORIA_LABEL: Record<string, string> = {
  alquiler_base: 'Alquiler base',
  servicio_adicional: 'Servicio adicional',
  arriendo_periodico: 'Arriendo periódico',
  hospedaje: 'Hospedaje',
  piscina: 'Piscina',
  servicio_especial: 'Servicio especial',
  evento_especial: 'Evento especial',
  no_clasificado: 'No clasificado',
};

const CATEGORIA_BADGE_CLASS: Record<string, string> = {
  alquiler_base:
    'border-emerald-200 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400',
  servicio_adicional:
    'border-sky-200 bg-sky-500/10 text-sky-700 dark:border-sky-500/30 dark:text-sky-400',
  arriendo_periodico:
    'border-violet-200 bg-violet-500/10 text-violet-700 dark:border-violet-500/30 dark:text-violet-400',
  hospedaje:
    'border-amber-200 bg-amber-500/10 text-amber-700 dark:border-amber-500/30 dark:text-amber-400',
  piscina:
    'border-cyan-200 bg-cyan-500/10 text-cyan-700 dark:border-cyan-500/30 dark:text-cyan-400',
  servicio_especial:
    'border-orange-200 bg-orange-500/10 text-orange-700 dark:border-orange-500/30 dark:text-orange-400',
  evento_especial:
    'border-fuchsia-200 bg-fuchsia-500/10 text-fuchsia-700 dark:border-fuchsia-500/30 dark:text-fuchsia-400',
  no_clasificado:
    'border-slate-200 bg-slate-500/10 text-slate-700 dark:border-slate-500/30 dark:text-slate-400',
};

const ACCION_LABEL: Record<string, string> = {
  crear_campo_simple: 'Crear campo simple',
  agregar_como_variante: 'Agregar variante',
  modulo_piscina: 'Módulo piscina',
  desvincular_directo: 'Desvincular',
  gestionar_variante: 'Gestionar variante',
  fuera_de_alcance: 'Fuera de alcance',
  revisar_clasificacion: 'Revisar',
};

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

// [B87/B104] Modalidad de reserva (fuente: enum ModalidadReservaCampo, campo.modalidad_reserva).
// Eje ORTOGONAL a la vinculación SIREB (directo/variante = cómo se cobra). La columna debe
// mostrar AMBOS: antes solo pintaba color de vinculación e infería la máquina mal (piscina
// turno_por_perfil + directo salía verde igual que cancha-franja).
const MODALIDAD_CONFIG: Record<
  string,
  { label: string; className: string; icon: typeof Clock }
> = {
  franja_horaria: {
    label: 'Reserva por franja',
    className:
      'border-emerald-200 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400',
    icon: LayoutGrid,
  },
  turno_por_perfil: {
    label: 'Reserva por turno',
    className:
      'border-cyan-200 bg-cyan-500/10 text-cyan-700 dark:border-cyan-500/30 dark:text-cyan-400',
    icon: Clock,
  },
  sin_reserva_online: {
    label: 'Solo ventanilla',
    className:
      'border-slate-200 bg-slate-500/10 text-slate-700 dark:border-slate-500/30 dark:text-slate-400',
    icon: AlertTriangle,
  },
};

export default function CamposDeportivos() {
  const navigate = useNavigate();

  // ─── Lista ───
  const [campos, setCampos] = useState<CampoDeportivo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [busqueda, setBusqueda] = useState('');
  const [filtroCategoria, setFiltroCategoria] = useState<string>(TODOS);
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
    // FIX (Estrategia B): uuid del servicio SIREB de la fila del catálogo.
    // Se lo pasamos al dialog para que, tras crear el campo, lo vincule con
    // camposService.vincularSireb(id, uuid). Sin esto el dialog crea el campo
    // y lo deja "Sin vincular" (consistente, pero no enlaza).
    servicioSirebId?: string | null;
  } | null>(null);

  // ─── Vinculación SIREB ───
  const [campoVinculando, setCampoVinculando] = useState<CampoDeportivo | null>(null);

  // ─── Cambio de estado ───
  const [campoEstado, setCampoEstado] = useState<CampoDeportivo | null>(null);
  const [nuevoEstado, setNuevoEstado] = useState<EstadoCampo>('activo');
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  // [B93] desvincular es destructivo: confirma antes de ejecutar.
  const [campoDesvinculando, setCampoDesvinculando] = useState<CampoDeportivo | null>(null);
  const [desvinculando, setDesvinculando] = useState(false);

  // ─── Sincronización SIREB ───
  const [sincronizando, setSincronizando] = useState(false);

  const cargarCatalogoSireb = useCallback(async () => {
    setCargandoSireb(true);
    setErrorSireb(null);
    try {
      // El GET /v1/admin/catalogo-sireb/campos sirve caché (TTL 10min).
      // Sin el refresh explícito, un servicio recién dado de alta en SIREB
      // tarda hasta 10 min en verse acá. Por eso primero invalidamos caché
      // y después leemos.
      const refresh = await catalogoSirebService.refrescarCatalogo();
      const respuesta = await catalogoSirebService.listarCatalogo();
      setCatalogoSireb(respuesta.data);
      const total = refresh?.data?.total_servicios ?? respuesta.data?.length ?? 0;
      toast.success(`Catálogo SIREB refrescado: ${total} servicio${total === 1 ? '' : 's'}`);
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


  const cambiarFiltro = (valor: string) => {
    setFiltroEstado(valor);
    setPagina(1);
  };

  const vinculacionPorCampo = useMemo(() => {
  const map = new Map<
    string,
    {
      directo?: CatalogoSirebItem;
      variantes: CatalogoSirebItem[];
    }
  >();

  for (const item of catalogoSireb) {
    const campoId = item.campo_local?.id;
    if (!campoId) continue;

    const entry = map.get(campoId) ?? { variantes: [] };

    const campo = campos.find((c) => c.id === campoId);

    const esDirecto =
      item.tipo_vinculacion === 'directo' ||
      (!!campo && campo.servicio_sireb_id === item.sireb.id);

    if (esDirecto) {
      entry.directo = item;
    } else {
      entry.variantes.push(item);
    }

    map.set(campoId, entry);
  }

  return map;
}, [catalogoSireb, campos]);

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
      // FIX (Estrategia B): llevamos el uuid del servicio para que el dialog
      // vincule tras crear. item.sireb.id es el uuid de SIREB (el mismo que
      // usa nombreServicioSireb para casar campo↔servicio).
      servicioSirebId: item.sireb.id,
    });
    setDialogAbierto(true);
  };

  const abrirEditar = (campo: CampoDeportivo) => {
    setModo('editar');
    setCampoEditando(campo);
    // FIX (Estrategia B): en editar NO hace falta pasar el uuid por acá: el
    // dialog lo lee de campo.servicio_sireb_id. valoresIniciales queda null.
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

  // [B93] el click ya no ejecuta: abre confirmación. Ambos call-points (catálogo y
  // tabla local) llaman a desvincular(), así que los dos pasan por el diálogo sin
  // tocar sus onClick.
  const desvincular = (campo: CampoDeportivo) => {
    if (!campo.servicio_sireb_id) {
      toast.error('Este campo no está vinculado a SIREB');
      return;
    }
    setCampoDesvinculando(campo);
  };

  const confirmarDesvincular = async () => {
    if (!campoDesvinculando) return;
    setDesvinculando(true);
    try {
      await camposService.desvincularSireb(campoDesvinculando.id);
      toast.success('Campo desvinculado de SIREB');
      setCampoDesvinculando(null);
      cargar();
    } catch {
      toast.error('No se pudo desvincular el campo');
    } finally {
      setDesvinculando(false);
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
      directos: catalogoSireb.filter((i) => i.tipo_vinculacion === 'directo').length,
      porVariantes: catalogoSireb.filter((i) => i.tipo_vinculacion === 'variante').length,
      sinCampoLocal: catalogoSireb.filter((i) => i.vinculacion === 'sin_vincular').length,
      // [B90b] fueraDeAlcance = legítimos no-gestionables (kiosco/hospedaje/espectáculo/
      // curso/gimnasio). Los accionables (crear simple + agregar variante + revisar +
      // módulo piscina) = sinCampoLocal - fueraDeAlcance, se calcula en el card pa' no
      // enumerar buckets y no volver a sub-contar (B90 contaba solo crear_campo_simple).
    fueraDeAlcance: catalogoSireb.filter(
          (i) => i.vinculacion === 'sin_vincular' && i.accion_recomendada === 'fuera_de_alcance',
        ).length,
      };

    const categoriasDisponibles = useMemo(
    () => Array.from(new Set(catalogoSireb.map((i) => i.clasificacion))).sort(),
    [catalogoSireb],
  );

  const catalogoSirebFiltrado = catalogoSireb.filter((i) => {
    if (filtroCategoria !== TODOS && i.clasificacion !== filtroCategoria) {
      return false;
    }

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
      {/* ─── Header  */}
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
          {PERMITIR_ALTA_LIBRE_DE_CAMPO && (
            <Button onClick={abrirNuevo}>
              <Plus className="mr-2 h-4 w-4" /> Nuevo campo
            </Button>
          )}
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
              {vistaSireb ? 'Huecos accionables (sin campo)' : 'En mantenimiento'}
            </p>
            <p className="text-xl font-bold">
              {vistaSireb
                ? statsSireb.sinCampoLocal - statsSireb.fueraDeAlcance
                : stats.mantenimiento}
            </p>
            {vistaSireb && (
              <p className="text-xs text-muted-foreground">
                + {statsSireb.fueraDeAlcance} fuera de alcance
              </p>
            )}
          </div>
        </div>
      </div>

      {/* ─── Toolbar ── */}
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
          <div className="flex items-center gap-2">
            <Filter className="size-4 text-muted-foreground" />
            <Select value={filtroCategoria} onValueChange={setFiltroCategoria}>
              <SelectTrigger className="w-56">
                <SelectValue placeholder="Clasificación" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={TODOS}>Todas las clasificaciones</SelectItem>
                {categoriasDisponibles.map((categoria) => (
                  <SelectItem key={categoria} value={categoria}>
                    {CATEGORIA_LABEL[categoria] ?? categoria}
                  </SelectItem>
                ))}
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
          <div className="overflow-x-auto">
<Table className="w-full min-w-[1020px] table-fixed">
  <TableHeader>
    <TableRow className="bg-muted/30 hover:bg-muted/30">
      <TableHead className="w-[25%]">Servicio SIREB</TableHead>
      <TableHead className="hidden lg:table-cell w-[14%]">Clasificación</TableHead>
      <TableHead className="w-[12%]">Precio</TableHead>
      <TableHead className="w-[22%]">Campo local</TableHead>
      <TableHead className="w-[12%]">Vinculación</TableHead>
      <TableHead className="w-[190px] text-right">Acciones</TableHead>
    </TableRow>
  </TableHeader>
  <TableBody>
    {cargandoSireb ? (
      <TableRow>
        <TableCell colSpan={6} className="h-32 text-center text-muted-foreground">
          Consultando el catálogo de SIREB…
        </TableCell>
      </TableRow>
    ) : errorSireb ? (
      <TableRow>
        <TableCell colSpan={6} className="h-32 text-center">
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
        <TableCell colSpan={6} className="h-32 text-center text-muted-foreground">
          {catalogoSireb.length === 0
            ? 'SIREB no devolvió servicios liquidables.'
            : 'Ningún servicio coincide con la búsqueda.'}
        </TableCell>
      </TableRow>
    ) : (
      catalogoSirebFiltrado.map((item) => (
        <TableRow key={item.sireb.id} className="hover:bg-muted/20 transition-colors">
          {/* Servicio SIREB: nombre + código como subtítulo (absorbe la columna Código) */}
           <TableCell className="overflow-hidden">
            <p className="truncate font-semibold" title={item.sireb.nombre}>
              {item.sireb.nombre}
            </p>
            <p
              className="truncate font-mono text-[11px] text-muted-foreground"
              title={item.sireb.codigo}
            >
              {item.sireb.codigo}
            </p>
          </TableCell>

          <TableCell className="hidden lg:table-cell overflow-hidden">
            <div className="space-y-1">
              <Badge
                variant="outline"
                className={`gap-1.5 px-2 py-0.5 text-xs ${
                  CATEGORIA_BADGE_CLASS[item.clasificacion] ??
                  'border-slate-200 bg-slate-500/10 text-slate-700 dark:border-slate-500/30 dark:text-slate-400'
                }`}
              >
                {CATEGORIA_LABEL[item.clasificacion] ?? item.clasificacion}
              </Badge>
              {item.sireb.unidad_medida && (
                <p className="text-xs text-muted-foreground capitalize">
                  {item.sireb.unidad_medida}
                </p>
              )}
            </div>
          </TableCell>

          <TableCell className="text-sm whitespace-nowrap overflow-hidden">
            {item.sireb.precio_min != null && item.sireb.precio_max != null
              ? item.sireb.precio_min === item.sireb.precio_max
                ? `Bs. ${item.sireb.precio_min.toFixed(2)}`
                : `Bs. ${item.sireb.precio_min.toFixed(2)} – ${item.sireb.precio_max.toFixed(2)}`
              : '—'}
          </TableCell>

          <TableCell className="overflow-hidden">
            {item.campo_local ? (
              <div className="flex flex-col">
                <span className="truncate font-medium" title={item.campo_local.nombre}>
                  {item.campo_local.nombre}
                </span>
                {item.campo_local.codigo && (
                  <span
                    className="truncate font-mono text-xs text-muted-foreground"
                    title={item.campo_local.codigo}
                  >
                    {item.campo_local.codigo}
                  </span>
                )}
              </div>
            ) : (
              <span className="text-amber-600 dark:text-amber-400 whitespace-nowrap">
                Sin campo local
              </span>
            )}
          </TableCell>

           <TableCell className="overflow-hidden">
            {item.vinculacion === 'vinculado' ? (
              <div className="space-y-1">
                <Badge
                  variant="default"
                  className="gap-1.5 border-emerald-200 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400"
                >
                  <BadgeCheck className="size-3" />
                  <span className="text-xs">
                    {item.tipo_vinculacion === 'variante' ? 'Vía variante' : 'Directo'}
                  </span>
                </Badge>
                {item.tipo_vinculacion === 'variante' && item.campo_local?.variante_nombre && (
                  <p
                    className="max-w-[12rem] truncate text-xs text-muted-foreground"
                    title={item.campo_local.variante_nombre}
                  >
                    {item.campo_local.variante_nombre}
                  </p>
                )}
              </div>
            ) : (
              <Badge
                variant="secondary"
                className="gap-1.5 border-amber-200 bg-amber-500/10 px-2.5 py-1 text-amber-700 dark:border-amber-500/30 dark:text-amber-400"
              >
                <span className="text-xs">Sin vincular</span>
              </Badge>
            )}
          </TableCell>

          <TableCell className="overflow-hidden text-right">
            {(() => {
              const campoLocal = item.campo_local
                ? campos.find((c) => c.id === item.campo_local?.id)
                : null;
              const label = ACCION_LABEL[item.accion_recomendada] ?? 'Revisar';
              // icono-only <xl (con title), icono+texto xl+ → sin truncado "Crear campo si…"
              const btn = (
                contenido: React.ReactNode,
                variant: 'outline' | 'ghost' | 'secondary',
                disabled = false,
                onClick?: () => void,
                extraCls = '',
              ) => (
                <Button
                  variant={variant}
                  size="sm"
                  disabled={disabled}
                  onClick={onClick}
                  title={label}
                  className={`xl:px-3 px-2 max-w-full ${extraCls}`}
                >
                  {contenido}
                  <span className="hidden xl:inline ml-2 truncate">{label}</span>
                </Button>
              );

              switch (item.accion_recomendada) {
                case 'crear_campo_simple':
                  return btn(<Plus className="size-4" />, 'outline', false, () => abrirNuevoDesdeSireb(item));
                case 'agregar_como_variante':
                  return btn(<Link2 className="size-4" />, 'outline', true);
                case 'modulo_piscina':
                  return campoLocal
                    ? btn(
                        <Clock className="size-4" />,
                        'ghost',
                        false,
                        () => navigate(`/panel/parametricas/campos/${campoLocal.id}/piscina/turnos`),
                      )
                    : <Badge variant="secondary" className="text-xs">{label}</Badge>;
                case 'desvincular_directo':
                  return campoLocal
                    ? btn(<Unlink className="size-4" />, 'ghost', false, () => desvincular(campoLocal))
                    : btn(<Unlink className="size-4" />, 'ghost', true);
                case 'gestionar_variante':
                  return btn(<Unlink className="size-4" />, 'ghost', true);
                case 'fuera_de_alcance':
                  return <Badge variant="secondary" className="text-xs">{label}</Badge>;
                case 'revisar_clasificacion':
                default:
                  return btn(<AlertTriangle className="size-4" />, 'ghost', true);
              }
            })()}
          </TableCell>
        </TableRow>
      ))
    )}
  </TableBody>
</Table>
          </div>
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
              <TableHead className="w-40">Modalidad / Servicio SIREB</TableHead>
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
                      {(() => {
                        const info = vinculacionPorCampo.get(campo.id);
                        const tieneCatalogo = catalogoSireb.length > 0;
                        const modalKey = campo.modalidad_reserva;
                        const modal =
                          (modalKey ? MODALIDAD_CONFIG[modalKey] : undefined) ??
                          MODALIDAD_CONFIG.sin_reserva_online;
                        const ModalIcono = modal.icon;

                        const badgeModalidad = (
                          <Badge variant="outline" className={`gap-1.5 px-2.5 py-1 ${modal.className}`}>
                            <ModalIcono className="size-3" />
                            <span className="text-xs">{modal.label}</span>
                          </Badge>
                        );

                        // Fallback si el catálogo SIREB no cargó: comportamiento viejo + modalidad
                        // (la modalidad no depende del catálogo, viene del campo, se pinta siempre).
                        if (!tieneCatalogo) {
                          return (
                            <div className="space-y-1">
                              {badgeModalidad}
                              {campo.servicio_sireb_id ? (
                                <Badge
                                  variant="default"
                                  className="gap-1.5 border-emerald-200 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400"
                                >
                                  <BadgeCheck className="size-3" />
                                  <span className="text-xs">
                                    {campo.servicio_sireb_codigo ?? 'Vinculado'}
                                  </span>
                                </Badge>
                              ) : (
                                <Badge
                                  variant="secondary"
                                  className="gap-1.5 border-slate-200 bg-slate-500/10 px-2.5 py-1 text-slate-700 dark:border-slate-500/30 dark:text-slate-400"
                                >
                                  <span className="text-xs">Sin vincular</span>
                                </Badge>
                              )}
                            </div>
                          );
                        }

                        const directo = info?.directo;
                        const variantes = info?.variantes ?? [];

                        if (!directo && variantes.length === 0) {
                          return (
                            <div className="space-y-1">
                              {badgeModalidad}
                              <Badge
                                variant="secondary"
                                className="gap-1.5 border-slate-200 bg-slate-500/10 px-2.5 py-1 text-slate-700 dark:border-slate-500/30 dark:text-slate-400"
                              >
                                <span className="text-xs">Sin vincular</span>
                              </Badge>
                            </div>
                          );
                        }

                        return (
                          <div className="space-y-1">
                            {badgeModalidad}
                            {directo && (
                              <Badge
                                variant="default"
                                className="gap-1.5 border-emerald-200 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400"
                              >
                                <BadgeCheck className="size-3" />
                                <span className="text-xs">
                                  Directo · {directo.sireb.codigo}
                                </span>
                              </Badge>
                            )}

                            {variantes.length > 0 && (
                              <Badge
                                variant="secondary"
                                className="gap-1.5 border-sky-200 bg-sky-500/10 px-2.5 py-1 text-sky-700 dark:border-sky-500/30 dark:text-sky-400"
                              >
                                <Database className="size-3" />
                                <span className="text-xs">
                                  Variantes ({variantes.length})
                                </span>
                              </Badge>
                            )}

                            {variantes.length > 0 && (
                              <p
                                className="max-w-[12rem] truncate text-xs text-muted-foreground"
                                title={variantes.map((v) => v.sireb.codigo).join(', ')}
                              >
                                {variantes
                                  .slice(0, 2)
                                  .map((v) => v.sireb.codigo)
                                  .join(', ')}
                                {variantes.length > 2 ? ` +${variantes.length - 2}` : ''}
                              </p>
                            )}
                          </div>
                        );
                      })()}
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
                          {campo.modalidad_reserva === 'turno_por_perfil' && (
                            <DropdownMenuItem
                              onClick={() =>
                                navigate(`/panel/parametricas/campos/${campo.id}/piscina/turnos`)
                              }
                            >
                              <Clock className="mr-2 size-4" />
                              Gestionar turnos piscina
                            </DropdownMenuItem>
                          )}
                          {(() => {
                          const info = vinculacionPorCampo.get(campo.id);
                          const tieneCatalogo = catalogoSireb.length > 0;
                          const directo = info?.directo;
                          const variantes = info?.variantes ?? [];

                          const puedeDesvincularDirecto =
                            tieneCatalogo
                              ? Boolean(directo)
                              : Boolean(campo.servicio_sireb_id);

                          return (
                            <>
                              <DropdownMenuSeparator />

                              {puedeDesvincularDirecto ? (
                                <DropdownMenuItem onClick={() => desvincular(campo)}>
                                  <Unlink className="mr-2 size-4" />
                                  Desvincular servicio directo
                                </DropdownMenuItem>
                              ) : (
                                <DropdownMenuItem onClick={() => abrirVincular(campo)}>
                                  <Link2 className="mr-2 size-4" />
                                  Vincular servicio directo
                                </DropdownMenuItem>
                              )}

                              {tieneCatalogo && variantes.length > 0 && (
                                <DropdownMenuItem disabled>
                                  <Database className="mr-2 size-4" />
                                  Gestionar variantes ({variantes.length})
                                </DropdownMenuItem>
                              )}
                            </>
                          );
                        })()}
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

      {/* [B93] confirmación destructiva. Texto neutro-verdadero: NO afirma el efecto
          sobre reservas porque eso depende de si sincronizar-tarifas (305) purga las
          tarifas huérfanas (ver nota del frente). */}
      <AlertDialog open={campoDesvinculando !== null} onOpenChange={() => setCampoDesvinculando(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              Desvincular "{campoDesvinculando?.nombre}" de SIREB
            </AlertDialogTitle>
            <AlertDialogDescription>
              Se quitará el vínculo de este campo con su servicio de recaudaciones de SIREB.
              El campo dejará de poder reservarse online con las tarifas de ese servicio.
              Para volver a habilitarlo, vinculalo de nuevo desde esta misma pantalla.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={desvinculando}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmarDesvincular}
              disabled={desvinculando}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {desvinculando ? 'Desvinculando…' : 'Desvincular'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
