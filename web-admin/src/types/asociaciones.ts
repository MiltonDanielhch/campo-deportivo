export type TipoAsociacion = 'departamental' | 'municipal';

export interface Asociacion {
  id: string;
  nit: string;
  nombre: string;
  tipo: TipoAsociacion | null;
  convenio_numero: string | null;
  convenio_desde: string | null;
  convenio_hasta: string | null;
  responsable_nombre: string;
  responsable_ci: string;
  responsable_telefono: string | null;
  activo: boolean;
  /** Derivado en el resource (esVigente()): pa' saber si puedo ofrecer convenio YA. */
  vigente: boolean;
}

/** Lo que manda el form (sin id/activo/vigente, que los maneja el backend o el PATCH de estado). */
export interface AsociacionPayload {
  nit: string;
  nombre: string;
  tipo: TipoAsociacion | null;
  convenio_numero: string | null;
  convenio_desde: string | null;
  convenio_hasta: string | null;
  responsable_nombre: string;
  responsable_ci: string;
  responsable_telefono: string | null;
}

export interface AsociacionListParams {
  buscar?: string;
  tipo?: string;
  per_page?: number;
  page?: number;
}

/** Shape de Laravel Resource::collection(paginate()). */
export interface Paginated<T> {
  data: T[];
  meta?: { total: number; current_page: number; last_page: number; from?: number; to?: number };
  links?: { next?: string | null; prev?: string | null };
}

// ─── Fase 5: admin-fallback de alumnos ──────────────────────────────────
// Alumno completo (gestión admin). Distinto de AlumnoVentanilla (flaco,
// activo-only, en asociacionesService) a propósito: el multi-select de
// ventanilla no necesita fecha_nacimiento ni inactivos.
export interface Alumno {
  id: string;
  ci: string;
  nombre_completo: string;
  fecha_nacimiento: string | null;
  activo: boolean;
}

/** Lo que manda el form de alumno (sin id/activo, que maneja el backend). */
export interface AlumnoPayload {
  ci: string;
  nombre_completo: string;
  fecha_nacimiento: string | null;
}
