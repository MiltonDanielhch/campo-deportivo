import apiClient from './apiClient';
import type {
  Alumno,
  AlumnoPayload,
  Asociacion,
  AsociacionListParams,
  AsociacionPayload,
  Paginated,
} from '@/types/asociaciones';

/**
 * Si apiClient es axios crudo, la respuesta trae { status, data, ... }.
 * Si tu apiClient ya desenvuelve el body en un interceptor, te llega directo el body.
 * Este unwrap maneja los dos casos para que no tengas que adivinar.
 */
function unwrap<T>(raw: unknown): T {
  if (typeof raw === 'object' && raw !== null && 'status' in raw && 'data' in raw) {
    return (raw as { data: T }).data;
  }

  return raw as T;
}

export const asociacionesService = {
  listar: async (params: AsociacionListParams = {}): Promise<Paginated<Asociacion>> => {
    const raw = await apiClient.get('/v1/asociaciones', { params });
    return unwrap<Paginated<Asociacion>>(raw);
  },

  crear: async (payload: AsociacionPayload): Promise<Asociacion> => {
    const raw = await apiClient.post('/v1/asociaciones', payload);
    return unwrap<{ data: Asociacion }>(raw).data;
  },

  actualizar: async (id: string, payload: Partial<AsociacionPayload>): Promise<Asociacion> => {
    const raw = await apiClient.put(`/v1/asociaciones/${id}`, payload);
    return unwrap<{ data: Asociacion }>(raw).data;
  },

  cambiarEstado: async (id: string, activo: boolean): Promise<Asociacion> => {
    const raw = await apiClient.patch(`/v1/asociaciones/${id}/estado`, { activo });
    return unwrap<{ data: Asociacion }>(raw).data;
  },

  /**
   * Pa' la ventanilla: "¿este CI es asociación vigente?".
   * El backend responde siempre 200 con { asociacion: Asociacion | null }.
   * El null no es error: significa "no es asociación".
   */
  porResponsable: async (ci: string): Promise<{ asociacion: Asociacion | null }> => {
    const raw = await apiClient.get(
      `/v1/admin/asociaciones/por-responsable/${encodeURIComponent(ci)}`,
    );

    return unwrap<{ asociacion: Asociacion | null }>(raw);
  },

  /**
   * [Fase 3] Alumnos ACTIVOS de una asociacion, pa' el multi-select de
   * ventanilla. Mismo unwrap que porResponsable (maneja apiClient crudo o
   * ya desenvuelto). Endpoint admin (role:admin_*), no publico: son datos de
   * terceros, potencialmente menores.
   */
  listarAlumnos: async (asociacionId: string): Promise<AlumnoVentanilla[]> => {
    const raw = await apiClient.get(`/v1/admin/asociaciones/${asociacionId}/alumnos`);
    return unwrap<{ data: AlumnoVentanilla[] }>(raw).data;
  },

  // ─── Fase 5: admin-fallback de alumnos ───
  listarAlumnosGestion: async (asociacionId: string): Promise<Alumno[]> => {
    const raw = await apiClient.get(
      `/v1/admin/asociaciones/${asociacionId}/alumnos/gestion`,
    );
    return unwrap<{ data: Alumno[] }>(raw).data;
  },

  crearAlumno: async (
    asociacionId: string,
    payload: AlumnoPayload,
  ): Promise<Alumno> => {
    const raw = await apiClient.post(
      `/v1/admin/asociaciones/${asociacionId}/alumnos`,
      payload,
    );
    return unwrap<{ data: Alumno }>(raw).data;
  },

  actualizarAlumno: async (
    asociacionId: string,
    alumnoId: string,
    payload: AlumnoPayload,
  ): Promise<Alumno> => {
    const raw = await apiClient.put(
      `/v1/admin/asociaciones/${asociacionId}/alumnos/${alumnoId}`,
      payload,
    );
    return unwrap<{ data: Alumno }>(raw).data;
  },

  cambiarEstadoAlumno: async (
    asociacionId: string,
    alumnoId: string,
    activo: boolean,
  ): Promise<Alumno> => {
    const raw = await apiClient.patch(
      `/v1/admin/asociaciones/${asociacionId}/alumnos/${alumnoId}/estado`,
      { activo },
    );
    return unwrap<{ data: Alumno }>(raw).data;
  },
};

export interface AlumnoVentanilla {
  id: string;
  ci: string;
  nombre_completo: string;
}
