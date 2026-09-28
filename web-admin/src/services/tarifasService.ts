import apiClient from './apiClient';
import type {
  HistorialTarifas,
  TarifaCampo,
  TarifaCampoPayload,
} from '@/types/parametricas';

const BASE = '/v1/campos-deportivos';

export const tarifasService = {
  /**
   * Fijar una nueva tarifa (diurna o nocturna) para un campo.
   * Solo se cierra la tarifa anterior del MISMO tipo.
   */
  async crear(campoId: string, payload: TarifaCampoPayload): Promise<TarifaCampo> {
    const { data } = await apiClient.post<{
      message: string;
      data: TarifaCampo;
    }>(`${BASE}/${campoId}/tarifas`, payload);
    return data.data;
  },

  /**
   * Historial completo + tarifas activas (diurna y nocturna) + hora de corte.
   */
  async historial(campoId: string): Promise<HistorialTarifas> {
    const { data } = await apiClient.get<{ data: HistorialTarifas }>(
      `${BASE}/${campoId}/tarifas`,
    );
    return data.data;
  },
};
