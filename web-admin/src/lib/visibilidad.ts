import type { CampoDeportivo } from '@/types/parametricas';

/**
 * Forma mínima de variante que necesita el cálculo de visibilidad.
 * Tanto `VarianteArancelaria` (dialog) como `VarianteResumen` (listado)
 * son estructuralmente asignables a esta interfaz.
 */
export interface VarianteParaVisibilidad {
  visible_publico: boolean;
  activo: boolean;
  unidad_medida?: string | null;
  tipo_asociacion?: string | null;
}

export interface ResumenVisibilidad {
  /** El anónimo vería un precio de franja reservable (config, no sync). */
  publicoVeFranja: boolean;
  /** Tipos de asociación con convenio activo (p.ej. ['departamental']). */
  tiposConvenio: string[];
  totalConvenios: number;
  /** Servicios adicionales visibles (unidad 'servicio'); no ocupan franja. */
  totalAdicionales: number;
}

/**
 * Espeja la INTENCIÓN de CampoPublicoSirebPresenter::discriminarTarifas:
 *  - norma/franja pública = vínculo base del campo o variante visible_publico
 *    con unidad 'hora'.
 *  - convenio = variante visible_publico=false con tipo_asociacion.
 *  - servicio adicional = visible_publico con unidad 'servicio'.
 *
 * Es una VISTA DE CONFIGURACIÓN (qué decidió el admin), no una garantía de
 * que el precio esté espejado: eso depende de "Sincronizar tarifas" desde
 * SIREB. El presenter es la fuente de verdad en runtime.
 */
export function calcularVisibilidad(
  campo: Pick<CampoDeportivo, 'servicio_sireb_id'> | null,
  variantes: VarianteParaVisibilidad[],
): ResumenVisibilidad {
  const activas = variantes.filter((v) => v.activo);
  const normas = activas.filter(
    (v) => v.visible_publico && (v.unidad_medida ?? 'hora') === 'hora',
  );
  const adicionales = activas.filter(
    (v) => v.visible_publico && v.unidad_medida === 'servicio',
  );
  const convenios = activas.filter(
    (v) => !v.visible_publico && Boolean(v.tipo_asociacion),
  );
  const tiposConvenio = Array.from(
    new Set(convenios.map((v) => v.tipo_asociacion as string)),
  );
  const publicoVeFranja =
    Boolean(campo?.servicio_sireb_id) || normas.length > 0;

  return {
    publicoVeFranja,
    tiposConvenio,
    totalConvenios: convenios.length,
    totalAdicionales: adicionales.length,
  };
}
