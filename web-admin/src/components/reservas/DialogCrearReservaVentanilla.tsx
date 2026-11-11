import { useEffect, useState } from 'react';
import {
  Check,
  ChevronDown,
  Loader2,
  MapPin,
  Plus,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
} from '@/components/ui/select';
import { useVentanillaAsociacion } from './ventanilla/useVentanillaAsociacion';
import { useVentanillaCampos } from './ventanilla/useVentanillaCampos';
import { useVentanillaVariantes } from './ventanilla/useVentanillaVariantes';
import { useVentanillaServicios } from './ventanilla/useVentanillaServicios';
import { useVentanillaSlots } from './ventanilla/useVentanillaSlots';
import { useVentanillaPricing } from './ventanilla/useVentanillaPricing';
import { useVentanillaEnvioResultado } from './ventanilla/useVentanillaEnvioResultado';
import { VentanillaResultado } from './ventanilla/VentanillaResultado';
import { hoyBoliviaISO } from './ventanilla/utils';
import { VentanillaFranjasSelector } from './ventanilla/VentanillaFranjasSelector';
import { VentanillaServiciosSelector } from './ventanilla/VentanillaServiciosSelector';
import { VentanillaAlumnosSelector } from './ventanilla/VentanillaAlumnosSelector';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreada: () => void;
}

const MODAL_MAX_WIDTH = '52rem';

export default function DialogCrearReservaVentanilla({
  open,
  onOpenChange,
  onCreada,
}: Props) {
  // ── Hooks de carga (PR #61a) ──
  const {
    campoId,
    campoNombre,
    busquedaCampo,
    setBusquedaCampo,
    campoAbierto,
    setCampoAbierto,
    campoWrapRef,
    campoInputRef,
    camposFiltrados,
    handleCampoChange,
    reset: resetCampos,
  } = useVentanillaCampos(open);

  const {
    cargandoVariantes,
    varianteId,
    varianteNombre,
    variantesHorarias,
    tieneVariantes,
    requiereVariante,
    bloqueadoPorVariantesNoHorarias,
    varianteSeleccionada,
    handleVarianteChange,
    setVarianteConvenio,
    reset: resetVariantes,
  } = useVentanillaVariantes(campoId, open);

  const {
    serviciosAdicionales,
    cargandoServicios,
    serviciosSeleccionados,
    serviciosAdicionalesValidos,
    toggleServicio,
    setCantidadServicio,
    reset: resetServicios,
  } = useVentanillaServicios(campoId, open);

  const {
    fecha,
    setFecha,
    bloques,
    cargandoSlots,
    slots,
    toggleSlot,
    horaAmedida,
    setHoraAmedida,
    toggleAmedida,
    slotsAmedida,
    horaInicioNocheGrilla,
    limpiarSlots,
    reset: resetSlots,
  } = useVentanillaSlots(open, campoId);

  // ── Estado del pagador ──
  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');

  const {
    asociacionDetectada,
    asociacionCargando,
    alumnos,
    cargandoAlumnos,
    reset: resetAsociacion,
  } = useVentanillaAsociacion(open, ciNit);

  const [alumnosPorFranja, setAlumnosPorFranja] = useState<Record<string, string[]>>({});

  const {
    enviando,
    resultado,
    copiado,
    puedeEnviar,
    motivoDeshabilitado,
    enviar,
    copiar,
    descargarQr,
    enviarWhatsapp,
    resetResultado,
  } = useVentanillaEnvioResultado({
    campoId,
    varianteId,
    fecha,
    slots,
    serviciosSeleccionados,
    alumnosPorFranja,
    nombre,
    ciNit,
    telefono,
    requiereVariante,
    bloqueadoPorVariantesNoHorarias,
    onCreada,
  });

  const {
    clasificacionAplicable,
    totalServicios,
    subtotalFranja,
    totalEstimado,
    resolverTarifaServicio,
  } = useVentanillaPricing({
    slots,
    bloques,
    horaInicioNocheGrilla,
    varianteSeleccionada,
    serviciosSeleccionados,
    serviciosAdicionalesValidos,
  });

  // Al cambiar de campo, los hooks de variantes/servicios se resetean solos por
  // su efecto [open, campoId]. Acá solo limpiamos franjas/hora a medida, que
  // viven en este componente.
  const handleCampoChangeCompuesto = (nuevoId: string) => {
    handleCampoChange(nuevoId);
    limpiarSlots();
  };

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      resetCampos();
      resetVariantes();
      resetServicios();
      resetSlots();
      setNombre('');
      setCiNit('');
      setTelefono('');
      resetAsociacion();
      setAlumnosPorFranja({});
      resetResultado();
    }, 200);
  };


  // P2b: si hay asociación vigente con tipo, preseleccionar la variante de convenio
  // que matchee. Declarado DESPUES del hook de variantes para que gane sobre la
  // preselección "única horaria". Sugerencia, no bloqueo: el backend (guardia
  // 5.6-5.8) sigue siendo la última línea.
  useEffect(() => {
    if (!asociacionDetectada?.vigente || !asociacionDetectada.tipo) return;
    const match = variantesHorarias.find(
      (v) => v.tipo_asociacion === asociacionDetectada.tipo,
    );
    if (match) {
      setVarianteConvenio(match.id, match.nombre);
    }
  }, [asociacionDetectada, variantesHorarias, setVarianteConvenio]);

  useEffect(() => {
    if (!campoAbierto) return;

    campoInputRef.current?.focus();

    const onPointerDown = (e: MouseEvent) => {
      if (campoWrapRef.current && !campoWrapRef.current.contains(e.target as Node)) {
        setCampoAbierto(false);
      }
    };
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setCampoAbierto(false);
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [campoAbierto, campoInputRef, campoWrapRef, setCampoAbierto]);

  // [Fase 3] toggle alumno<->franja, keyed por slot.inicio (identificador
  // estable del slot, igual que toggleSlot/toggleAmedida).
  const toggleAlumnoFranja = (inicio: string, alumnoId: string) =>
    setAlumnosPorFranja((prev) => {
      const actual = prev[inicio] ?? [];
      const next = actual.includes(alumnoId)
        ? actual.filter((id) => id !== alumnoId)
        : [...actual, alumnoId];
      return { ...prev, [inicio]: next };
    });

  return (
    <Dialog open={open} onOpenChange={(v) => (v ? onOpenChange(v) : cerrar())}>
      <DialogContent
        className="max-h-[92vh] w-full overflow-y-auto"
        style={{ maxWidth: MODAL_MAX_WIDTH }}
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plus className="size-5 text-teal-600" />
            Nueva reserva de ventanilla
          </DialogTitle>
          <DialogDescription>
            Generá la reserva y el código de pago SIREB para el ciudadano. El precio sale del espejo de SIREB, no se fija acá.
          </DialogDescription>
        </DialogHeader>

        {resultado ? (
          <VentanillaResultado
            resultado={resultado}
            copiado={copiado}
            onCopiar={copiar}
            onDescargarQr={descargarQr}
            onEnviarWhatsapp={enviarWhatsapp}
            onCerrar={cerrar}
          />
        ) : (
          <div className="space-y-5 py-1">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="relative min-w-0 space-y-2" ref={campoWrapRef}>
                <Label>Campo *</Label>
                <Button
                  type="button"
                  variant="outline"
                  role="combobox"
                  aria-expanded={campoAbierto}
                  aria-label="Campo deportivo"
                  className="w-full justify-between font-normal"
                  onClick={() => setCampoAbierto((v) => !v)}
                >
                  <span className="flex min-w-0 items-center gap-2 text-left">
                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                    <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
                      {campoNombre || 'Elegí el campo'}
                    </span>
                  </span>
                  <ChevronDown className="size-4 shrink-0 opacity-50" />
                </Button>

                {campoAbierto && (
                  <div className="absolute left-0 right-0 top-full z-50 mt-1 rounded-md border bg-background p-1 shadow-md">
                    <Input
                      ref={campoInputRef}
                      value={busquedaCampo}
                      onChange={(e) => setBusquedaCampo(e.target.value)}
                      placeholder="Buscar campo…"
                      className="mb-1 h-8 text-sm"
                      aria-label="Buscar campo"
                    />
                    <div className="max-h-56 overflow-y-auto">
                      {camposFiltrados.length === 0 ? (
                        <p className="px-2 py-1.5 text-sm text-muted-foreground">
                          Sin campos que coincidan.
                        </p>
                      ) : (
                        camposFiltrados.map((c) => {
                          const activo = c.id === campoId;
                          return (
                            <button
                              key={c.id}
                              type="button"
                              onClick={() => handleCampoChangeCompuesto(c.id)}
                              className={`flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted ${
                                activo ? 'bg-muted font-medium' : ''
                              }`}
                            >
                              <span className="block truncate" title={c.nombre || c.id}>
                                {c.nombre || c.id}
                              </span>
                              {activo && <Check className="size-4 shrink-0" />}
                            </button>
                          );
                        })
                      )}
                    </div>
                  </div>
                )}
              </div>

              <div className="min-w-0 space-y-2">
                <Label>Fecha *</Label>
                <Input
                  type="date"
                  value={fecha}
                  min={hoyBoliviaISO()}
                  onChange={(e) => setFecha(e.target.value)}
                />
              </div>
            </div>

            {(cargandoVariantes || tieneVariantes) && (
              <div className="space-y-2">
                <Label className="flex items-center gap-2">
                  Variante arancelaria
                  {requiereVariante && <span className="text-destructive">*</span>}
                </Label>

                {cargandoVariantes ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="size-4 animate-spin" />
                    Cargando variantes del campo…
                  </div>
                ) : bloqueadoPorVariantesNoHorarias ? (
                  <p className="rounded-lg border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                    Este campo tiene variantes arancelarias, pero ninguna es por hora.
                    Esta ventanilla por ahora solo reserva franjas horarias.
                  </p>

                ) : (
                  <>
                    <Select
                      value={varianteId}
                      onValueChange={handleVarianteChange}
                      disabled={!requiereVariante}
                    >
                      <SelectTrigger className="w-full" aria-label="Variante arancelaria">
                        <span className="flex min-w-0 items-center gap-2 text-left">
                          <span className={`truncate ${varianteNombre ? '' : 'text-muted-foreground'}`}>
                            {varianteNombre || 'Elegí la variante arancelaria'}
                          </span>
                        </span>
                      </SelectTrigger>
                      <SelectContent>
                        {variantesHorarias.length === 0 ? (
                          <div className="px-2 py-1.5 text-sm text-muted-foreground">
                            Sin variantes horarias disponibles
                          </div>
                        ) : (
                          variantesHorarias.map((v) => {
                            const full = `${v.nombre}${v.servicio_sireb_codigo ? ` · ${v.servicio_sireb_codigo}` : ''}`;
                            return (
                              <SelectItem key={v.id} value={v.id}>
                                <span className="block truncate" title={full}>
                                  {full}
                                </span>
                              </SelectItem>
                            );
                          })
                        )}
                      </SelectContent>
                    </Select>

                    {asociacionDetectada?.vigente && varianteSeleccionada?.tipo_asociacion && (
                      <p className="flex items-center gap-1.5 pt-1 text-xs text-emerald-700 dark:text-emerald-400">
                        <Check className="size-3.5 shrink-0" />
                        Convenio {varianteSeleccionada.tipo_asociacion} · {asociacionDetectada.nombre}
                      </p>
                    )}

                    {varianteSeleccionada?.tarifas?.length ? (
                      <div className="flex flex-wrap gap-2 pt-1">
                        {varianteSeleccionada.tarifas.map((t) => {
                          const aplicable =
                            slots.length > 0 &&
                            (t.clasificacion === 'unica' ||
                              t.clasificacion === clasificacionAplicable);
                          return (
                            <Badge
                              key={t.tarifa_sireb_id}
                              variant={aplicable ? 'default' : 'secondary'}
                              className="gap-1.5"
                            >
                              <span className="text-xs">
                                {t.etiqueta ?? t.clasificacion ?? 'Tarifa'}
                              </span>
                              <span className="text-xs font-semibold tabular-nums">
                                Bs {Number(t.monto).toFixed(2)}
                              </span>
                            </Badge>
                          );
                        })}
                      </div>
                    ) : null}
                  </>
                )}
              </div>
            )}

            <VentanillaFranjasSelector
              campoId={campoId}
              fecha={fecha}
              bloques={bloques}
              cargandoSlots={cargandoSlots}
              slots={slots}
              toggleSlot={toggleSlot}
              horaAmedida={horaAmedida}
              setHoraAmedida={setHoraAmedida}
              toggleAmedida={toggleAmedida}
              slotsAmedida={slotsAmedida}
            />

            <VentanillaAlumnosSelector
              asociacionDetectada={asociacionDetectada}
              slots={slots}
              cargandoAlumnos={cargandoAlumnos}
              alumnos={alumnos}
              alumnosPorFranja={alumnosPorFranja}
              toggleAlumnoFranja={toggleAlumnoFranja}
            />

            <VentanillaServiciosSelector
              serviciosAdicionales={serviciosAdicionales}
              cargandoServicios={cargandoServicios}
              serviciosAdicionalesValidos={serviciosAdicionalesValidos}
              serviciosSeleccionados={serviciosSeleccionados}
              clasificacionAplicable={clasificacionAplicable}
              resolverTarifaServicio={resolverTarifaServicio}
              toggleServicio={toggleServicio}
              setCantidadServicio={setCantidadServicio}
            />

            <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
              <p className="text-sm font-semibold">Datos del pagador</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Nombre completo *</Label>
                  <Input value={nombre} onChange={(e) => setNombre(e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>CI / NIT *</Label>
                  <div className="relative">
                    <Input value={ciNit} onChange={(e) => setCiNit(e.target.value)} />
                    {asociacionCargando && (
                      <Loader2 className="absolute right-2 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    )}
                  </div>
                  {asociacionDetectada?.vigente && (
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">
                      Asociación vigente: {asociacionDetectada.nombre}
                    </p>
                  )}
                  {!asociacionDetectada && !asociacionCargando && (
                    <p className="text-xs text-muted-foreground">
                      Tipeá el CI de un responsable con convenio: la variante se elige sola.
                    </p>
                  )}
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Teléfono (opcional)</Label>
                  <Input value={telefono} onChange={(e) => setTelefono(e.target.value)} />
                </div>
              </div>
            </div>

            <div className="rounded-xl border bg-card p-3">
              <div className="flex items-center justify-between gap-3">
                <span className="min-w-0 truncate text-xs text-muted-foreground">
                  Estimado · el monto final lo confirma SIREB
                </span>
                <span className="shrink-0 text-lg font-bold tabular-nums">
                  Bs {totalEstimado.toFixed(2)}
                </span>
              </div>
              <p className="mt-1 truncate text-xs text-muted-foreground">
                {slots.length > 0
                  ? `${slots.length} franja${slots.length === 1 ? '' : 's'} · Bs ${subtotalFranja.toFixed(2)}`
                  : 'Sin franja'}
                {' · '}
                Servicios Bs {totalServicios.toFixed(2)}
              </p>
              {!puedeEnviar && motivoDeshabilitado && (
                <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                  {motivoDeshabilitado}
                </p>
              )}
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={cerrar} disabled={enviando}>
                Cancelar
              </Button>
              <Button onClick={enviar} disabled={!puedeEnviar || enviando}>
                {enviando && <Loader2 className="mr-2 size-4 animate-spin" />}
                Crear reserva y código de pago
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
