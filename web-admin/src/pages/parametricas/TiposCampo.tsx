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
import { tiposCampoService } from '@/services/tiposCampoService';
import type { EstadoTipoCampo, TipoCampo } from '@/types/parametricas';

const TODOS = 'todos';

export default function TiposCampo() {
  // ─── Estado de la lista ────────────────────────────────────────────────
  const [tipos, setTipos] = useState<TipoCampo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Estado del dialog de alta/edición ─────────────────────────────────
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [tipoEnEdicion, setTipoEnEdicion] = useState<TipoCampo | null>(null);
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

  // ─── Carga de datos ────────────────────────────────────────────────────
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

  // Resetear página al cambiar el filtro
  const cambiarFiltro = (valor: string) => {
    setFiltroEstado(valor);
    setPagina(1);
  };

  // ─── Dialog: abrir para crear / editar ─────────────────────────────────
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

  // ─── Guardar (crear o actualizar) ──────────────────────────────────────
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

  // ─── Inhabilitar / reactivar ───────────────────────────────────────────
  const cambiarEstado = async (tipo: TipoCampo) => {
    try {
      if (tipo.estado === 'activo') {
        await tiposCampoService.inhabilitar(tipo.id);
        toast.success(`"${tipo.nombre}" inhabilitado`);
      } else {
        await tiposCampoService.reactivar(tipo.id);
        toast.success(`"${tipo.nombre}" reactivado`);
      }
      cargar();
    } catch {
      toast.error('No se pudo cambiar el estado');
    }
  };

  // ─── Render ────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6 p-6">
      {/* Encabezado */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Tipos de Campo</h1>
          <p className="text-sm text-muted-foreground">
            Administra las categorías de campos deportivos del sistema
          </p>
        </div>
        <Button onClick={abrirNuevo}>Nuevo tipo de campo</Button>
      </div>

      {/* Filtro por estado */}
      <div className="flex items-center gap-2">
        <Label htmlFor="filtro-estado" className="text-sm">
          Estado:
        </Label>
        <Select value={filtroEstado} onValueChange={cambiarFiltro}>
          <SelectTrigger id="filtro-estado" className="w-44">
            <SelectValue placeholder="Filtrar por estado" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TODOS}>Todos</SelectItem>
            <SelectItem value="activo">Activos</SelectItem>
            <SelectItem value="inactivo">Inactivos</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Tabla */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nombre</TableHead>
              <TableHead>Descripción</TableHead>
              <TableHead className="w-28">Estado</TableHead>
              <TableHead className="w-40 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={4} className="h-24 text-center">
                  Cargando…
                </TableCell>
              </TableRow>
            ) : tipos.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="h-24 text-center">
                  No hay tipos de campo registrados
                </TableCell>
              </TableRow>
            ) : (
              tipos.map((tipo) => (
                <TableRow key={tipo.id}>
                  <TableCell className="font-medium">{tipo.nombre}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {tipo.descripcion ?? '—'}
                  </TableCell>
                  <TableCell>
                    <Badge variant={tipo.estado === 'activo' ? 'default' : 'secondary'}>
                      {tipo.estado}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => abrirEditar(tipo)}
                      >
                        Editar
                      </Button>
                      <Button
                        variant={tipo.estado === 'activo' ? 'destructive' : 'outline'}
                        size="sm"
                        onClick={() => cambiarEstado(tipo)}
                      >
                        {tipo.estado === 'activo' ? 'Inhabilitar' : 'Reactivar'}
                      </Button>
                    </div>
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

      {/* Dialog de alta/edición */}
      <Dialog open={dialogAbierto} onOpenChange={setDialogAbierto}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {tipoEnEdicion ? 'Editar tipo de campo' : 'Nuevo tipo de campo'}
            </DialogTitle>
            <DialogDescription>
              {tipoEnEdicion
                ? 'Modifica los datos del tipo de campo.'
                : 'Registra una nueva categoría de campo deportivo.'}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="nombre">Nombre</Label>
              <Input
                id="nombre"
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                placeholder="Ej: Fútbol"
                required
                autoFocus
              />
              {errores.nombre && (
                <p className="text-sm text-destructive">{errores.nombre[0]}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="descripcion">Descripción (opcional)</Label>
              <Input
                id="descripcion"
                value={descripcion}
                onChange={(e) => setDescripcion(e.target.value)}
                placeholder="Ej: Cancha de fútbol profesional"
              />
              {errores.descripcion && (
                <p className="text-sm text-destructive">{errores.descripcion[0]}</p>
              )}
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
                {guardando ? 'Guardando…' : 'Guardar'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}