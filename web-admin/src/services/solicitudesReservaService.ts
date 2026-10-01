import apiClient from './apiClient';
import type {
  DetalleSolicitudResponse,
  ListadoSolicitudesResponse,
} from '@/types/reservas';

const BASE = '/v1/admin/solicitudes-reserva';

export const solicitudesReservaService = {
  async listar(params?: {
    estado?: string;
    codigo?: string;
    desde?: string;
    hasta?: string;
    page?: number;
    per_page?: number;
  }): Promise<ListadoSolicitudesResponse> {
    const { data } = await apiClient.get<ListadoSolicitudesResponse>(BASE, {
      params,
    });
    return data;
  },

  async obtener(id: string): Promise<DetalleSolicitudResponse> {
    const { data } = await apiClient.get<{ data: DetalleSolicitudResponse }>(
      `${BASE}/${id}`,
    );
    return data.data;
  },

  async refrescarSireb(id: string): Promise<{
    estado_sireb: any;
    puede_anularse: boolean;
  }> {
    const { data } = await apiClient.get<{
      data: { estado_sireb: any; puede_anularse: boolean };
    }>(`${BASE}/${id}/refrescar-sireb`);
    return data.data;
  },

  async anularLiquidacion(
    id: string,
    motivo: string,
  ): Promise<{ message: string }> {
    const { data } = await apiClient.post<{ message: string }>(
      `${BASE}/${id}/anular-liquidacion`,
      { motivo },
    );
    return data;
  },
};
