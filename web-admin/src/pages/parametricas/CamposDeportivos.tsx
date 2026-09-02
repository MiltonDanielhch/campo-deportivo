import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
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
import { Checkbox } from '@/components/ui/checkbox';
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
import { camposService } from '@/services/camposService';
import { tiposCampoService } from '@/services/tiposCampoService';
import type {
  CampoDeportivo,
  EstadoCampo,
  TipoCampo,
} from '@/types/parametricas';

const DIAS_SEMANA = [
  'Lunes',
  'Martes',
  'Miércoles',
  'Jueves',
  'Viernes',
  'Sábado',
  'Domingo',
];

const TODOS = 'todos';

/** Fila editable de la grilla de horarios del formulario */
interface FilaHorario {
  dia: number; // 1-7
  habilitado: boolean;
  apertura: string;
  cierre: string;
}

const horariosIniciales = (): FilaHorario[] =>
  DIAS_SEMANA.map((_, i) => ({
    dia: i + 1,
    habilitado: true,
    apertura: '08:00',
    cierre: '20:00',
  }));

const ESTADO_VARIANT: Record<EstadoCampo, 'default' | 'secondary' | 'destructive'> = {
  activo: 'default',
  mantenimiento: 'secondary',
  inactivo: 'destructive',
};

const ESTADO_DESCRIPCION: Record<EstadoCampo, string> = {
  activo: 'El campo puede recibir reservas normalmente.',
  mantenimiento: 'El campo queda bloqueado para reservas futuras hasta reactivarlo.',
  inactivo: 'El campo queda fuera de operación de forma indefinida.',
};

export default function CamposDeportivos() {
  const navigate = useNavigate();

  // ─── Estado de la lista ────────────────────────────────────────────────
  const [campos, setCampos] = useState<CampoDeportivo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>(TODOS);
  const [pagina, setPagina] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);

  // ─── Dialog de alta ────────────────────────────────────────────────────
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [tiposActivos, setTiposActivos] = useState<TipoCampo[]>([]);
  const [tipoCampoId, setTipoCampoId] = useState('');
  const [codigo, setCodigo] = useState('');
  const [nombre, setNombre] = useState('');
  const [direccion, setDireccion] = useState('');
  const [latitud, setLatitud] = useState('-14.8432');
  const [longitud, setLongitud] = useState('-64.9012');
  const [horarios, setHorarios] = useState<FilaHorario[]>(horariosIniciales());
  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

  // ─── AlertDialog de cambio de estado ───────────────────────────────────
  const [campoEstado, setCampoEstado] = useState<CampoDeportivo | null>(null);
  const [nuevoEstado, setNuevoEstado] = useState<EstadoCampo>('activo');
  const [cambiandoEstado, setCambiandoEstado] = useState(false);

  // ─── Carga de datos ────────────────────────────────────────────────────
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

  // ─── Dialog de alta ────────────────────────────────────────────────────
  const abrirNuevo = async () => {
    setErrores({});
    setTipoCampoId('');
    setCodigo('');
    setNombre('');
    setDireccion('');
    setLatitud('-14.8432');
    setLongitud('-64.9012');
    setHorarios(horariosIniciales());
    try {
      setTiposActivos(await tiposCampoService.listarActivos());
    } catch {
      toast.error('No se pudieron cargar los tipos de campo');
    }
    setDialogAbierto(true);
  };

  const actualizarHorario = (dia: number, cambios: Partial<FilaHorario>) => {
    setHorarios((prev) =>
      prev.map((h) => (h.dia === dia ? { ...h, ...cambios } : h)),
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrores({});

    // Validación cliente de la grilla de horarios
    const habilitados = horarios.filter((h) => h.habilitado);
    if (habilitados.length === 0) {
      toast.error('Habilita al menos un día de atención');
      return;
    }
    const horarioInvalido = habilitados.find((h) => h.cierre <= h.apertura);
    if (horarioInvalido) {
      toast.error(
        `Horario inválido el ${DIAS_SEMANA[horarioInvalido.dia - 1]}: el cierre debe ser posterior a la apertura`,
      );
      return;
    }

    setGuardando(true);
    try {
      await camposService.crear({
        tipo_campo_id: tipoCampoId,
        codigo: codigo.trim().toUpperCase(),
        nombre: nombre.trim(),
        direccion: direccion.trim(),
        latitud: parseFloat(latitud),
        longitud: parseFloat(longitud),
        horarios: habilitados.map((h) => ({
          dia_semana: h.dia,
          hora_apertura: h.apertura,
          hora_cierre: h.cierre,
        })),
      });
      toast.success('Campo deportivo creado con sus horarios de atención');
      setDialogAbierto(false);
      cargar();
    } catch (error: any) {
      if (error.response?.status === 422) {
        const errs = error.response.data.errors ?? {};
        setErrores(errs);
        // Errores de horarios van a toast (son de la grilla completa)
        if (errs.horarios) toast.error(errs.horarios[0]);
      } else {
        toast.error('Ocurrió un error al guardar el campo');
      }
    } finally {
      setGuardando(false);
    }
  };

  // ─── Cambio de estado con confirmación ─────────────────────────────────
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

  // ─── Render ────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6 p-6">
      {/* Encabezado */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Campos Deportivos</h1>
          <p className="text-sm text-muted-foreground">
            Alta de campos con sus horarios de atención y control de estado
          </p>
        </div>
        <Button onClick={abrirNuevo}>Nuevo campo deportivo</Button>
      </div>

      {/* Filtro por estado */}
      <div className="flex items-center gap-2">
        <Label className="text-sm">Estado:</Label>
        <Select value={filtroEstado} onValueChange={cambiarFiltro}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Filtrar por estado" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TODOS}>Todos</SelectItem>
            <SelectItem value="activo">Activos</SelectItem>
            <SelectItem value="mantenimiento">En mantenimiento</SelectItem>
            <SelectItem value="inactivo">Inactivos</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Tabla */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Código</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Dirección</TableHead>
              <TableHead className="w-32">Estado</TableHead>
              <TableHead className="w-52 text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={6} className="h-24 text-center">
                  Cargando…
                </TableCell>
              </TableRow>
            ) : campos.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="h-24 text-center">
                  No hay campos deportivos registrados
                </TableCell>
              </TableRow>
            ) : (
              campos.map((campo) => (
                <TableRow key={campo.id}>
                  <TableCell className="font-mono text-sm">{campo.codigo}</TableCell>
                  <TableCell className="font-medium">{campo.nombre}</TableCell>
                  <TableCell>{campo.tipo_campo?.nombre ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {campo.direccion}
                  </TableCell>
                  <TableCell>
                    <Badge variant={ESTADO_VARIANT[campo.estado]}>
                      {campo.estado}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                          navigate(`/panel/parametricas/campos/${campo.id}/tarifas`)
                        }
                      >
                        Tarifas
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => abrirCambioEstado(campo)}
                      >
                        Cambiar estado
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

      {/* Dialog de alta con grilla de horarios */}
      <Dialog open={dialogAbierto} onOpenChange={setDialogAbierto}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Nuevo campo deportivo</DialogTitle>
            <DialogDescription>
              El campo y sus horarios se crean en una única transacción: si algo
              falla, nada queda guardado.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Datos generales */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Tipo de campo *</Label>
                <Select value={tipoCampoId} onValueChange={setTipoCampoId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecciona un tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    {tiposActivos.map((tipo) => (
                      <SelectItem key={tipo.id} value={tipo.id}>
                        {tipo.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errores.tipo_campo_id && (
                  <p className="text-sm text-destructive">{errores.tipo_campo_id[0]}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="codigo">Código *</Label>
                <Input
                  id="codigo"
                  value={codigo}
                  onChange={(e) => setCodigo(e.target.value)}
                  placeholder="CD-001"
                  required
                />
                {errores.codigo && (
                  <p className="text-sm text-destructive">{errores.codigo[0]}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="nombre">Nombre *</Label>
                <Input
                  id="nombre"
                  value={nombre}
                  onChange={(e) => setNombre(e.target.value)}
                  placeholder="Cancha Central"
                  required
                />
                {errores.nombre && (
                  <p className="text-sm text-destructive">{errores.nombre[0]}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="direccion">Dirección *</Label>
                <Input
                  id="direccion"
                  value={direccion}
                  onChange={(e) => setDireccion(e.target.value)}
                  placeholder="Av. Principal #123, Trinidad"
                  required
                />
                {errores.direccion && (
                  <p className="text-sm text-destructive">{errores.direccion[0]}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="latitud">Latitud * (Beni: -18.0 a -9.6)</Label>
                <Input
                  id="latitud"
                  type="number"
                  step="0.00000001"
                  value={latitud}
                  onChange={(e) => setLatitud(e.target.value)}
                  required
                />
                {errores.latitud && (
                  <p className="text-sm text-destructive">{errores.latitud[0]}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="longitud">Longitud * (Beni: -67.5 a -57.4)</Label>
                <Input
                  id="longitud"
                  type="number"
                  step="0.00000001"
                  value={longitud}
                  onChange={(e) => setLongitud(e.target.value)}
                  required
                />
                {errores.longitud && (
                  <p className="text-sm text-destructive">{errores.longitud[0]}</p>
                )}
              </div>
            </div>

            {/* Grilla de horarios */}
            <div className="space-y-2">
              <Label>Horarios de atención (desmarca los días sin servicio)</Label>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-12">Habilitado</TableHead>
                      <TableHead>Día</TableHead>
                      <TableHead>Apertura</TableHead>
                      <TableHead>Cierre</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {horarios.map((h) => (
                      <TableRow key={h.dia}>
                        <TableCell>
                          <Checkbox
                            checked={h.habilitado}
                            onCheckedChange={(v) =>
                              actualizarHorario(h.dia, { habilitado: v === true })
                            }
                          />
                        </TableCell>
                        <TableCell className="font-medium">
                          {DIAS_SEMANA[h.dia - 1]}
                        </TableCell>
                        <TableCell>
                          <Input
                            type="time"
                            value={h.apertura}
                            disabled={!h.habilitado}
                            onChange={(e) =>
                              actualizarHorario(h.dia, { apertura: e.target.value })
                            }
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="time"
                            value={h.cierre}
                            disabled={!h.habilitado}
                            onChange={(e) =>
                              actualizarHorario(h.dia, { cierre: e.target.value })
                            }
                          />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
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
                {guardando ? 'Guardando…' : 'Crear campo con horarios'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* AlertDialog de cambio de estado */}
      <AlertDialog open={campoEstado !== null} onOpenChange={() => setCampoEstado(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              Cambiar estado de "{campoEstado?.nombre}"
            </AlertDialogTitle>
            <AlertDialogDescription>
              Estado actual: <strong>{campoEstado?.estado}</strong>.{' '}
              {ESTADO_DESCRIPCION[nuevoEstado]}
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
                <SelectItem value="activo">activo</SelectItem>
                <SelectItem value="mantenimiento">mantenimiento</SelectItem>
                <SelectItem value="inactivo">inactivo</SelectItem>
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