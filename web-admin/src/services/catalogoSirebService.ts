import apiClient from './apiClient';

export interface ServicioSireb {
  id: string;
  codigo: string;
  nombre: string;
  estado: string | null;
  tarifario: string | null;
  precio_min: number | null;
  precio_max: number | null;
  tarifas: any[];
}

export interface CampoLocalVinculado {
  id: string;
  codigo: string;
  nombre: string;
  estado: string;
  direccion: string;
}

export interface CatalogoSirebItem {
  sireb: ServicioSireb;
  campo_local: CampoLocalVinculado | null;
  vinculacion: 'vinculado' | 'sin_vincular';
  reservable_online: boolean;
  mensaje_no_reservable: string | null;
}

export interface CatalogoSirebResponse {
  data: CatalogoSirebItem[];
  meta: {
    fuente: string;
    sincronizado_en: string | null;
    total_servicios: number;
    vinculados: number;
    sin_vincular: number;
  };
}

export const catalogoSirebService = {
  /**
   * Lista todos los servicios SIREB con su campo local vinculado (si existe).
   */
  async listarCatalogo(): Promise<CatalogoSirebResponse> {
    const { data } = await apiClient.get<CatalogoSirebResponse>(
      '/v1/admin/catalogo-sireb/campos',
    );
    return data;
  },

  /**
   * Vincula un campo local a un servicio SIREB.
   */
  async vincularCampo(
    campoId: string,
    servicioSirebId: string,
  ): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.patch<{ message: string; data: any }>(
      `/v1/campos-deportivos/${campoId}/vinculo-sireb`,
      { servicio_sireb_id: servicioSirebId },
    );
    return data;
  },

  /**
   * Desvincula un campo de su servicio SIREB.
   */
  async desvincularCampo(
    campoId: string,
  ): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.delete<{ message: string; data: any }>(
      `/v1/campos-deportivos/${campoId}/vinculo-sireb`,
    );
    return data;
  },

  /**
   * Ejecuta sincronización manual de tarifas desde SIREB.
   */
  async sincronizarTarifas(): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.post<{ message: string; data: any }>(
      '/v1/admin/sireb/sincronizar-tarifas',
    );
    return data;
  },
};
