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

export interface VarianteArancelariaTarifa {
  tarifa_sireb_id: string;
  etiqueta: string | null;
  clasificacion: string | null;
  monto: number;
}

export interface VarianteArancelaria {
  id: string;
  codigo: string;
  nombre: string;
  descripcion?: string | null;
  tipo_uso?: string | null;
  servicio_sireb_id?: string | null;
  servicio_sireb_codigo?: string | null;
  unidad_medida: string;
  visible_publico: boolean;
  activo: boolean;
  orden: number;
  tarifas: VarianteArancelariaTarifa[];
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
   * Lista las variantes arancelarias activas de un campo físico.
   *
   * P8.2D: usado por ventanilla para campos como Cancha Vieja,
   * donde un mismo espacio físico tiene varios servicios SIREB
   * según el tipo de uso: asociación departamental, municipal, etc.
   */
  async listarVariantesPorCampo(campoId: string): Promise<VarianteArancelaria[]> {
    const { data } = await apiClient.get<{ data: VarianteArancelaria[] }>(
      `/v1/admin/campos/${campoId}/variantes`,
    );

    return data.data ?? [];
  },

  /**
   * Lista los servicios adicionales de un campo físico.
   *
   * P8.3C: limpieza, energía, etc.
   * No son franjas horarias y no ocupan disponibilidad física.
   */
  async listarServiciosAdicionalesPorCampo(campoId: string): Promise<VarianteArancelaria[]> {
    const { data } = await apiClient.get<{ data: VarianteArancelaria[] }>(
      `/v1/admin/campos/${campoId}/servicios-adicionales`,
    );

    return data.data ?? [];
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

  /**
   * Invalida la caché del catálogo SIREB (TTL 10min) y la repuebla
   * iterando todas las páginas. Se usa cuando el operador agrega/quita
   * un servicio en SIREB y no quiere esperar el TTL.
   */
  async refrescarCatalogo(): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.post<{ message: string; data: any }>(
      '/v1/admin/catalogo-sireb/refrescar',
    );
    return data;
  },
};
