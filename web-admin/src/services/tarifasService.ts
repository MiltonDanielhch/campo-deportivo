import apiClient from './apiClient';
import type { HistorialTarifas } from '@/types/parametricas';

const BASE = '/v1/campos-deportivos';

/**
 * Tarifas de un campo. SOLO LECTURA.
 *
 * El precio lo define SIREB (fuente de verdad del contrato de recaudaciones)
 * y el backend lo espeja con `sireb:sincronizar-tarifas`. No existe método
 * para crear ni modificar tarifas desde el panel.
 */
export const tarifasService = {
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
