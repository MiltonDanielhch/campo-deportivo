import apiClient from './apiClient';
import type {
  CambioEstadoCampoPayload,
  CampoDeportivo,
  CampoDeportivoPayload,
  PaginatedResponse,
} from '@/types/parametricas';

const BASE = '/v1/campos-deportivos';

/**
 * Construye un FormData a partir del payload.
 * Los horarios se serializan con la convención que Laravel espera:
 *   horarios[0][dia_semana]=1
 *   horarios[0][hora_apertura]=08:00
 *   horarios[0][hora_cierre]=20:00
 *   horarios[1][...]
 */
function construirFormData(payload: CampoDeportivoPayload): FormData {
  const fd = new FormData();

  fd.append('tipo_campo_id', payload.tipo_campo_id);
  fd.append('codigo', payload.codigo);
  fd.append('nombre', payload.nombre);
  fd.append('direccion', payload.direccion);
  fd.append('latitud', String(payload.latitud));
  fd.append('longitud', String(payload.longitud));

  payload.horarios.forEach((h, i) => {
    fd.append(`horarios[${i}][dia_semana]`, String(h.dia_semana));
    fd.append(`horarios[${i}][hora_apertura]`, h.hora_apertura);
    fd.append(`horarios[${i}][hora_cierre]`, h.hora_cierre);
  });

  if (payload.imagen) {
    fd.append('imagen', payload.imagen);
  }

  if (payload.quitar_imagen) {
    fd.append('quitar_imagen', '1');
  }

  return fd;
}

export const camposService = {
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

  async obtener(id: string): Promise<CampoDeportivo> {
    const { data } = await apiClient.get<{ data: CampoDeportivo }>(
      `${BASE}/${id}`,
    );
    return data.data;
  },

  /**
   * Crear campo con sus horarios. Envía multipart/form-data para soportar imagen opcional.
   */
  async crear(payload: CampoDeportivoPayload): Promise<CampoDeportivo> {
    const { data } = await apiClient.post<{
      message: string;
      data: CampoDeportivo;
    }>(BASE, construirFormData(payload), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  },

  /**
   * Actualizar datos generales del campo. Envía multipart/form-data.
   */
  async actualizar(
    id: string,
    payload: CampoDeportivoPayload,
  ): Promise<CampoDeportivo> {
    const { data } = await apiClient.put<{
      message: string;
      data: CampoDeportivo;
    }>(`${BASE}/${id}`, construirFormData(payload), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  },

    /**
   * Actualiza SOLO la hora de inicio nocturno (hora de corte diurna/nocturna).
   */
  async actualizarHoraNoche(id: string, hora: string): Promise<CampoDeportivo> {
    const { data } = await apiClient.patch<{
      message: string;
      data: CampoDeportivo;
    }>(`${BASE}/${id}/hora-noche`, { hora_inicio_noche: hora });
    return data.data;
  },

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

  async listarTodos(): Promise<CampoDeportivo[]> {
    const { data } = await apiClient.get<PaginatedResponse<CampoDeportivo>>(
      BASE,
      { params: { estado: 'activo', per_page: 100 } },
    );
    return data.data;
  },
};
