import apiClient from './apiClient';
import type { MisCamposResponse, VerificarCodigoResponse } from '@/types/ocupacion';

export const ocupacionService = {
  /**
   * GET /api/v1/ocupacion/mis-campos
   *
   * Devuelve los campos asignados al funcionario con su ocupación del día.
   * Si es admin, devuelve todos los campos activos.
   */
  async misCampos(fecha?: string): Promise<MisCamposResponse> {
    const params = fecha ? { fecha } : {};
    const { data } = await apiClient.get<MisCamposResponse>(
      '/v1/ocupacion/mis-campos',
      { params }
    );
    return data;
  },

  /**
   * GET /api/v1/ocupacion/verificar/{codigo}
   *
   * Verifica un código de reserva. Si el funcionario es funcionario_control
   * y el campo no está entre sus asignados, responde 404.
   */
  async verificarCodigo(codigo: string): Promise<VerificarCodigoResponse> {
    const { data } = await apiClient.get<VerificarCodigoResponse>(
      `/v1/ocupacion/verificar/${codigo}`
    );
    return data;
  },

  /**
   * GET /api/v1/ocupacion/mapa-global
   *
   * Devuelve todos los campos activos o en mantenimiento con su ocupación
   * del día, coordenadas y metadatos administrativos.
   * Solo accesible para admin_parametricas y gerencia.
   */
  async mapaGlobal(fecha?: string): Promise<import('@/types/mapaGlobal').MapaGlobalResponse> {
    const params = fecha ? { fecha } : {};
    const { data } = await apiClient.get<import('@/types/mapaGlobal').MapaGlobalResponse>(
      '/v1/ocupacion/mapa-global',
      { params }
    );
    return data;
  },
};
