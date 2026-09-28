import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  Users,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  Search,
  Filter,
  MoreVertical,
  Pencil,
  Power,
  PowerOff,
  AlertCircle,
  Calendar,
  UserPlus,
  MapPin,
  Eye,
  EyeOff,
  Dices,
  Copy,
} from 'lucide-react';
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
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import { funcionariosService } from '@/services/funcionariosService';
import type { Funcionario, Rol } from '@/types/usuarios';

const TODOS = 'todos';

// ─── Helpers ─────────────────────────────────────────────────────────────

function iniciales(nombre: string): string {
  return nombre
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

/** Fecha relativa corta */
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

// ─── Mapeo de roles a colores semánticos ───

type RolConfig = {
  bg: string;
  fg: string;
  label: string;
  icon: typeof ShieldCheck;
};

const ROL_CONFIG: Record<string, RolConfig> = {
  admin_parametricas: {
    bg: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30',
    fg: 'text-emerald-700 dark:text-emerald-400',
    label: 'Admin. Paramétricas',
    icon: ShieldCheck,
  },
  funcionario_control: {
    bg: 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/30',
    fg: 'text-sky-700 dark:text-sky-400',
    label: 'Control de Canchas',
    icon: MapPin,
  },
  admin_reservas: {
    bg: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30',
    fg: 'text-amber-700 dark:text-amber-400',
    label: 'Admin. Reservas',
    icon: Calendar,
  },
  superadmin: {
    bg: 'bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/30',
    fg: 'text-violet-700 dark:text-violet-400',
    label: 'Superadmin',
    icon: ShieldCheck,
  },
};

const ROL_FALLBACK: RolConfig = {
  bg: 'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30',
  fg: 'text-slate-700 dark:text-slate-400',
  label: 'Funcionario',
  icon: Users,
};

function configDeRol(rol: Rol | undefined): RolConfig {
  if (!rol) return ROL_FALLBACK;
  return ROL_CONFIG[rol.nombre] ?? { ...ROL_FALLBACK, label: rol.nombre };
}

// ─── Generador de contraseñas seguras ───

function generarPassword(longitud = 12): string {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
  let result = '';
  for (let i = 0; i < longitud; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

export default function Funcionarios() {
  // ─── Lista ─────────────────────────────────────────────────────────────
  const [funcionarios, setFuncionarios] = useState<Funcionario[]>([]);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroRol, setFiltroRol] = useState<string>(TODOS);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [busqueda, setBusqueda] = useState('');
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Dialog de alta ────────────────────────────────────────────────────
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [nombreCompleto, setNombreCompleto] = useState('');
  const [ci, setCi] = useState('');
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [mostrarPassword, setMostrarPassword] = useState(false);
  const [rolId, setRolId] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

  // ─── AlertDialog de inactivación ───
  const [funcionarioAInactivar, setFuncionarioAInactivar] =
    useState<Funcionario | null>(null);
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  // ─── Carga de datos ────────────────────────────────────────────────────
  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const params: { rol_id?: string; estado?: string; page: number } = {
        page: pagina,
      };
      if (filtroRol !== TODOS) params.rol_id = filtroRol;
      if (filtroEstado !== TODOS) params.estado = filtroEstado;

      const respuesta = await funcionariosService.listar(params);
      setFuncionarios(respuesta.data);
      setTotalPaginas(respuesta.last_page);
    } catch {
      toast.error('No se pudieron cargar los funcionarios');
    } finally {
      setCargando(false);
    }
  }, [filtroRol, filtroEstado, pagina]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  useEffect(() => {
    funcionariosService
      .listarRoles()
      .then(setRoles)
      .catch(() => toast.error('No se pudieron cargar los roles'));
  }, []);

  const cambiarFiltros = (rol: string, estado: string) => {
    setFiltroRol(rol);
    setFiltroEstado(estado);
    setPagina(1);
  };

  // ─── Dialog de alta ────────────────────────────────────────────────────
  const abrirNuevo = () => {
    setErrores({});
    setNombreCompleto('');
    setCi('');
    setUsuario('');
    setPassword('');
    setPasswordConfirmation('');
    setMostrarPassword(false);
    setRolId('');
    setDialogAbierto(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setGuardando(true);
    setErrores({});

    try {
      await funcionariosService.crear({
        nombre_completo: nombreCompleto.trim(),
        ci: ci.trim(),
        usuario: usuario.trim(),
        password,
        password_confirmation: passwordConfirmation,
        rol_id: rolId,
      });
      toast.success('Funcionario creado exitosamente');
      setDialogAbierto(false);
      cargar();
    } catch (error: any) {
      if (error.response?.status === 422) {
        setErrores(error.response.data.errors ?? {});
      } else {
        toast.error('Ocurrió un error al crear el funcionario');
      }
    } finally {
      setGuardando(false);
    }
  };

  // ─── Inactivar (con confirmación) / Reactivar (sin confirmación) ───
  const confirmarInactivar = async () => {
    if (!funcionarioAInactivar) return;
    setCambiandoEstado(true);
    try {
      await funcionariosService.cambiarEstado(funcionarioAInactivar.id, {
        estado: 'inactivo',
      });
      toast.success(`"${funcionarioAInactivar.nombre_completo}" inactivado`);
      setFuncionarioAInactivar(null);
      cargar();
    } catch {
      toast.error('No se pudo inactivar el funcionario');
    } finally {
      setCambiandoEstado(false);
    }
  };

  const reactivar = async (funcionario: Funcionario) => {
    try {
      await funcionariosService.cambiarEstado(funcionario.id, {
        estado: 'activo',
      });
      toast.success(`"${funcionario.nombre_completo}" reactivado`);
      cargar();
    } catch {
      toast.error('No se pudo reactivar el funcionario');
    }
  };

  const errorDe = (campo: string) =>
    errores[campo] && (
      <p className="text-xs text-destructive flex items-center gap-1">
        <AlertCircle className="size-3" />
        {errores[campo][0]}
      </p>
    );

  // ─── Filtro local por búsqueda ───
  const funcionariosFiltrados = useMemo(() => {
    if (!busqueda.trim()) return funcionarios;
    const q = busqueda.toLowerCase();
    return funcionarios.filter(
      (f) =>
        f.nombre_completo.toLowerCase().includes(q) ||
        f.usuario.toLowerCase().includes(q) ||
        f.ci.toLowerCase().includes(q) ||
        (f.rol?.nombre ?? '').toLowerCase().includes(q),
    );
  }, [funcionarios, busqueda]);

  // ─── Stats ───
  const stats = {
    total: funcionarios.length,
    activos: funcionarios.filter((f) => f.estado === 'activo').length,
    inactivos: funcionarios.filter((f) => f.estado === 'inactivo').length,
    control: funcionarios.filter(
      (f) => f.rol?.nombre === 'funcionario_control',
    ).length,
  };

  // ─── Fuerza de la contraseña ───
  const fuerzaPassword = useMemo(() => {
    if (!password) return { nivel: 0, label: '', color: '' };
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 2)
      return { nivel: score, label: 'Débil', color: 'bg-destructive' };
    if (score <= 3)
      return { nivel: score, label: 'Media', color: 'bg-amber-500' };
    return { nivel: score, label: 'Fuerte', color: 'bg-emerald-500' };
  }, [password]);

  return (
    <div className="space-y-6 p-6 max-w-[1600px] mx-auto">
      {/* ─── Header ─── */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-widest text-primary mb-2">
            Usuarios
          </p>
          <h1 className="text-2xl md:text-3xl font-bold tracking-tight">
            Funcionarios
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Alta y control de estado de los usuarios del sistema
          </p>
        </div>
        <Button onClick={abrirNuevo} className="rounded-full shadow-md">
          <UserPlus className="mr-2 size-4" />
          Nuevo funcionario
        </Button>
      </div>

      {/* ─── Stats ─── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Users className="size-5" />
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
        <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
          <div className="flex size-10 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
            <MapPin className="size-5" />
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Control</p>
            <p className="text-xl font-bold">{stats.control}</p>
          </div>
        </div>
      </div>

      {/* ─── Toolbar ─── */}
      <div className="flex flex-col sm:flex-row gap-3 rounded-xl border bg-card p-3">
        <div className="relative flex-1 min-w-0">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Buscar por nombre, usuario, CI o rol..."
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
            className="pl-9 bg-muted/50"
          />
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          <Filter className="size-4 text-muted-foreground flex-shrink-0" />
          <Select
            value={filtroRol}
            onValueChange={(v) => cambiarFiltros(v, filtroEstado)}
          >
            <SelectTrigger className="w-full sm:w-48">
              <SelectValue placeholder="Rol" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos los roles</SelectItem>
              {roles.map((rol) => (
                <SelectItem key={rol.id} value={rol.id}>
                  {configDeRol(rol).label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={filtroEstado}
            onValueChange={(v) => cambiarFiltros(filtroRol, v)}
          >
            <SelectTrigger className="w-full sm:w-40">
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
              <TableHead className="w-14">Avatar</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead className="hidden md:table-cell">CI</TableHead>
              <TableHead className="hidden md:table-cell">Usuario</TableHead>
              <TableHead>Rol</TableHead>
              <TableHead className="w-32">Estado</TableHead>
              <TableHead className="w-32 hidden lg:table-cell">Alta</TableHead>
              <TableHead className="w-14 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell
                  colSpan={8}
                  className="h-32 text-center text-muted-foreground"
                >
                  Cargando…
                </TableCell>
              </TableRow>
            ) : funcionariosFiltrados.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="h-40 text-center">
                  <div className="flex flex-col items-center gap-2 text-muted-foreground">
                    <Users className="size-8" />
                    <p className="font-medium">
                      {funcionarios.length === 0
                        ? 'No hay funcionarios registrados'
                        : 'Ningún funcionario coincide con tu búsqueda'}
                    </p>
                    <p className="text-xs">
                      {funcionarios.length === 0
                        ? 'Creá el primero con "Nuevo funcionario"'
                        : 'Probá ajustar los filtros o la búsqueda'}
                    </p>
                  </div>
                </TableCell>
              </TableRow>
            ) : (
              funcionariosFiltrados.map((f) => {
                const rolCfg = configDeRol(f.rol);
                const IconoRol = rolCfg.icon;
                return (
                  <TableRow
                    key={f.id}
                    className="hover:bg-muted/20 transition-colors"
                  >
                    <TableCell>
                      <Avatar className="size-10 rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600">
                        <AvatarFallback className="rounded-lg bg-transparent text-white text-xs font-bold">
                          {iniciales(f.nombre_completo)}
                        </AvatarFallback>
                      </Avatar>
                    </TableCell>
                    <TableCell>
                      <div>
                        <p className="font-semibold leading-tight">
                          {f.nombre_completo}
                        </p>
                        <p className="text-xs text-muted-foreground md:hidden">
                          @{f.usuario} · CI {f.ci}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell className="font-mono text-sm hidden md:table-cell">
                      {f.ci}
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      <span className="text-primary">@</span>
                      <span className="font-medium">{f.usuario}</span>
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant="outline"
                        className={`gap-1.5 px-2.5 py-1 ${rolCfg.bg}`}
                      >
                        <IconoRol className="size-3" />
                        <span className="text-xs font-medium">
                          {rolCfg.label}
                        </span>
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {f.estado === 'activo' ? (
                        <Badge
                          variant="outline"
                          className="gap-1.5 px-2.5 py-1 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30"
                        >
                          <CheckCircle2 className="size-3" />
                          Activo
                        </Badge>
                      ) : (
                        <Badge
                          variant="outline"
                          className="gap-1.5 px-2.5 py-1 bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30"
                        >
                          <XCircle className="size-3" />
                          Inactivo
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground hidden lg:table-cell tabular-nums">
                      {fechaRelativa(f.creado_en)}
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
                          <DropdownMenuItem disabled>
                            <Pencil className="mr-2 size-4" />
                            Editar
                            <Badge
                              variant="secondary"
                              className="ml-auto text-[9px]"
                            >
                              pronto
                            </Badge>
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          {f.estado === 'activo' ? (
                            <DropdownMenuItem
                              onClick={() => setFuncionarioAInactivar(f)}
                              className="text-destructive focus:text-destructive"
                            >
                              <PowerOff className="mr-2 size-4" />
                              Inactivar
                            </DropdownMenuItem>
                          ) : (
                            <DropdownMenuItem onClick={() => reactivar(f)}>
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

      {/* ─── Dialog de alta ─── */}
      <Dialog
        open={dialogAbierto}
        onOpenChange={(abierto) => {
          setDialogAbierto(abierto);
          if (!abierto) {
            setErrores({});
            setMostrarPassword(false);
          }
        }}
      >
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <UserPlus className="size-5" />
              </div>
              <div>
                <DialogTitle className="text-lg">Nuevo funcionario</DialogTitle>
                <DialogDescription className="text-xs">
                  La contraseña se guarda hasheada. El funcionario podrá iniciar
                  sesión de inmediato.
                </DialogDescription>
              </div>
            </div>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Preview del avatar */}
            <div className="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
              <Avatar className="size-12 rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600">
                <AvatarFallback className="rounded-lg bg-transparent text-white text-sm font-bold">
                  {nombreCompleto ? iniciales(nombreCompleto) : '?'}
                </AvatarFallback>
              </Avatar>
              <div className="flex-1 min-w-0">
                <p className="font-semibold leading-tight truncate">
                  {nombreCompleto || 'Nombre del funcionario'}
                </p>
                <p className="text-xs text-muted-foreground truncate">
                  {usuario ? `@${usuario}` : '@usuario'} · CI{' '}
                  {ci || '0000000'}
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="nombre_completo" className="text-sm font-semibold">
                Nombre completo <span className="text-destructive">*</span>
              </Label>
              <Input
                id="nombre_completo"
                value={nombreCompleto}
                onChange={(e) => setNombreCompleto(e.target.value)}
                placeholder="Ej: Juan Pérez"
                required
                autoFocus
              />
              {errorDe('nombre_completo')}
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="ci" className="text-sm font-semibold">
                  CI <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="ci"
                  value={ci}
                  onChange={(e) => setCi(e.target.value)}
                  placeholder="87654321"
                  required
                />
                {errorDe('ci')}
              </div>
              <div className="space-y-2">
                <Label htmlFor="usuario" className="text-sm font-semibold">
                  Usuario <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="usuario"
                  value={usuario}
                  onChange={(e) => setUsuario(e.target.value)}
                  placeholder="juan.perez"
                  required
                />
                {errorDe('usuario')}
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="password" className="text-sm font-semibold">
                  Contraseña inicial <span className="text-destructive">*</span>
                </Label>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    const nueva = generarPassword();
                    setPassword(nueva);
                    setPasswordConfirmation(nueva);
                    toast.success('Contraseña generada');
                  }}
                  className="h-7 text-xs gap-1"
                >
                  <Dices className="size-3" />
                  Generar
                </Button>
              </div>
              <div className="relative">
                <Input
                  id="password"
                  type={mostrarPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => {
                    setPassword(e.target.value);
                    setPasswordConfirmation('');
                  }}
                  placeholder="Mínimo 8 caracteres"
                  required
                  className="pr-20 font-mono text-sm"
                />
                <div className="absolute right-1 top-1/2 -translate-y-1/2 flex gap-0.5">
                  <button
                    type="button"
                    onClick={() => navigator.clipboard.writeText(password)}
                    disabled={!password}
                    className="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted disabled:opacity-30"
                    title="Copiar"
                  >
                    <Copy className="size-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => setMostrarPassword((v) => !v)}
                    className="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                    title={mostrarPassword ? 'Ocultar' : 'Mostrar'}
                  >
                    {mostrarPassword ? (
                      <EyeOff className="size-3.5" />
                    ) : (
                      <Eye className="size-3.5" />
                    )}
                  </button>
                </div>
              </div>

              {/* Indicador de fuerza */}
              {password && (
                <div className="flex items-center gap-2">
                  <div className="flex gap-0.5 flex-1">
                    {[1, 2, 3, 4, 5].map((i) => (
                      <div
                        key={i}
                        className={`h-1 flex-1 rounded-full transition-colors ${
                          i <= fuerzaPassword.nivel
                            ? fuerzaPassword.color
                            : 'bg-muted'
                        }`}
                      />
                    ))}
                  </div>
                  <span className="text-xs text-muted-foreground w-16 text-right">
                    {fuerzaPassword.label}
                  </span>
                </div>
              )}
              {errorDe('password')}
            </div>

            <div className="space-y-2">
              <Label
                htmlFor="password_confirmation"
                className="text-sm font-semibold"
              >
                Confirmar contraseña <span className="text-destructive">*</span>
              </Label>
              <Input
                id="password_confirmation"
                type={mostrarPassword ? 'text' : 'password'}
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="Repetí la contraseña"
                required
                className={
                  passwordConfirmation &&
                  password !== passwordConfirmation &&
                  passwordConfirmation.length > 0
                    ? 'border-destructive focus-visible:ring-destructive'
                    : ''
                }
              />
              {passwordConfirmation &&
                password !== passwordConfirmation &&
                passwordConfirmation.length > 0 && (
                  <p className="text-xs text-destructive flex items-center gap-1">
                    <AlertCircle className="size-3" />
                    Las contraseñas no coinciden
                  </p>
                )}
            </div>

            <div className="space-y-2">
              <Label className="text-sm font-semibold">
                Rol <span className="text-destructive">*</span>
              </Label>
              <Select value={rolId} onValueChange={setRolId}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecciona un rol" />
                </SelectTrigger>
                <SelectContent>
                  {roles.map((rol) => {
                    const cfg = configDeRol(rol);
                    const Icono = cfg.icon;
                    return (
                      <SelectItem key={rol.id} value={rol.id}>
                        <div className="flex items-center gap-2">
                          <Icono className={`size-3.5 ${cfg.fg}`} />
                          <span>{cfg.label}</span>
                        </div>
                      </SelectItem>
                    );
                  })}
                </SelectContent>
              </Select>
              {errorDe('rol_id')}
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
              <Button
                type="submit"
                disabled={
                  guardando ||
                  (password.length > 0 && password !== passwordConfirmation)
                }
                className="rounded-full"
              >
                {guardando ? 'Creando…' : 'Crear funcionario'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* ─── AlertDialog de inactivación ─── */}
      <AlertDialog
        open={funcionarioAInactivar !== null}
        onOpenChange={() => setFuncionarioAInactivar(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertCircle className="size-5 text-destructive" />
              Inactivar "{funcionarioAInactivar?.nombre_completo}"
            </AlertDialogTitle>
            <AlertDialogDescription asChild>
              <div className="space-y-3 pt-2">
                <p>
                  El funcionario perderá acceso al panel de inmediato y no podrá
                  iniciar sesión hasta que lo reactives.
                </p>
                <div className="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
                  <Avatar className="size-10 rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600">
                    <AvatarFallback className="rounded-lg bg-transparent text-white text-xs font-bold">
                      {iniciales(funcionarioAInactivar?.nombre_completo ?? '')}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold leading-tight">
                      {funcionarioAInactivar?.nombre_completo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      @{funcionarioAInactivar?.usuario} ·{' '}
                      {configDeRol(funcionarioAInactivar?.rol).label}
                    </p>
                  </div>
                </div>
                <p className="text-xs text-muted-foreground">
                  Podés reactivarlo más adelante desde el mismo menú de
                  acciones.
                </p>
              </div>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cambiandoEstado}>
              Cancelar
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmarInactivar}
              disabled={cambiandoEstado}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {cambiandoEstado ? 'Inactivando…' : 'Confirmar inactivación'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
