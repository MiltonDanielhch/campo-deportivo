import apiClient from './apiClient';
import type {
  PaginatedResponse,
  TipoCampo,
  TipoCampoPayload,
} from '@/types/parametricas';

const BASE = '/tipos-campo';

export const tiposCampoService = {
  /**
   * Listado paginado con filtro opcional por estado.
   */
  async listar(params?: { estado?: string; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<TipoCampo>>(BASE, {
      params,
    });
    return data;
  },

  /**
   * Lista solo los tipos activos (para selects en formularios).
   */
  async listarActivos(): Promise<TipoCampo[]> {
    const { data } = await apiClient.get<{ data: TipoCampo[] }>(
      `${BASE}/activos`,
    );
    return data.data;
  },

  /**
   * Detalle de un tipo de campo.
   */
  async obtener(id: string): Promise<TipoCampo> {
    const { data } = await apiClient.get<{ data: TipoCampo }>(`${BASE}/${id}`);
    return data.data;
  },

  /**
   * Crear un tipo de campo.
   */
  async crear(payload: TipoCampoPayload): Promise<TipoCampo> {
    const { data } = await apiClient.post<{ message: string; data: TipoCampo }>(
      BASE,
      payload,
    );
    return data.data;
  },

  /**
   * Actualizar un tipo de campo existente.
   */
  async actualizar(id: string, payload: TipoCampoPayload): Promise<TipoCampo> {
    const { data } = await apiClient.put<{ message: string; data: TipoCampo }>(
      `${BASE}/${id}`,
      payload,
    );
    return data.data;
  },

  /**
   * Inhabilitar un tipo de campo (cambia estado a 'inactivo').
   */
  async inhabilitar(id: string): Promise<TipoCampo> {
    const { data } = await apiClient.patch<{
      message: string;
      data: TipoCampo;
    }>(`${BASE}/${id}/inhabilitar`);
    return data.data;
  },

  /**
   * Reactivar un tipo de campo previamente inhabilitado.
   */
  async reactivar(id: string): Promise<TipoCampo> {
    const { data } = await apiClient.patch<{
      message: string;
      data: TipoCampo;
    }>(`${BASE}/${id}/reactivar`);
    return data.data;
  },
};