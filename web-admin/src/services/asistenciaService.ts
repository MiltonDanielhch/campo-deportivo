import apiClient from './apiClient';
import type {
  AsistenciaReservaResponse,
  AsistenciaAlumnoResponse,
} from '@/types/reservas';

export const asistenciaService = {
  /**
   * Marca o desmarca asistencia de una reserva.
   *
   * Endpoint backend Fase 7.2:
   * POST /api/v1/admin/reservas/{id}/asistencia
   *
   * Body:
   * { "marcar": true | false }
   */
  async marcar(
    reservaId: string,
    marcar: boolean,
  ): Promise<AsistenciaReservaResponse> {
    const { data } = await apiClient.post<{
      data: AsistenciaReservaResponse;
    }>(`/v1/admin/reservas/${reservaId}/asistencia`, {
      marcar,
    });

    return data.data;
  },

  /**
   * [Fase 4] Marca o desmarca asistencia de un ALUMNO en una franja.
   *
   * Endpoint backend Fase 4:
   * POST /api/v1/admin/solicitud-detalle-alumno/{id}/asistencia
   *
   * {id} es el uuid de la bridge (alumno asignado a la franja), no el del
   * alumno ni el de la reserva. Body: { "marcar": true | false }.
   */
  async marcarAlumno(
    bridgeId: string,
    marcar: boolean,
  ): Promise<AsistenciaAlumnoResponse> {
    const { data } = await apiClient.post<{
      data: AsistenciaAlumnoResponse;
    }>(`/v1/admin/solicitud-detalle-alumno/${bridgeId}/asistencia`, {
      marcar,
    });

    return data.data;
  },
};
