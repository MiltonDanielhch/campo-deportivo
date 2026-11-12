import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  Database,
  Globe,
  Loader2,
  Lock,
  Pencil,
  Plus,
  Search,
  ShieldCheck,
  Trash2,
} from 'lucide-react';
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
import { catalogoSirebService } from '@/services/catalogoSirebService';
import type { CatalogoSirebItem } from '@/services/catalogoSirebService';
import {
  camposService,
  type VarianteArancelaria,
  type VariantePayload,
} from '@/services/camposService';
import { calcularVisibilidad } from '@/lib/visibilidad';
import type { CampoDeportivo } from '@/types/parametricas';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  campo: CampoDeportivo | null;
  onGuardado: () => void;
}

/** Presets que fijan los campos derivados pa' que el admin no tipee vocabulario. */
const PRESETS = {
  norma: {
    label: 'Norma pública (alquiler base)',
    descripcion: 'Visible para todos. Precio oficial del campo.',
    tipo_uso: 'alquiler_base',
    unidad_medida: 'hora',
    visible_publico: true,
    esConvenio: false,
  },
  convenio: {
    label: 'Convenio de asociación',
    descripcion: 'Solo para asociación logueada del tipo elegido. Precio de convenio.',
    tipo_uso: 'asociacion',
    unidad_medida: 'hora',
    visible_publico: false,
    esConvenio: true,
  },
  servicio: {
    label: 'Servicio adicional',
    descripcion: 'Limpieza, energía, etc. No ocupa franja horaria.',
    tipo_uso: 'servicio_adicional',
    unidad_medida: 'servicio',
    visible_publico: true,
    esConvenio: false,
  },
} as const;

type PresetKey = keyof typeof PRESETS;

const TIPOS_ASOCIACION = ['departamental', 'municipal'];

function etiquetaVariante(v: VarianteArancelaria): string {
  if (v.visible_publico && v.unidad_medida === 'servicio') return 'Servicio adicional';
  if (!v.visible_publico && v.tipo_asociacion) return `Convenio ${v.tipo_asociacion}`;
  if (v.visible_publico) return 'Norma pública';
  return v.tipo_uso;
}

export default function GestionarVariantesDialog({ open, onOpenChange, campo, onGuardado }: Props) {
  const [variantes, setVariantes] = useState<VarianteArancelaria[]>([]);
  const [cargando, setCargando] = useState(false);
  const [items, setItems] = useState<CatalogoSirebItem[]>([]);
  const [cargandoCatalogo, setCargandoCatalogo] = useState(false);
  const [modo, setModo] = useState<'lista' | 'crear' | 'editar'>('lista');
  const [editando, setEditando] = useState<VarianteArancelaria | null>(null);

  // Form
  const [preset, setPreset] = useState<PresetKey>('norma');
  const [codigo, setCodigo] = useState('');
  const [nombre, setNombre] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [orden, setOrden] = useState('0');
  const [activo, setActivo] = useState(true);
  const [tipoAsociacion, setTipoAsociacion] = useState<string | null>(null);
  const [servicioId, setServicioId] = useState<string | null>(null);
  const [busqueda, setBusqueda] = useState('');
  const [guardando, setGuardando] = useState(false);

  const cargarVariantes = useMemo(
    () => async () => {
      if (!campo) return;
      setCargando(true);
      try {
        setVariantes(await camposService.listarVariantes(campo.id));
      } catch {
        toast.error('No se pudieron cargar las variantes');
      } finally {
        setCargando(false);
      }
    },
    [campo],
  );

  useEffect(() => {
    if (!open || !campo) return;
    setModo('lista');
    setEditando(null);
    cargarVariantes();
    setCargandoCatalogo(true);
    catalogoSirebService
      .listarCatalogo()
      .then((res) => setItems(res.data))
      .catch(() => toast.error('No se pudo obtener el catálogo de SIREB'))
      .finally(() => setCargandoCatalogo(false));
  }, [open, campo, cargarVariantes]);

  const resetForm = (p: PresetKey = 'norma') => {
    setPreset(p);
    setCodigo('');
    setNombre('');
    setDescripcion('');
    setOrden('0');
    setActivo(true);
    setTipoAsociacion(null);
    setServicioId(null);
    setBusqueda('');
  };

  const abrirCrear = () => {
    resetForm('norma');
    setModo('crear');
  };

  const abrirEditar = (v: VarianteArancelaria) => {
    setEditando(v);
    setPreset(
      !v.visible_publico && v.tipo_asociacion
        ? 'convenio'
        : v.unidad_medida === 'servicio'
          ? 'servicio'
          : 'norma',
    );
    setCodigo(v.codigo);
    setNombre(v.nombre);
    setDescripcion(v.descripcion ?? '');
    setOrden(String(v.orden ?? 0));
    setActivo(v.activo);
    setTipoAsociacion(v.tipo_asociacion);
    setServicioId(v.servicio_sireb_id);
    setModo('editar');
  };

  const serviciosDisponibles = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    return items.filter((i) => {
      // Un servicio ya vinculado (base o variante de cualquier campo) está tomado:
      // unique global de servicio_sireb_id en arancel_variantes + coherencia de catálogo.
      if (i.campo_local != null && i.campo_local.id !== campo?.id) return false;
      if (!q) return true;
      return (
        i.sireb.codigo.toLowerCase().includes(q) ||
        i.sireb.nombre.toLowerCase().includes(q)
      );
    });
  }, [items, busqueda, campo]);

  const confirmar = async () => {
    if (!campo) return;
    const p = PRESETS[preset];
    if (!codigo.trim() || !nombre.trim()) {
      toast.error('Código y nombre son obligatorios');
      return;
    }
    if (p.esConvenio && !tipoAsociacion) {
      toast.error('Elegí el tipo de asociación para el convenio');
      return;
    }
    if (!servicioId) {
      toast.error('Elegí el servicio SIREB que corresponde a esta variante');
      return;
    }

    const payload: VariantePayload = {
      codigo: codigo.trim().toUpperCase(),
      nombre: nombre.trim(),
      descripcion: descripcion.trim() || null,
      tipo_uso: p.tipo_uso,
      unidad_medida: p.unidad_medida,
      visible_publico: p.visible_publico,
      tipo_asociacion: p.esConvenio ? tipoAsociacion : null,
      servicio_sireb_id: servicioId,
      activo,
      orden: parseInt(orden, 10) || 0,
    };

    setGuardando(true);
    try {
      const res =
        modo === 'editar' && editando
          ? await camposService.actualizarVariante(campo.id, editando.id, payload)
          : await camposService.crearVariante(campo.id, payload);
      toast.success(res.message ?? 'Variante guardada. Ejecutá Sincronizar tarifas para espejar los precios.');
      setModo('lista');
      setEditando(null);
      await cargarVariantes();
      onGuardado();
    } catch (e: any) {
      if (e.response?.status === 422) {
        const errs = e.response.data?.errors ?? {};
        toast.error(Object.values(errs).flat().join(' '));
      } else {
        toast.error('No se pudo guardar la variante');
      }
    } finally {
      setGuardando(false);
    }
  };

  const desactivar = async (v: VarianteArancelaria) => {
    if (!campo) return;
    setGuardando(true);
    try {
      await camposService.eliminarVariante(campo.id, v.id);
      toast.success('Variante desvinculada y desactivada');
      await cargarVariantes();
      onGuardado();
    } catch {
      toast.error('No se pudo desactivar la variante');
    } finally {
      setGuardando(false);
    }
  };

  const p = PRESETS[preset];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[92vh] flex-col gap-0 overflow-hidden p-0! sm:max-w-3xl">
        <DialogHeader className="border-b px-6 py-4 text-left">
          <DialogTitle className="text-lg">
            Variantes de "{campo?.nombre ?? ''}"
          </DialogTitle>
          <DialogDescription className="text-xs">
            Norma pública (todos reservan) y convenios por tipo de asociación (solo logueado).
            El precio lo espeja SIREB: tras crear, corré "Sincronizar tarifas".
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto px-6 py-4">
          {modo === 'lista' ? (
            <div className="space-y-3">
              {(() => {
                const vis = calcularVisibilidad(campo, variantes);
                const tiposTxt = vis.tiposConvenio.join(' / ');
                return (
                  <div className="rounded-xl border bg-muted/30 p-3 text-xs">
                    <p className="mb-2 font-semibold text-foreground">
                      Resumen de visibilidad
                      <span className="ml-2 font-normal text-muted-foreground">
                        (configuración; el precio real depende de Sincronizar tarifas)
                      </span>
                    </p>
                    <div className="space-y-1.5">
                      <p className="flex items-start gap-2 text-muted-foreground">
                        <Globe className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span>
                          <strong className="text-foreground">Público:</strong>{' '}
                          {vis.publicoVeFranja
                            ? 've el precio oficial de franja.'
                            : 've “Consultar precio” (no hay norma pública activa).'}
                        </span>
                      </p>
                      <p className="flex items-start gap-2 text-muted-foreground">
                        <ShieldCheck className="mt-0.5 size-3.5 shrink-0 text-sky-600 dark:text-sky-400" />
                        <span>
                          <strong className="text-foreground">Asociados:</strong>{' '}
                          {vis.totalConvenios === 0
                            ? 'sin convenios activos.'
                            : vis.publicoVeFranja
                              ? `ven precio oficial + convenio (${tiposTxt}).`
                              : `ven SOLO el convenio (${tiposTxt}).`}
                        </span>
                      </p>
                      {vis.totalAdicionales > 0 && (
                        <p className="flex items-start gap-2 text-muted-foreground">
                          <Database className="mt-0.5 size-3.5 shrink-0 text-violet-600 dark:text-violet-400" />
                          <span>
                            + {vis.totalAdicionales} servicio(s) adicional(es) visible(s)
                            (no ocupan franja horaria).
                          </span>
                        </p>
                      )}
                    </div>
                  </div>
                );
              })()}
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium">
                  {variantes.length} variante{variantes.length === 1 ? '' : 's'}
                </p>
                <Button size="sm" onClick={abrirCrear}>
                  <Plus className="mr-1.5 size-4" /> Nueva variante
                </Button>
              </div>
              {cargando ? (
                <div className="flex h-32 items-center justify-center gap-2 text-muted-foreground">
                  <Loader2 className="size-4 animate-spin" /> Cargando…
                </div>
              ) : variantes.length === 0 ? (
                <p className="py-12 text-center text-sm text-muted-foreground">
                  Este campo no tiene variantes. Creá la norma pública o un convenio.
                </p>
              ) : (
                <ul className="space-y-2">
                  {variantes.map((v) => (
                    <li
                      key={v.id}
                      className="flex items-start justify-between gap-3 rounded-xl border p-3"
                    >
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <p className="truncate font-semibold">{v.nombre}</p>
                          <Badge variant="secondary" className="gap-1 text-[10px]">
                            {!v.visible_publico && v.tipo_asociacion ? (
                              <ShieldCheck className="size-3" />
                            ) : (
                              <Database className="size-3" />
                            )}
                            {etiquetaVariante(v)}
                          </Badge>
                          {!v.activo && (
                            <Badge variant="outline" className="text-[10px] text-muted-foreground">
                              inactiva
                            </Badge>
                          )}
                        </div>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                          {v.codigo} · {v.servicio_sireb_codigo ?? 'sin servicio'}
                        </p>
                        {v.tarifas.length > 0 ? (
                          <p className="mt-1 text-xs text-muted-foreground">
                            {v.tarifas.map((t) => `${t.etiqueta ?? t.clasificacion}: Bs ${t.monto.toFixed(2)}`).join(' · ')}
                          </p>
                        ) : (
                          <p className="mt-1 text-xs text-amber-600">
                            Sin tarifas espejadas — corré Sincronizar tarifas.
                          </p>
                        )}
                      </div>
                      <div className="flex shrink-0 gap-1">
                        <Button variant="ghost" size="icon" onClick={() => abrirEditar(v)} title="Editar">
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => desactivar(v)}
                          disabled={guardando}
                          title="Desactivar"
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                {(Object.keys(PRESETS) as PresetKey[]).map((k) => (
                  <button
                    key={k}
                    type="button"
                    disabled={modo === 'editar'}
                    onClick={() => { setPreset(k); setTipoAsociacion(null); }}
                    className={`rounded-xl border p-3 text-left transition-colors disabled:opacity-60 ${
                      preset === k ? 'border-primary bg-primary/5 ring-2 ring-primary/30' : 'hover:border-primary/40'
                    }`}
                  >
                    <p className="text-sm font-semibold">{PRESETS[k].label}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{PRESETS[k].descripcion}</p>
                  </button>
                ))}
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">Código *</Label>
                  <Input value={codigo} onChange={(e) => setCodigo(e.target.value)} placeholder="BASE / DPTO / MUNI" className="font-mono uppercase" />
                </div>
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">Nombre *</Label>
                  <Input value={nombre} onChange={(e) => setNombre(e.target.value)} placeholder="Alquiler base / Convenio Municipal" />
                </div>
              </div>

              {p.esConvenio && (
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">Tipo de asociación *</Label>
                  <Select value={tipoAsociacion ?? ''} onValueChange={setTipoAsociacion}>
                    <SelectTrigger><SelectValue placeholder="Elegí el tipo" /></SelectTrigger>
                    <SelectContent>
                      {TIPOS_ASOCIACION.map((t) => (
                        <SelectItem key={t} value={t}>{t}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground">
                    Solo una asociación logueada de este tipo verá el precio de convenio.
                  </p>
                </div>
              )}

              <div className="space-y-2">
                <Label className="text-xs font-semibold">Descripción</Label>
                <Input value={descripcion} onChange={(e) => setDescripcion(e.target.value)} placeholder="Opcional" />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label className="text-xs font-semibold">Orden</Label>
                  <Input type="number" value={orden} onChange={(e) => setOrden(e.target.value)} className="font-mono text-sm" />
                </div>
                <div className="flex items-end gap-2 pb-2">
                  <input id="activo" type="checkbox" checked={activo} onChange={(e) => setActivo(e.target.checked)} className="size-4" />
                  <Label htmlFor="activo" className="text-xs font-semibold cursor-pointer">Activa</Label>
                </div>
              </div>

              <div className="space-y-2">
                <Label className="flex items-center gap-1.5 text-xs font-semibold">
                  <Lock className="size-3.5" /> Servicio SIREB *
                </Label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input placeholder="Buscar servicio por código o nombre…" value={busqueda} onChange={(e) => setBusqueda(e.target.value)} className="pl-9" />
                </div>
                {cargandoCatalogo ? (
                  <div className="flex h-24 items-center justify-center gap-2 text-muted-foreground"><Loader2 className="size-4 animate-spin" /> Consultando catálogo…</div>
                ) : serviciosDisponibles.length === 0 ? (
                  <p className="py-6 text-center text-sm text-muted-foreground">Ningún servicio libre coincide. Los ya vinculados a otro campo no aparecen.</p>
                ) : (
                  <ul className="max-h-56 space-y-1.5 overflow-y-auto">
                    {serviciosDisponibles.map((i) => {
                      const sel = servicioId === i.sireb.id;
                      return (
                        <li key={i.sireb.id}>
                          <button
                            type="button"
                            onClick={() => setServicioId(i.sireb.id)}
                            className={`w-full rounded-lg border p-2 text-left text-sm transition-colors ${sel ? 'border-primary bg-primary/5 ring-2 ring-primary/30' : 'hover:border-primary/40'}`}
                          >
                            <span className="font-mono text-xs text-muted-foreground">{i.sireb.codigo}</span>
                            <span className="ml-2 font-medium">{i.sireb.nombre}</span>
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </div>
            </div>
          )}
        </div>

        <DialogFooter className="border-t bg-muted/40 px-6 py-4">
          {modo === 'lista' ? (
            <Button variant="outline" onClick={() => onOpenChange(false)}>Cerrar</Button>
          ) : (
            <>
              <Button variant="outline" onClick={() => { setModo('lista'); setEditando(null); }} disabled={guardando}>Cancelar</Button>
              <Button onClick={confirmar} disabled={guardando || !servicioId}>
                {guardando ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                {modo === 'editar' ? 'Guardar cambios' : 'Crear variante'}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
