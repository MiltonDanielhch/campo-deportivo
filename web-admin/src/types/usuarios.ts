/**
 * Tipos TypeScript del dominio de Usuarios (Módulo 2, Fase 2.7).
 * Reflejan los modelos Eloquent del Módulo 1:
 *   - Rol, Funcionario, AsignacionFuncionario
 *
 * Nota: password_hash NUNCA se serializa en el JSON del backend
 * (está oculto en el modelo), por eso no aparece en estos tipos.
 */

import type { CampoDeportivo } from './parametricas';

// ─── Estados ─────────────────────────────────────────────────────────────

export type EstadoFuncionario = 'activo' | 'inactivo';

// ─── Rol ─────────────────────────────────────────────────────────────────

export interface Rol {
  id: string;
  nombre: string;
  descripcion: string | null;
  /** Array de permisos; ['*'] = superadmin */
  permisos: string[];
}

// ─── Funcionario ─────────────────────────────────────────────────────────

export interface Funcionario {
  id: string;
  nombre_completo: string;
  ci: string;
  usuario: string;
  rol_id: string;
  estado: EstadoFuncionario;
  creado_en: string;
  /** Relación cargada con with('rol') */
  rol?: Rol;
  /** Relación cargada en el detalle de asignaciones */
  campos_asignados?: CampoDeportivo[];
}

/** Payload para crear un funcionario nuevo. */
export interface FuncionarioPayload {
  nombre_completo: string;
  ci: string;
  usuario: string;
  /** Contraseña inicial en texto plano; el backend la hashea */
  password: string;
  password_confirmation: string;
  rol_id: string;
}

/** Payload para actualizar un funcionario (contraseña opcional). */
export interface FuncionarioUpdatePayload {
  nombre_completo?: string;
  ci?: string;
  usuario?: string;
  /** Si se omite o va vacía, la contraseña no cambia */
  password?: string;
  password_confirmation?: string;
  rol_id?: string;
}

/** Payload para activar/inactivar un funcionario. */
export interface CambioEstadoFuncionarioPayload {
  estado: EstadoFuncionario;
}

// ─── AsignacionFuncionario ───────────────────────────────────────────────

export interface AsignacionFuncionario {
  id: string;
  funcionario_id: string;
  campo_id: string;
  asignado_en: string;
}

/**
 * Funcionario con sus campos asignados (endpoint GET /asignaciones).
 * Solo devuelve funcionarios con rol funcionario_control que tienen
 * al menos una asignación.
 */
export interface FuncionarioConAsignaciones extends Funcionario {
  campos_asignados: CampoDeportivo[];
}
