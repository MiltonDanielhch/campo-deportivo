import apiClient from './apiClient';
import type {
  IngresosResponse,
  HorasPicoResponse,
  ClientesFrecuentesResponse,
} from '@/types/reportes';

export const reportesService = {
  /**
   * GET /api/v1/reportes/ingresos
   */
  async ingresos(desde: string, hasta: string, campoId?: string): Promise<IngresosResponse> {
    const params: any = { desde, hasta };
    if (campoId) params.campo_id = campoId;

    const { data } = await apiClient.get<IngresosResponse>(
      '/v1/reportes/ingresos',
      { params }
    );
    return data;
  },

  /**
   * GET /api/v1/reportes/horas-pico
   */
  async horasPico(desde: string, hasta: string): Promise<HorasPicoResponse> {
    const { data } = await apiClient.get<HorasPicoResponse>(
      '/v1/reportes/horas-pico',
      { params: { desde, hasta } }
    );
    return data;
  },

  /**
   * GET /api/v1/reportes/clientes-frecuentes
   */
  async clientesFrecuentes(
    desde: string,
    hasta: string,
    limite?: number
  ): Promise<ClientesFrecuentesResponse> {
    const params: any = { desde, hasta };
    if (limite) params.limite = limite;

    const { data } = await apiClient.get<ClientesFrecuentesResponse>(
      '/v1/reportes/clientes-frecuentes',
      { params }
    );
    return data;
  },
};
