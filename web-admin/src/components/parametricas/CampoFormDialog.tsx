import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
  Camera,
  Clock,
  Copy,
  ExternalLink,
  FileText,
  ImageIcon,
  MapPin,
  Trash2,
  Upload,
  X,
} from 'lucide-react';
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
import type { CampoDeportivo, TipoCampo } from '@/types/parametricas';

const DIAS_SEMANA = [
  'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo',
];

const LAT_TRINIDAD = '-14.8432';
const LNG_TRINIDAD = '-64.9012';

interface FilaHorario {
  dia: number;
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

interface CampoFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  modo: 'crear' | 'editar';
  campo?: CampoDeportivo | null;
  onGuardado: () => void;
}

/** Encabezado de sección con icono */
function Seccion({
  icon: Icono,
  titulo,
  descripcion,
  children,
  extra,
}: {
  icon: typeof Camera;
  titulo: string;
  descripcion?: string;
  children: React.ReactNode;
  extra?: React.ReactNode;
}) {
  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Icono className="size-4" />
          </div>
          <div>
            <h3 className="text-sm font-semibold leading-tight">{titulo}</h3>
            {descripcion && (
              <p className="text-xs text-muted-foreground">{descripcion}</p>
            )}
          </div>
        </div>
        {extra}
      </div>
      {children}
    </section>
  );
}

export default function CampoFormDialog({
  open,
  onOpenChange,
  modo,
  campo,
  onGuardado,
}: CampoFormDialogProps) {
  // ─── Datos generales ───
  const [tiposActivos, setTiposActivos] = useState<TipoCampo[]>([]);
  const [tipoCampoId, setTipoCampoId] = useState('');
  const [codigo, setCodigo] = useState('');
  const [nombre, setNombre] = useState('');
  const [direccion, setDireccion] = useState('');
  const [latitud, setLatitud] = useState(LAT_TRINIDAD);
  const [longitud, setLongitud] = useState(LNG_TRINIDAD);
  const [horarios, setHorarios] = useState<FilaHorario[]>(horariosIniciales());

  // ─── Imagen ───
  const [imagenFile, setImagenFile] = useState<File | null>(null);
  const [quitarImagen, setQuitarImagen] = useState(false);
  const [imagenPersistida, setImagenPersistida] = useState<string | null>(null);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [guardando, setGuardando] = useState(false);
  const [errores, setErrores] = useState<Record<string, string[]>>({});

  // ─── Carga inicial al abrir ───
  useEffect(() => {
    if (!open) return;

    setErrores({});
    setImagenFile(null);
    setQuitarImagen(false);
    setDragOver(false);

    if (modo === 'editar' && campo) {
      setTipoCampoId(campo.tipo_campo_id);
      setCodigo(campo.codigo);
      setNombre(campo.nombre);
      setDireccion(campo.direccion);
      setLatitud(String(campo.latitud));
      setLongitud(String(campo.longitud));
      setImagenPersistida(campo.imagen_url ?? null);

      if (campo.horarios_atencion?.length) {
        setHorarios(
          horariosIniciales().map((fila) => {
            const existente = campo.horarios_atencion!.find(
              (h) => h.dia_semana === fila.dia,
            );
            return existente
              ? {
                  dia: fila.dia,
                  habilitado: true,
                  apertura: existente.hora_apertura,
                  cierre: existente.hora_cierre,
                }
              : { ...fila, habilitado: false };
          }),
        );
      } else {
        setHorarios(horariosIniciales());
      }
    } else {
      setTipoCampoId('');
      setCodigo('');
      setNombre('');
      setDireccion('');
      setLatitud(LAT_TRINIDAD);
      setLongitud(LNG_TRINIDAD);
      setImagenPersistida(null);
      setHorarios(horariosIniciales());
    }

    tiposCampoService
      .listarActivos()
      .then(setTiposActivos)
      .catch(() => toast.error('No se pudieron cargar los tipos de campo'));
  }, [open, modo, campo]);

  // ─── Imagen: helpers ───
  const previewUrl = imagenFile
    ? URL.createObjectURL(imagenFile)
    : !quitarImagen && imagenPersistida
      ? imagenPersistida
      : null;

  useEffect(() => {
    return () => {
      if (imagenFile) URL.revokeObjectURL(previewUrl);
    };
  }, [imagenFile, previewUrl]);

  const aceptarArchivo = (file: File | undefined | null) => {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      toast.error('Formato no soportado: usá JPG, PNG o WebP');
      return;
    }
    if (file.size > 4 * 1024 * 1024) {
      toast.error('La imagen supera los 4 MB');
      return;
    }
    setImagenFile(file);
    setQuitarImagen(false);
  };

  // ─── Horarios: acciones rápidas ───
  const diasHabilitados = horarios.filter((h) => h.habilitado).length;

  const igualarAlLunes = () => {
    const lunes = horarios.find((h) => h.dia === 1);
    if (!lunes) return;
    setHorarios((prev) =>
      prev.map((h) =>
        h.habilitado
          ? { ...h, apertura: lunes.apertura, cierre: lunes.cierre }
          : h,
      ),
    );
    toast.success('Horarios igualados al lunes');
  };

  const setTodosHabilitados = (valor: boolean) => {
    setHorarios((prev) => prev.map((h) => ({ ...h, habilitado: valor })));
  };

  const actualizarHorario = (dia: number, cambios: Partial<FilaHorario>) => {
    setHorarios((prev) =>
      prev.map((h) => (h.dia === dia ? { ...h, ...cambios } : h)),
    );
  };

  // ─── Submit ───
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrores({});

    const habilitados = horarios.filter((h) => h.habilitado);
    if (habilitados.length === 0) {
      toast.error('Habilitá al menos un día de atención');
      return;
    }
    const invalido = habilitados.find((h) => h.cierre <= h.apertura);
    if (invalido) {
      toast.error(
        `Horario inválido el ${DIAS_SEMANA[invalido.dia - 1]}: el cierre debe ser posterior a la apertura`,
      );
      return;
    }

    setGuardando(true);
    try {
      const payload = {
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
        imagen: imagenFile ?? undefined,
        quitar_imagen: quitarImagen && !imagenFile ? true : undefined,
      };

      if (modo === 'editar' && campo) {
        await camposService.actualizar(campo.id, payload);
        toast.success('Campo deportivo actualizado');
      } else {
        await camposService.crear(payload);
        toast.success('Campo deportivo creado con sus horarios de atención');
      }

      onOpenChange(false);
      onGuardado();
    } catch (error: any) {
      if (error.response?.status === 422) {
        const errs = error.response.data.errors ?? {};
        setErrores(errs);
        if (errs.horarios) toast.error(errs.horarios[0]);
      } else {
        toast.error('Ocurrió un error al guardar el campo');
      }
    } finally {
      setGuardando(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[92vh] flex-col gap-0 overflow-hidden p-0! sm:max-w-3xl">
        <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
          {/* ─── Header sticky ─── */}
          <DialogHeader className="border-b px-6 py-4 text-left">
            <DialogTitle className="text-lg">
              {modo === 'editar' ? `Editar: ${campo?.nombre ?? ''}` : 'Nuevo campo deportivo'}
            </DialogTitle>
            <DialogDescription className="text-xs">
              {modo === 'editar'
                ? 'Modificá datos, foto, ubicación y horarios. Los cambios se aplican al guardar.'
                : 'El campo y sus horarios se crean en una única transacción.'}
            </DialogDescription>
          </DialogHeader>

          {/* ─── Cuerpo scrolleable ─── */}
          <div className="flex-1 space-y-8 overflow-y-auto px-6 py-6">
            {/* 📷 FOTO */}
            <Seccion
              icon={Camera}
              titulo="Foto del campo"
              descripcion="Opcional · JPG, PNG o WebP · máx. 4 MB"
              extra={
                previewUrl && (
                  <Button
                    type="button"
                    variant={quitarImagen ? 'default' : 'ghost'}
                    size="sm"
                    className={quitarImagen ? '' : 'text-destructive hover:text-destructive'}
                    onClick={() => {
                      if (quitarImagen) {
                        setQuitarImagen(false);
                      } else {
                        setImagenFile(null);
                        setQuitarImagen(true);
                        if (fileInputRef.current) fileInputRef.current.value = '';
                      }
                    }}
                  >
                    <Trash2 className="mr-1.5 size-3.5" />
                    {quitarImagen ? 'Cancelar eliminación' : 'Quitar foto'}
                  </Button>
                )
              }
            >
              <div
                role="button"
                tabIndex={0}
                onClick={() => fileInputRef.current?.click()}
                onKeyDown={(e) => e.key === 'Enter' && fileInputRef.current?.click()}
                onDragOver={(e) => {
                  e.preventDefault();
                  setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={(e) => {
                  e.preventDefault();
                  setDragOver(false);
                  aceptarArchivo(e.dataTransfer.files?.[0]);
                }}
                className={`relative flex cursor-pointer items-center gap-5 rounded-xl border-2 border-dashed p-5 transition-colors ${
                  dragOver
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/50 hover:bg-muted/30'
                } ${quitarImagen ? 'opacity-60' : ''}`}
              >
                {/* Preview */}
                <div className="relative h-28 w-40 flex-shrink-0 overflow-hidden rounded-lg border bg-muted">
                  {previewUrl ? (
                    <img
                      src={previewUrl}
                      alt="Vista previa"
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-full w-full flex-col items-center justify-center text-muted-foreground">
                      <ImageIcon className="mb-1 size-6" />
                      <span className="text-xs">Sin foto</span>
                    </div>
                  )}
                  {quitarImagen && (
                    <div className="absolute inset-0 flex items-center justify-center bg-red-500/80 text-xs font-semibold text-white">
                      Se eliminará al guardar
                    </div>
                  )}
                </div>

                {/* Texto del dropzone */}
                <div className="text-center sm:text-left">
                  <div className="mx-auto mb-2 flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary sm:mx-0">
                    <Upload className="size-4" />
                  </div>
                  <p className="text-sm font-medium">
                    Arrastrá una imagen o hacé click
                  </p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    Se muestra en la web pública y la app móvil
                  </p>
                </div>

                {imagenFile && (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      setImagenFile(null);
                      if (fileInputRef.current) fileInputRef.current.value = '';
                    }}
                    className="absolute right-3 top-3 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Descartar selección"
                  >
                    <X className="size-4" />
                  </button>
                )}
              </div>

              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => aceptarArchivo(e.target.files?.[0])}
                className="hidden"
              />

              {errores.imagen && (
                <p className="text-sm text-destructive">{errores.imagen[0]}</p>
              )}
            </Seccion>

            {/* 📋 DATOS */}
            <Seccion
              icon={FileText}
              titulo="Datos generales"
              descripcion="Identificación del campo en el sistema"
            >
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">
                    Tipo de campo <span className="text-destructive">*</span>
                  </Label>
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
                    <p className="text-xs text-destructive">{errores.tipo_campo_id[0]}</p>
                  )}
                </div>
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">
                    Código <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    placeholder="CD-001"
                    className="font-mono uppercase"
                    required
                  />
                  {errores.codigo && (
                    <p className="text-xs text-destructive">{errores.codigo[0]}</p>
                  )}
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label className="text-xs font-semibold">
                    Nombre <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={nombre}
                    onChange={(e) => setNombre(e.target.value)}
                    placeholder="Cancha Central"
                    required
                  />
                  {errores.nombre && (
                    <p className="text-xs text-destructive">{errores.nombre[0]}</p>
                  )}
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label className="text-xs font-semibold">
                    Dirección <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={direccion}
                    onChange={(e) => setDireccion(e.target.value)}
                    placeholder="Av. Principal #123, Trinidad"
                    required
                  />
                  {errores.direccion && (
                    <p className="text-xs text-destructive">{errores.direccion[0]}</p>
                  )}
                </div>
              </div>
            </Seccion>

            {/* 📍 UBICACIÓN */}
            <Seccion
              icon={MapPin}
              titulo="Ubicación (coordenadas)"
              descripcion="Se usa para el mapa público y la app móvil"
              extra={
                <div className="flex gap-1.5">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setLatitud(LAT_TRINIDAD);
                      setLongitud(LNG_TRINIDAD);
                    }}
                  >
                    Centrar en Trinidad
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    asChild
                  >
                    <a
                      href={`https://www.google.com/maps?q=${latitud},${longitud}`}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <ExternalLink className="mr-1.5 size-3.5" />
                      Ver en mapa
                    </a>
                  </Button>
                </div>
              }
            >
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">
                    Latitud <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    type="number"
                    step="0.00000001"
                    value={latitud}
                    onChange={(e) => setLatitud(e.target.value)}
                    className="font-mono text-sm"
                    required
                  />
                  {errores.latitud && (
                    <p className="text-xs text-destructive">{errores.latitud[0]}</p>
                  )}
                </div>
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">
                    Longitud <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    type="number"
                    step="0.00000001"
                    value={longitud}
                    onChange={(e) => setLongitud(e.target.value)}
                    className="font-mono text-sm"
                    required
                  />
                  {errores.longitud && (
                    <p className="text-xs text-destructive">{errores.longitud[0]}</p>
                  )}
                </div>
              </div>
              <p className="text-xs text-muted-foreground">
                Rango válido Beni: latitud -18.0 a -9.6 · longitud -67.5 a -57.4
              </p>
            </Seccion>

            {/* 🕐 HORARIOS */}
            <Seccion
              icon={Clock}
              titulo="Horarios de atención"
              descripcion="Desmarcá los días sin servicio"
              extra={
                <Badge variant="secondary" className="gap-1.5">
                  <Clock className="size-3" />
                  {diasHabilitados} de 7 días
                </Badge>
              }
            >
              {/* Acciones rápidas */}
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={igualarAlLunes}
                >
                  <Copy className="mr-1.5 size-3.5" />
                  Igualar todos al lunes
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setTodosHabilitados(true)}
                >
                  Marcar todos
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => setTodosHabilitados(false)}
                >
                  Desmarcar todos
                </Button>
              </div>

              <div className="overflow-hidden rounded-xl border">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                      <TableHead className="w-16">Activo</TableHead>
                      <TableHead>Día</TableHead>
                      <TableHead>Apertura</TableHead>
                      <TableHead>Cierre</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {horarios.map((h) => (
                      <TableRow
                        key={h.dia}
                        className={h.habilitado ? '' : 'opacity-50'}
                      >
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
            </Seccion>
          </div>

          {/* ─── Footer sticky ─── */}
          <DialogFooter className="border-t bg-muted/40 px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={guardando}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={guardando} className="rounded-full px-6">
              {guardando
                ? 'Guardando…'
                : modo === 'editar'
                  ? 'Guardar cambios'
                  : 'Crear campo'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
