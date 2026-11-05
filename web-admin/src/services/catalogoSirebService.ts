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
  sireb: {
    id: string;
    codigo: string;
    nombre: string;
    descripcion?: string | null;
    rubro?: string | null;
    unidad_medida?: string | null;
    modo_tarifa?: string | null;
    requiere_factura?: boolean | null;
    plazo_pago_dias?: number | null;
    estado?: string | null;
    tarifario?: string | null;
    precio_min?: number | null;
    precio_max?: number | null;
    tarifas: unknown[];
  };

  campo_local?: {
    id: string;
    codigo: string;
    nombre: string;
    estado: string;
    direccion?: string | null;
    vinculacion_tipo?: 'directo' | 'variante';
    variante_id?: string | null;
    variante_nombre?: string | null;
    variante_tipo_uso?: string | null;
    variante_unidad_medida?: string | null;
  } | null;

  vinculacion: 'vinculado' | 'sin_vincular';
  tipo_vinculacion?: 'directo' | 'variante' | null;
  variante_id?: string | null;

  clasificacion: string;
  clasificacion_motivo: string;
  accion_recomendada: string;

  reservable_online: boolean;
  mensaje_no_reservable?: string | null;
}

export interface CatalogoSirebResponse {
  data: CatalogoSirebItem[];
  meta: {
    fuente: string;
    sincronizado_en?: string | null;
    total_servicios: number;
    vinculados: number;
    vinculados_directos: number;
    vinculados_por_variante: number;
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
  tipo_asociacion?: 'departamental' | 'municipal' | null;
  tarifas: VarianteArancelariaTarifa[];
}

/** Payload de escritura de variante (store/update). */
export interface VariantePayload {
  codigo: string;
  nombre: string;
  descripcion?: string | null;
  tipo_uso: string;
  servicio_sireb_id?: string | null;
  unidad_medida: string;
  visible_publico?: boolean;
  activo?: boolean;
  orden?: number;
}

export const catalogoSirebService = {
  async listarCatalogo(): Promise<CatalogoSirebResponse> {
    const { data } = await apiClient.get<CatalogoSirebResponse>(
      '/v1/admin/catalogo-sireb/campos',
    );
    return data;
  },

  async listarVariantesPorCampo(campoId: string): Promise<VarianteArancelaria[]> {
    const { data } = await apiClient.get<{ data: VarianteArancelaria[] }>(
      `/v1/admin/campos/${campoId}/variantes`,
    );
    return data.data ?? [];
  },

  async listarServiciosAdicionalesPorCampo(campoId: string): Promise<VarianteArancelaria[]> {
    const { data } = await apiClient.get<{ data: VarianteArancelaria[] }>(
      `/v1/admin/campos/${campoId}/servicios-adicionales`,
    );
    return data.data ?? [];
  },

  /** ⟦BLOQUE 2⟧ Cuelga un servicio SIREB como variante bajo un campo físico. */
  async crearVariante(
    campoId: string,
    payload: VariantePayload,
  ): Promise<{ message: string; data: VarianteArancelaria }> {
    const { data } = await apiClient.post<{ message: string; data: VarianteArancelaria }>(
      `/v1/admin/campos/${campoId}/variantes`,
      payload,
    );
    return data;
  },

  /** ⟦BLOQUE 2⟧ Edita una variante existente. */
  async actualizarVariante(
    campoId: string,
    varianteId: string,
    payload: VariantePayload,
  ): Promise<{ message: string; data: VarianteArancelaria }> {
    const { data } = await apiClient.put<{ message: string; data: VarianteArancelaria }>(
      `/v1/admin/campos/${campoId}/variantes/${varianteId}`,
      payload,
    );
    return data;
  },

  /** ⟦BLOQUE 2⟧ Desvincula la variante del servicio SIREB y la desactiva. */
  async desvincularVariante(
    campoId: string,
    varianteId: string,
  ): Promise<{ message: string; data: VarianteArancelaria }> {
    const { data } = await apiClient.delete<{ message: string; data: VarianteArancelaria }>(
      `/v1/admin/campos/${campoId}/variantes/${varianteId}`,
    );
    return data;
  },

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

  async desvincularCampo(
    campoId: string,
  ): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.delete<{ message: string; data: any }>(
      `/v1/campos-deportivos/${campoId}/vinculo-sireb`,
    );
    return data;
  },

  async sincronizarTarifas(): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.post<{ message: string; data: any }>(
      '/v1/admin/sireb/sincronizar-tarifas',
    );
    return data;
  },

  async refrescarCatalogo(): Promise<{ message: string; data: any }> {
    const { data } = await apiClient.post<{ message: string; data: any }>(
      '/v1/admin/catalogo-sireb/refrescar',
    );
    return data;
  },
};
