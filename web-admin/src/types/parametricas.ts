/**
 * Tipos TypeScript del dominio de Paramétricas (Módulo 2).
 * Reflejan los modelos Eloquent del Módulo 1:
 *   - TipoCampo, CampoDeportivo, HorarioAtencion, TarifaCampo
 *
 * Nota sobre DECIMAL: PostgreSQL devuelve los campos DECIMAL (latitud,
 * longitud, precio_por_hora) como string en el JSON de Laravel.
 * Por eso se tipan como string en las respuestas, y como number en los
 * payloads de escritura (el backend los acepta como número).
 */

// ─── Estados (union types para type-safety) ──────────────────────────────

export type EstadoTipoCampo = 'activo' | 'inactivo';

export type EstadoCampo = 'activo' | 'mantenimiento' | 'inactivo';

// ─── TipoCampo ───────────────────────────────────────────────────────────

export interface TipoCampo {
  id: string;
  nombre: string;
  descripcion: string | null;
  estado: EstadoTipoCampo;
  creado_en?: string | null;
}

/** Payload para crear/actualizar un tipo de campo. */
export interface TipoCampoPayload {
  nombre: string;
  descripcion?: string | null;
}

// ─── HorarioAtencion ─────────────────────────────────────────────────────

export interface HorarioAtencion {
  id: string;
  campo_id: string;
  /** 1 = Lunes, 7 = Domingo */
  dia_semana: number;
  /** Formato HH:MM */
  hora_apertura: string;
  /** Formato HH:MM */
  hora_cierre: string;
}

/** Payload para crear horarios junto con un campo. */
export interface HorarioAtencionPayload {
  dia_semana: number;
  hora_apertura: string;
  hora_cierre: string;
}

// ─── CampoDeportivo ──────────────────────────────────────────────────────

export interface CampoDeportivo {
  id: string;
  tipo_campo_id: string;
  codigo: string;
  nombre: string;
  direccion: string;
  provincia?: string | null;
  municipio?: string | null;
  latitud: string;
  longitud: string;
  estado: EstadoCampo;
  imagen_url: string | null;
  /** Hora (HH:MM:SS) a partir de la cual se aplica la tarifa nocturna */
  hora_inicio_noche: string;
  /** UUID del servicio SIREB vinculado (nullable) */
  servicio_sireb_id: string | null;
  /** Código legible del servicio SIREB (nullable) */
  servicio_sireb_codigo: string | null;
  /** Modalidad de reserva: franja_horaria (canchas), turno_por_perfil (piscina), sin_reserva_online */
  modalidad_reserva?: 'franja_horaria' | 'turno_por_perfil' | 'sin_reserva_online';
  creado_en: string;
  tipo_campo?: TipoCampo;
  horarios_atencion?: HorarioAtencion[];
}

export interface CampoDeportivoPayload {
  tipo_campo_id: string;
  codigo: string;
  nombre: string;
  direccion: string;
  provincia?: string | null;
  municipio?: string | null;
  latitud: number;
  longitud: number;
  horarios: HorarioAtencionPayload[];
  imagen?: File | null;
  quitar_imagen?: boolean;
}

/** Payload para cambiar el estado operativo de un campo. */
export interface CambioEstadoCampoPayload {
  estado: EstadoCampo;
}

// ─── TarifaCampo ─────────────────────────────────────────────────────────

export type TipoTarifa = 'diurna' | 'nocturna';

export type ClasificacionTarifa =
  | 'diurna'
  | 'nocturna'
  | 'unica'
  | 'no_clasificada';

export type PerfilTarifa =
  | 'mayor_12'
  | 'menor_12'
  | 'institucion'
  | string;

// ─── Resumen de funcionario (usado en auditorías de tarifas) ─────────

export interface FuncionarioResumen {
  id: string;
  nombre_completo: string;
  mamore_id?: string | null;
}

export interface TarifaCampo {
  id: string;
  campo_id: string;

  /**
   * Columna legacy. Puede ser null en tarifas únicas o no clasificadas.
   */
  tipo_tarifa: TipoTarifa | null;

  /**
   * Clasificación canónica derivada del espejo SIREB.
   */
  clasificacion?: ClasificacionTarifa | null;

  /**
   * Perfil ortogonal al horario: piscina, instituciones, etc.
   * Si es distinto de null, la tarifa NO debe entrar a grilla horaria de canchas.
   */
  perfil?: PerfilTarifa | null;

  /**
   * Etiqueta cruda enviada por SIREB.
   */
  etiqueta_sireb?: string | null;

  /**
   * UUID de la tarifa en SIREB. Es la llave real del espejo/upsert.
   */
  tarifa_sireb_id?: string | null;

  /**
   * UUID del servicio SIREB asociado.
   */
  servicio_sireb_id?: string | null;

  /**
   * Indica si SIREB permite liquidar esta tarifa.
   */
  liquidable?: boolean;

  /**
   * Estado del tarifario en SIREB: vigente, programado, derogado, etc.
   */
  tarifario_estado?: string | null;

  /**
   * Estado de la tarifa en SIREB: activo, inactivo, etc.
   */
  estado_tarifa_sireb?: string | null;

  rango_desde?: string | null;
  rango_hasta?: string | null;

  /**
   * Categorías crudas de SIREB. Hoy SEDEDE suele mandar [], pero el schema las prevé.
   */
  categorias_sireb?: unknown[] | null;

  /**
   * Cuándo se espejó esta tarifa desde SIREB.
   * No confundir con vigente_desde, que es desde cuándo rige en el espejo local.
   */
  sincronizado_en?: string | null;

  precio_por_hora: string;
  vigente_desde: string;
  vigente_hasta: string | null;

  /**
   * Funcionario que registró la tarifa. Es null en las tarifas espejadas
   * desde SIREB por el job de sincronización (no las carga una persona).
   */
  creado_por: FuncionarioResumen | string | null;
}

/** Respuesta del endpoint GET /campos-deportivos/{id}/tarifas */
export interface HistorialTarifas {
  hora_inicio_noche: string | null;

  /**
   * Última sincronización desde SIREB para este campo.
   */
  sincronizado_en?: string | null;

  /**
   * Tarifas activas ofrecibles en grilla horaria.
   */
  activas: {
    diurna: TarifaCampo | null;
    nocturna: TarifaCampo | null;
    unica?: TarifaCampo | null;
  };

  /**
   * Tarifas espejadas desde SIREB que NO deben entrar a grilla horaria:
   * piscina, instituciones, perfiles o no clasificadas.
   */
  no_horarias?: TarifaCampo[];

  /**
   * Historial completo, incluyendo versiones cerradas.
   */
  historial: TarifaCampo[];
}

// ─── Respuesta paginada de Laravel ───────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  from: number | null;
  last_page: number;
  last_page_url: string;
  per_page: number;
  to: number | null;
  total: number;
}


export interface UbicacionesOficiales {
  provincias: string[];
  municipios: Record<string, string[]>;
}

export interface UbicacionesOficialesResponse {
  data: UbicacionesOficiales;
}
