import apiClient from './apiClient';
import type { AsistenciaReservaResponse } from '@/types/reservas';

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
};
