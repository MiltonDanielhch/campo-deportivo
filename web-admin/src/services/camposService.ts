import apiClient from './apiClient';
import type {
  CambioEstadoCampoPayload,
  CampoDeportivo,
  CampoDeportivoPayload,
  PaginatedResponse,
} from '@/types/parametricas';

const BASE = '/campos-deportivos';

export const camposService = {
  /**
   * Listado paginado con filtros opcionales por tipo_campo_id y estado.
   */
  async listar(params?: {
    tipo_campo_id?: string;
    estado?: string;
    page?: number;
  }) {
    const { data } = await apiClient.get<PaginatedResponse<CampoDeportivo>>(
      BASE,
      { params },
    );
    return data;
  },

  /**
   * Detalle de un campo con sus horarios y tipo_campo.
   */
  async obtener(id: string): Promise<CampoDeportivo> {
    const { data } = await apiClient.get<{ data: CampoDeportivo }>(
      `${BASE}/${id}`,
    );
    return data.data;
  },

  /**
   * Crear un campo deportivo con sus horarios en una transacción atómica.
   */
  async crear(payload: CampoDeportivoPayload): Promise<CampoDeportivo> {
    const { data } = await apiClient.post<{
      message: string;
      data: CampoDeportivo;
    }>(BASE, payload);
    return data.data;
  },

  /**
   * Actualizar datos generales del campo (NO el estado).
   */
  async actualizar(
    id: string,
    payload: CampoDeportivoPayload,
  ): Promise<CampoDeportivo> {
    const { data } = await apiClient.put<{
      message: string;
      data: CampoDeportivo;
    }>(`${BASE}/${id}`, payload);
    return data.data;
  },

  /**
   * Cambiar el estado del campo (activo/mantenimiento/inactivo).
   */
  async cambiarEstado(
    id: string,
    payload: CambioEstadoCampoPayload,
  ): Promise<CampoDeportivo> {
    const { data } = await apiClient.patch<{
      message: string;
      data: CampoDeportivo;
    }>(`${BASE}/${id}/estado`, payload);
    return data.data;
  },

  /**
   * Lista todos los campos activos (para poblar listas de selección).
   * Usa un per_page alto para traer todos en una sola petición.
   */
  async listarTodos(): Promise<CampoDeportivo[]> {
    const { data } = await apiClient.get<PaginatedResponse<CampoDeportivo>>(
      BASE,
      { params: { estado: 'activo', per_page: 100 } },
    );
    return data.data;
  },
};
