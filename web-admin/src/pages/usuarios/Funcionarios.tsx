import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
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

export default function Funcionarios() {
  // ─── Estado de la lista ────────────────────────────────────────────────
  const [funcionarios, setFuncionarios] = useState<Funcionario[]>([]);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroRol, setFiltroRol] = useState<string>(TODOS);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Dialog de alta ────────────────────────────────────────────────────
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [nombreCompleto, setNombreCompleto] = useState('');
  const [ci, setCi] = useState('');
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [rolId, setRolId] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

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

  // ─── Activar / inactivar ───────────────────────────────────────────────
  const cambiarEstado = async (funcionario: Funcionario) => {
    const nuevoEstado = funcionario.estado === 'activo' ? 'inactivo' : 'activo';
    try {
      await funcionariosService.cambiarEstado(funcionario.id, {
        estado: nuevoEstado,
      });
      toast.success(
        `"${funcionario.nombre_completo}" ahora está ${nuevoEstado}`,
      );
      cargar();
    } catch {
      toast.error('No se pudo cambiar el estado');
    }
  };

  const errorDe = (campo: string) =>
    errores[campo] && (
      <p className="text-sm text-destructive">{errores[campo][0]}</p>
    );

  // ─── Render ────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6 p-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Funcionarios</h1>
          <p className="text-sm text-muted-foreground">
            Alta y control de estado de los usuarios del sistema
          </p>
        </div>
        <Button onClick={abrirNuevo}>Nuevo funcionario</Button>
      </div>

      {/* Filtros */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2">
          <Label className="text-sm">Rol:</Label>
          <Select
            value={filtroRol}
            onValueChange={(v) => cambiarFiltros(v, filtroEstado)}
          >
            <SelectTrigger className="w-48">
              <SelectValue placeholder="Todos los roles" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos</SelectItem>
              {roles.map((rol) => (
                <SelectItem key={rol.id} value={rol.id}>
                  {rol.nombre}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-2">
          <Label className="text-sm">Estado:</Label>
          <Select
            value={filtroEstado}
            onValueChange={(v) => cambiarFiltros(filtroRol, v)}
          >
            <SelectTrigger className="w-40">
              <SelectValue placeholder="Todos" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos</SelectItem>
              <SelectItem value="activo">Activos</SelectItem>
              <SelectItem value="inactivo">Inactivos</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Tabla */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nombre</TableHead>
              <TableHead>CI</TableHead>
              <TableHead>Usuario</TableHead>
              <TableHead>Rol</TableHead>
              <TableHead className="w-28">Estado</TableHead>
              <TableHead className="w-32 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={6} className="h-24 text-center">
                  Cargando…
                </TableCell>
              </TableRow>
            ) : funcionarios.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="h-24 text-center">
                  No hay funcionarios registrados
                </TableCell>
              </TableRow>
            ) : (
              funcionarios.map((f) => (
                <TableRow key={f.id}>
                  <TableCell className="font-medium">{f.nombre_completo}</TableCell>
                  <TableCell className="font-mono text-sm">{f.ci}</TableCell>
                  <TableCell>{f.usuario}</TableCell>
                  <TableCell>{f.rol?.nombre ?? '—'}</TableCell>
                  <TableCell>
                    <Badge
                      variant={f.estado === 'activo' ? 'default' : 'secondary'}
                    >
                      {f.estado}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <Button
                      variant={
                        f.estado === 'activo' ? 'destructive' : 'outline'
                      }
                      size="sm"
                      onClick={() => cambiarEstado(f)}
                    >
                      {f.estado === 'activo' ? 'Inactivar' : 'Activar'}
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Paginación */}
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
          <span className="text-sm text-muted-foreground">
            Página {pagina} de {totalPaginas}
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

      {/* Dialog de alta */}
      <Dialog open={dialogAbierto} onOpenChange={setDialogAbierto}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Nuevo funcionario</DialogTitle>
            <DialogDescription>
              La contraseña se guarda hasheada. El funcionario queda activo y
              podrá iniciar sesión de inmediato.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="nombre_completo">Nombre completo *</Label>
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
                <Label htmlFor="ci">CI *</Label>
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
                <Label htmlFor="usuario">Usuario *</Label>
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

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="password">Contraseña inicial *</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
                {errorDe('password')}
              </div>
              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirmar *</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Rol *</Label>
              <Select value={rolId} onValueChange={setRolId}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecciona un rol" />
                </SelectTrigger>
                <SelectContent>
                  {roles.map((rol) => (
                    <SelectItem key={rol.id} value={rol.id}>
                      {rol.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errorDe('rol_id')}
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogAbierto(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={guardando}>
                {guardando ? 'Creando…' : 'Crear funcionario'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
