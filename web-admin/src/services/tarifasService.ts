import apiClient from './apiClient';
import type {
  HistorialTarifas,
  TarifaCampo,
  TarifaCampoPayload,
} from '@/types/parametricas';

/**
 * Endpoints de tarifas, anidados bajo /campos-deportivos/{id}/tarifas.
 */
export const tarifasService = {
  /**
   * Fijar una nueva tarifa para un campo.
   * La tarifa anterior se cierra automáticamente.
   */
  async crear(campoId: string, payload: TarifaCampoPayload): Promise<TarifaCampo> {
    const { data } = await apiClient.post<{
      message: string;
      data: TarifaCampo;
    }>(`/campos-deportivos/${campoId}/tarifas`, payload);
    return data.data;
  },

  /**
   * Historial completo de tarifas de un campo:
   * la tarifa activa actual + todas las anteriores.
   */
  async historial(campoId: string): Promise<HistorialTarifas> {
    const { data } = await apiClient.get<{ data: HistorialTarifas }>(
      `/campos-deportivos/${campoId}/tarifas`,
    );
    return data.data;
  },
};