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
  creado_en: string;
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
  creado_en: string;
  tipo_campo?: TipoCampo;
  horarios_atencion?: HorarioAtencion[];
}

export interface CampoDeportivoPayload {
  tipo_campo_id: string;
  codigo: string;
  nombre: string;
  direccion: string;
  latitud: number;
  longitud: number;
  horarios: HorarioAtencionPayload[];
  imagen?: File | null;
  quitar_imagen?: boolean;
}

// ─── TarifaCampo ─────────────────────────────────────────────────────────

export type TipoTarifa = 'diurna' | 'nocturna';

// ─── Resumen de funcionario (usado en auditorías de tarifas) ─────────

export interface FuncionarioResumen {
  id: string;
  nombre_completo: string;
  mamore_id?: string | null;
}

export interface TarifaCampo {
  id: string;
  campo_id: string;
  tipo_tarifa: TipoTarifa;
  precio_por_hora: string;
  vigente_desde: string;
  vigente_hasta: string | null;
  /**
   * Funcionario que registró la tarifa. Es null en las tarifas espejadas
   * desde SIREB por el job de sincronización (no las carga una persona).
   */
  creado_por: FuncionarioResumen | string | null;
}

export interface TarifaCampoPayload {
  tipo_tarifa: TipoTarifa;
  precio_por_hora: number;
}

/** Respuesta del endpoint GET /campos-deportivos/{id}/tarifas */
export interface HistorialTarifas {
  hora_inicio_noche: string;
  activas: {
    diurna: TarifaCampo | null;
    nocturna: TarifaCampo | null;
  };
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
