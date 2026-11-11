import { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import { VentanillaCampoCombobox } from './ventanilla/VentanillaCampoCombobox';
import { VentanillaVarianteSelector } from './ventanilla/VentanillaVarianteSelector';
import { VentanillaPagadorForm } from './ventanilla/VentanillaPagadorForm';
import { VentanillaEstimadoFooter } from './ventanilla/VentanillaEstimadoFooter';
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
              <VentanillaCampoCombobox
                campoId={campoId}
                campoNombre={campoNombre}
                busquedaCampo={busquedaCampo}
                setBusquedaCampo={setBusquedaCampo}
                campoAbierto={campoAbierto}
                setCampoAbierto={setCampoAbierto}
                campoWrapRef={campoWrapRef}
                campoInputRef={campoInputRef}
                camposFiltrados={camposFiltrados}
                onSelect={handleCampoChangeCompuesto}
              />

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

            <VentanillaVarianteSelector
              cargandoVariantes={cargandoVariantes}
              tieneVariantes={tieneVariantes}
              requiereVariante={requiereVariante}
              bloqueadoPorVariantesNoHorarias={bloqueadoPorVariantesNoHorarias}
              varianteId={varianteId}
              varianteNombre={varianteNombre}
              variantesHorarias={variantesHorarias}
              varianteSeleccionada={varianteSeleccionada}
              asociacionDetectada={asociacionDetectada}
              clasificacionAplicable={clasificacionAplicable}
              haySlots={slots.length > 0}
              onChange={handleVarianteChange}
            />

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

            <VentanillaPagadorForm
              nombre={nombre}
              onNombreChange={setNombre}
              ciNit={ciNit}
              onCiNitChange={setCiNit}
              telefono={telefono}
              onTelefonoChange={setTelefono}
              asociacionCargando={asociacionCargando}
              asociacionDetectada={asociacionDetectada}
            />

            <VentanillaEstimadoFooter
              totalEstimado={totalEstimado}
              cantidadFranjas={slots.length}
              subtotalFranja={subtotalFranja}
              totalServicios={totalServicios}
              puedeEnviar={puedeEnviar}
              motivoDeshabilitado={motivoDeshabilitado}
              enviando={enviando}
              onCancel={cerrar}
              onSubmit={enviar}
            />
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
