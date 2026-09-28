import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  Layers,
  MapPin,
  Search,
  Trophy,
  Waves,
  Target,
  CircleDot,
  Bike,
  CheckCircle2,
  Circle,
  UserCog,
  Users,
  Map as MapIcon,
  X,
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { asignacionesService } from '@/services/asignacionesService';
import { camposService } from '@/services/camposService';
import { funcionariosService } from '@/services/funcionariosService';
import type { CampoDeportivo } from '@/types/parametricas';
import type { Funcionario, Rol } from '@/types/usuarios';

const ROL_CONTROL = 'funcionario_control';

// ─── Iconos temáticos por tipo de deporte (igual que en TiposCampo) ───

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

function iconoDeTipo(nombre: string | undefined): IconoConfig {
  if (!nombre) return FALLBACK_ICONO;
  const n = nombre
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

  if (n.includes('futbol') || n.includes('soccer') || n.includes('cancha'))
    return ICONOS_DEPORTE.futbol;
  if (n.includes('voley') || n.includes('volley')) return ICONOS_DEPORTE.voley;
  if (n.includes('tenis')) return ICONOS_DEPORTE.tenis;
  if (n.includes('basquet') || n.includes('basket')) return ICONOS_DEPORTE.basquet;
  if (n.includes('paddl') || n.includes('padel')) return ICONOS_DEPORTE.paddle;
  if (n.includes('cicl')) return ICONOS_DEPORTE.ciclismo;
  return FALLBACK_ICONO;
}

function iniciales(nombre: string): string {
  return nombre
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

export default function Asignaciones() {
  const [cargando, setCargando] = useState(true);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [funcionariosControl, setFuncionariosControl] = useState<Funcionario[]>([]);
  const [campos, setCampos] = useState<CampoDeportivo[]>([]);

  const [funcionarioId, setFuncionarioId] = useState<string>('');
  const [asignadosIds, setAsignadosIds] = useState<Set<string>>(new Set());
  const [cargandoAsignaciones, setCargandoAsignaciones] = useState(false);
  const [operandoCampoId, setOperandoCampoId] = useState<string | null>(null);

  // ─── Filtros de la grilla ───
  const [busqueda, setBusqueda] = useState('');

  // Rol funcionario_control (para filtrar)
  const rolControl = useMemo(
    () => roles.find((r) => r.nombre === ROL_CONTROL),
    [roles],
  );

  const funcionarioSeleccionado = useMemo(
    () => funcionariosControl.find((f) => f.id === funcionarioId) ?? null,
    [funcionariosControl, funcionarioId],
  );

  // ─── Carga inicial: roles + campos ─────────────────────────────────────
  useEffect(() => {
    (async () => {
      setCargando(true);
      try {
        const [rolesData, camposData] = await Promise.all([
          funcionariosService.listarRoles(),
          camposService.listarTodos(),
        ]);
        setRoles(rolesData);
        setCampos(camposData);
      } catch {
        toast.error('No se pudieron cargar los datos iniciales');
      } finally {
        setCargando(false);
      }
    })();
  }, []);

  // ─── Cargar funcionarios_control cuando se conoce el rol ───────────────
  useEffect(() => {
    if (!rolControl) return;
    funcionariosService
      .listar({ rol_id: rolControl.id, estado: 'activo', page: 1 })
      .then((resp) => setFuncionariosControl(resp.data))
      .catch(() => toast.error('No se pudieron cargar los funcionarios de control'));
  }, [rolControl]);

  // ─── Al cambiar de funcionario, cargar sus asignaciones ────────────────
  const cargarAsignaciones = useCallback(async (id: string) => {
    setCargandoAsignaciones(true);
    try {
      const asignados = await asignacionesService.porFuncionario(id);
      setAsignadosIds(new Set(asignados.map((c) => c.id)));
    } catch {
      toast.error('No se pudieron cargar las asignaciones del funcionario');
    } finally {
      setCargandoAsignaciones(false);
    }
  }, []);

  const seleccionarFuncionario = (id: string) => {
    setFuncionarioId(id);
    setBusqueda('');
    if (id) cargarAsignaciones(id);
    else setAsignadosIds(new Set());
  };

  // ─── Toggle de asignación (optimista + rollback en error) ──────────────
  const toggleAsignacion = async (campo: CampoDeportivo) => {
    if (!funcionarioId || operandoCampoId) return;

    const estabaAsignado = asignadosIds.has(campo.id);
    setOperandoCampoId(campo.id);

    try {
      if (estabaAsignado) {
        await asignacionesService.desasignar(funcionarioId, campo.id);
        setAsignadosIds((prev) => {
          const siguiente = new Set(prev);
          siguiente.delete(campo.id);
          return siguiente;
        });
        toast.success(`"${campo.nombre}" desasignado`);
      } else {
        await asignacionesService.asignar(funcionarioId, campo.id);
        setAsignadosIds((prev) => new Set(prev).add(campo.id));
        toast.success(`"${campo.nombre}" asignado`);
      }
    } catch (error: any) {
      if (error.response?.status === 422) {
        const mensaje =
          error.response.data.errors?.funcionario_id?.[0] ??
          'No se pudo completar la asignación';
        toast.error(mensaje);
      } else {
        toast.error('Error al cambiar la asignación');
      }
    } finally {
      setOperandoCampoId(null);
    }
  };

  // ─── Acciones masivas ───
  const asignarTodos = async () => {
    if (!funcionarioId) return;
    const sinAsignar = campos.filter((c) => !asignadosIds.has(c.id));
    if (sinAsignar.length === 0) {
      toast.info('Todos los campos ya están asignados');
      return;
    }

    try {
      await Promise.all(
        sinAsignar.map((c) =>
          asignacionesService.asignar(funcionarioId, c.id).catch(() => null),
        ),
      );
      setAsignadosIds(new Set(campos.map((c) => c.id)));
      toast.success(`${sinAsignar.length} campos asignados`);
    } catch {
      toast.error('No se pudieron asignar todos los campos');
    }
  };

  const desasignarTodos = async () => {
    if (!funcionarioId) return;
    const asignados = campos.filter((c) => asignadosIds.has(c.id));
    if (asignados.length === 0) {
      toast.info('No hay campos para desasignar');
      return;
    }

    try {
      await Promise.all(
        asignados.map((c) =>
          asignacionesService.desasignar(funcionarioId, c.id).catch(() => null),
        ),
      );
      setAsignadosIds(new Set());
      toast.success(`${asignados.length} campos desasignados`);
    } catch {
      toast.error('No se pudieron desasignar los campos');
    }
  };

  // ─── Campos filtrados por búsqueda ───
  const camposFiltrados = useMemo(() => {
    if (!busqueda.trim()) return campos;
    const q = busqueda.toLowerCase();
    return campos.filter(
      (c) =>
        c.nombre.toLowerCase().includes(q) ||
        c.codigo.toLowerCase().includes(q) ||
        c.direccion.toLowerCase().includes(q) ||
        (c.tipo_campo?.nombre ?? '').toLowerCase().includes(q),
    );
  }, [campos, busqueda]);

  // ─── Stats ───
  const stats = {
    asignados: asignadosIds.size,
    sinAsignar: campos.length - asignadosIds.size,
    porcentaje:
      campos.length > 0 ? Math.round((asignadosIds.size / campos.length) * 100) : 0,
  };

  // ─── Estados de carga ───
  if (cargando) {
    return (
      <div className="p-6 max-w-[1600px] mx-auto space-y-6">
        <div className="h-16 bg-muted animate-pulse rounded-xl" />
        <div className="h-12 bg-muted animate-pulse rounded-xl" />
        <div className="h-96 bg-muted animate-pulse rounded-xl" />
      </div>
    );
  }

  if (!rolControl) {
    return (
      <div className="p-6 max-w-md mx-auto mt-10">
        <Card className="border-destructive/40">
          <CardContent className="p-6 flex items-start gap-4">
            <div className="flex size-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive flex-shrink-0">
              <X className="size-5" />
            </div>
            <div>
              <h3 className="font-semibold text-destructive mb-1">
                Rol no encontrado
              </h3>
              <p className="text-sm text-muted-foreground">
                No existe el rol <code className="font-mono">{ROL_CONTROL}</code> en
                el sistema. Verificá que haya sido creado en la migración inicial.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6 max-w-[1600px] mx-auto">
      {/* ─── Header ─── */}
      <div>
        <p className="text-xs font-semibold uppercase tracking-widest text-primary mb-2">
          Usuarios
        </p>
        <h1 className="text-2xl md:text-3xl font-bold tracking-tight">
          Asignación de Control
        </h1>
        <p className="text-sm text-muted-foreground mt-1">
          Definí qué campos deportivos supervisa cada funcionario de control
        </p>
      </div>

      {/* ─── Select de funcionario ─── */}
      <Card>
        <CardContent className="p-5">
          <div className="space-y-2">
            <Label className="text-sm font-semibold flex items-center gap-1.5">
              <UserCog className="size-4 text-primary" />
              Funcionario de control
            </Label>
            <Select value={funcionarioId} onValueChange={seleccionarFuncionario}>
              <SelectTrigger className="w-full md:w-[480px]">
                <SelectValue placeholder="Elegí un funcionario para gestionar sus asignaciones" />
              </SelectTrigger>
              <SelectContent>
                {funcionariosControl.length === 0 ? (
                  <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                    <Users className="size-5 mx-auto mb-1 opacity-50" />
                    No hay funcionarios de control activos
                  </div>
                ) : (
                  funcionariosControl.map((f) => (
                    <SelectItem key={f.id} value={f.id} className="py-2">
                      <div className="flex items-center gap-2.5 w-full">
                        <Avatar className="size-7">
                          <AvatarFallback className="text-[10px] font-semibold bg-gradient-to-br from-teal-400 to-emerald-600 text-white">
                            {iniciales(f.nombre_completo)}
                          </AvatarFallback>
                        </Avatar>
                        <div className="flex flex-col min-w-0 flex-1">
                          <span className="font-medium truncate leading-tight">
                            {f.nombre_completo}
                          </span>
                          <span className="text-[11px] text-muted-foreground truncate leading-tight">
                            {f.rol?.nombre ?? '—'} · @{f.usuario}
                          </span>
                        </div>
                      </div>
                    </SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* ─── Panel del funcionario seleccionado ─── */}
      {funcionarioSeleccionado ? (
        <>
          {/* Card de resumen */}
          <Card className="overflow-hidden">
            <CardContent className="p-0">
              <div className="flex flex-col sm:flex-row gap-5 p-5">
                {/* Avatar grande + nombre */}
                <div className="flex items-center gap-4 flex-shrink-0">
                  <Avatar className="size-14 rounded-xl bg-gradient-to-br from-teal-400 to-emerald-600">
                    <AvatarFallback className="rounded-xl bg-transparent text-white text-lg font-bold">
                      {iniciales(funcionarioSeleccionado.nombre_completo)}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <p className="font-bold text-lg leading-tight">
                      {funcionarioSeleccionado.nombre_completo}
                    </p>
                    <p className="text-sm text-primary font-medium">
                      {funcionarioSeleccionado.rol?.nombre ?? '—'}
                    </p>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      CI {funcionarioSeleccionado.ci} · @{funcionarioSeleccionado.usuario}
                    </p>
                  </div>
                </div>

                {/* Stats + barra de progreso */}
                <div className="flex-1 min-w-0 sm:border-l sm:border-border sm:pl-5">
                  <div className="flex items-baseline gap-2 mb-2">
                    <span className="text-2xl font-bold tabular-nums">
                      {stats.asignados}
                    </span>
                    <span className="text-sm text-muted-foreground">
                      de {campos.length} campos supervisados
                    </span>
                    <span className="ml-auto text-sm font-semibold text-primary tabular-nums">
                      {stats.porcentaje}%
                    </span>
                  </div>

                  {/* Barra de progreso */}
                  <div className="relative h-2 bg-muted rounded-full overflow-hidden">
                    <div
                      className="absolute inset-y-0 left-0 bg-gradient-to-r from-teal-500 to-emerald-500 rounded-full transition-all duration-500"
                      style={{ width: `${stats.porcentaje}%` }}
                    />
                  </div>

                  {/* Chips */}
                  <div className="flex flex-wrap gap-2 mt-3">
                    <Badge
                      variant="outline"
                      className="gap-1.5 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30"
                    >
                      <CheckCircle2 className="size-3" />
                      {stats.asignados} asignados
                    </Badge>
                    <Badge
                      variant="outline"
                      className="gap-1.5 bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30"
                    >
                      <Circle className="size-3" />
                      {stats.sinAsignar} sin asignar
                    </Badge>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Card de campos */}
          <Card>
            <CardContent className="p-5 space-y-4">
              {/* Toolbar */}
              <div className="flex flex-col sm:flex-row gap-3">
                <div className="relative flex-1 min-w-0">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Buscar campo por nombre, código o dirección..."
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                    className="pl-9 bg-muted/50"
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={asignarTodos}
                    disabled={operandoCampoId !== null || stats.sinAsignar === 0}
                  >
                    <CheckCircle2 className="mr-1.5 size-3.5" />
                    Asignar todos
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={desasignarTodos}
                    disabled={operandoCampoId !== null || stats.asignados === 0}
                  >
                    <X className="mr-1.5 size-3.5" />
                    Quitar todos
                  </Button>
                </div>
              </div>

              {/* Grid de campos */}
              {cargandoAsignaciones ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  {[1, 2, 3, 4, 5, 6].map((i) => (
                    <div
                      key={i}
                      className="h-24 bg-muted animate-pulse rounded-xl"
                    />
                  ))}
                </div>
              ) : campos.length === 0 ? (
                <div className="py-16 text-center">
                  <MapIcon className="size-10 mx-auto mb-3 text-muted-foreground/40" />
                  <p className="font-medium text-muted-foreground">
                    No hay campos deportivos disponibles
                  </p>
                  <p className="text-xs text-muted-foreground mt-1">
                    Creá campos primero en la sección Paramétricas.
                  </p>
                </div>
              ) : camposFiltrados.length === 0 ? (
                <div className="py-16 text-center">
                  <Search className="size-10 mx-auto mb-3 text-muted-foreground/40" />
                  <p className="font-medium text-muted-foreground">
                    Ningún campo coincide con "{busqueda}"
                  </p>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setBusqueda('')}
                    className="mt-3"
                  >
                    Limpiar búsqueda
                  </Button>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  {camposFiltrados.map((campo) => {
                    const asignado = asignadosIds.has(campo.id);
                    const operando = operandoCampoId === campo.id;
                    const icono = iconoDeTipo(campo.tipo_campo?.nombre);
                    return (
                      <button
                        key={campo.id}
                        type="button"
                        onClick={() => toggleAsignacion(campo)}
                        disabled={operando}
                        className={`group relative text-left rounded-xl border-2 p-4 transition-all ${
                          asignado
                            ? 'border-teal-500/50 bg-teal-500/5 dark:bg-teal-500/10 hover:border-teal-500/70'
                            : 'border-border hover:border-muted-foreground/30 bg-card'
                        } ${operando ? 'opacity-60' : 'hover:-translate-y-0.5 hover:shadow-sm'}`}
                      >
                        {/* Indicador de estado */}
                        <div className="flex items-start gap-3">
                          <Avatar className={`size-11 rounded-lg flex-shrink-0 ${icono.bg}`}>
                            <AvatarFallback className={`rounded-lg bg-transparent text-xl ${icono.fg}`}>
                              {icono.emoji}
                            </AvatarFallback>
                          </Avatar>

                          <div className="flex-1 min-w-0">
                            <div className="flex items-start justify-between gap-2 mb-1">
                              <p className="font-semibold leading-tight truncate">
                                {campo.nombre}
                              </p>
                              <div className="flex-shrink-0">
                                <Checkbox
                                  checked={asignado}
                                  disabled={operando}
                                  onCheckedChange={(checked) => {
                                    if (checked !== asignado) toggleAsignacion(campo);
                                  }}
                                  onClick={(e) => e.stopPropagation()}
                                />
                              </div>
                            </div>
                            <p className="font-mono text-[11px] text-muted-foreground mb-1.5">
                              {campo.codigo}
                            </p>
                            <p className="text-xs text-muted-foreground flex items-start gap-1 line-clamp-2">
                              <MapPin className="size-3 flex-shrink-0 mt-0.5" />
                              <span>{campo.direccion}</span>
                            </p>
                          </div>
                        </div>

                        {/* Footer con tipo de campo */}
                        <div className="flex items-center justify-between mt-3 pt-3 border-t border-border/60">
                          <Badge
                            variant="outline"
                            className="text-[10px] gap-1 px-1.5 py-0 h-5"
                          >
                            {icono.emoji}
                            {campo.tipo_campo?.nombre ?? '—'}
                          </Badge>
                          <span
                            className={`text-[11px] font-medium ${
                              asignado ? 'text-teal-700 dark:text-teal-300' : 'text-muted-foreground'
                            }`}
                          >
                            {asignado ? 'Asignado' : 'Sin asignar'}
                          </span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        </>
      ) : (
        // ─── Estado vacío: ningún funcionario seleccionado ───
        <Card className="border-dashed">
          <CardContent className="py-16 text-center">
            <div className="flex size-16 items-center justify-center rounded-2xl bg-primary/10 text-primary mx-auto mb-4">
              <UserCog className="size-8" />
            </div>
            <h3 className="font-semibold mb-1">
              {funcionariosControl.length > 0
                ? 'Seleccioná un funcionario'
                : 'No hay funcionarios de control'}
            </h3>
            <p className="text-sm text-muted-foreground max-w-md mx-auto">
              {funcionariosControl.length > 0
                ? 'Elegí un funcionario en el selector de arriba para gestionar qué campos supervisa.'
                : 'Creá funcionarios con el rol "funcionario_control" en la sección de Funcionarios.'}
            </p>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
