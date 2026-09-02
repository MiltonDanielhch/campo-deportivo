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
  /** DECIMAL(10,8) — viene como string desde Laravel */
  latitud: string;
  /** DECIMAL(11,8) — viene como string desde Laravel */
  longitud: string;
  estado: EstadoCampo;
  creado_en: string;
  /** Relación cargada con with('tipoCampo') */
  tipo_campo?: TipoCampo;
  /** Relación cargada en el detalle */
  horarios_atencion?: HorarioAtencion[];
}

/** Payload para crear un campo con sus horarios en una transacción. */
export interface CampoDeportivoPayload {
  tipo_campo_id: string;
  codigo: string;
  nombre: string;
  direccion: string;
  latitud: number;
  longitud: number;
  horarios: HorarioAtencionPayload[];
}

/** Payload para cambiar el estado de un campo. */
export interface CambioEstadoCampoPayload {
  estado: EstadoCampo;
}

// ─── TarifaCampo ─────────────────────────────────────────────────────────

/** Resumen de funcionario (relación cargada) */
export interface FuncionarioResumen {
  id: string;
  nombre_completo: string;
}

export interface TarifaCampo {
  id: string;
  campo_id: string;
  /** DECIMAL(10,2) — viene como string desde Laravel */
  precio_por_hora: string;
  vigente_desde: string;
  /** null si es la tarifa activa actual */
  vigente_hasta: string | null;
  /**
   * UUID string si la relación NO está cargada.
   * Objeto FuncionarioResumen si el backend la carga con load('creadoPor'),
   * porque Laravel serializa la relación con el mismo nombre que la FK.
   */
  creado_por: string | FuncionarioResumen;
}

/** Payload para fijar una nueva tarifa. */
export interface TarifaCampoPayload {
  precio_por_hora: number;
}

/** Respuesta del endpoint GET /campos-deportivos/{id}/tarifas */
export interface HistorialTarifas {
  activa: TarifaCampo | null;
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