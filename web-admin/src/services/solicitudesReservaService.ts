import apiClient from './apiClient';
import type {
  CampoOption,
  DetalleSolicitudResponse,
  FiltrosReserva,
  ListadoSolicitudesResponse,
} from '@/types/reservas';

const BASE = '/v1/admin/solicitudes-reserva';
const EXPORT_BASE = '/v1/admin/reservas/export';

function obtenerNombreArchivo(header?: string): string | null {
  if (!header) return null;

  // Soporta:
  // filename="reservas-2026-10-03.csv"
  // filename=reservas-2026-10-03.csv
  const match = /filename="?([^"]+)"?/i.exec(header);

  return match?.[1] ?? null;
}

async function leerMensajeError(error: any): Promise<string> {
  const data = error?.response?.data;

  // Cuando responseType es 'blob', el error puede venir como Blob.
  if (data instanceof Blob) {
    try {
      const text = await data.text();
      const json = JSON.parse(text);

      return (
        json.message ||
        json.error ||
        json.errors?.reserva?.[0] ||
        'Error al exportar el CSV.'
      );
    } catch {
      return 'Error al exportar el CSV.';
    }
  }

  return (
    data?.message ||
    data?.error ||
    data?.errors?.reserva?.[0] ||
    error?.message ||
    'Error al exportar el CSV.'
  );
}

export const solicitudesReservaService = {
  async listar(
    params?: FiltrosReserva,
  ): Promise<ListadoSolicitudesResponse> {
    const { data } = await apiClient.get<ListadoSolicitudesResponse>(BASE, {
      params,
    });

    return data;
  },

  async obtener(id: string): Promise<DetalleSolicitudResponse> {
    const { data } = await apiClient.get<{
      data: DetalleSolicitudResponse;
    }>(`${BASE}/${id}`);

    return data.data;
  },

  async refrescarSireb(id: string): Promise<{
    estado_sireb: unknown;
    puede_anularse: boolean;
  }> {
    const { data } = await apiClient.get<{
      data: {
        estado_sireb: unknown;
        puede_anularse: boolean;
      };
    }>(`${BASE}/${id}/refrescar-sireb`);

    return data.data;
  },

  async anularLiquidacion(
    id: string,
    motivo: string,
  ): Promise<{ message: string }> {
    const { data } = await apiClient.post<{ message: string }>(
      `${BASE}/${id}/anular-liquidacion`,
      { motivo },
    );

    return data;
  },

  /**
   * Descarga el CSV de reservas/franjas con los filtros actuales.
   *
   * Backend Fase 7.3:
   * GET /api/v1/admin/reservas/export?formato=csv
   *
   * Solo permitido para:
   * - admin_parametricas
   * - admin_reservas
   */
  async exportarCsv(filtros: FiltrosReserva = {}): Promise<void> {
    try {
      const response = await apiClient.get(EXPORT_BASE, {
        params: {
          ...filtros,
          formato: 'csv',
        },
        responseType: 'blob',
      });

      // Axios tipa el header como string | number | boolean | string[] |
      // AxiosHeaders: lo normalizamos antes de usarlo como MIME del blob.
      const contentTypeHeader = response.headers['content-type'];
      const contentType =
        typeof contentTypeHeader === 'string' && contentTypeHeader !== ''
          ? contentTypeHeader
          : 'text/csv; charset=UTF-8';

      const blob = new Blob([response.data], {
        type: contentType,
      });

      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');

      link.href = url;
      link.download =
        obtenerNombreArchivo(response.headers['content-disposition']) ??
        `reservas-${new Date().toISOString().slice(0, 10)}.csv`;

      document.body.appendChild(link);
      link.click();
      link.remove();

      window.URL.revokeObjectURL(url);
    } catch (error) {
      const mensaje = await leerMensajeError(error);
      throw new Error(mensaje);
    }
  },

  /**
   * Solo para admin_parametricas.
   * Si falla por permisos, la pantalla oculta el filtro de campo.
   */
  async listarCampos(): Promise<CampoOption[]> {
    const { data } = await apiClient.get<unknown>('/v1/campos-deportivos', {
      params: {
        per_page: 100,
      },
    });

    const raw = Array.isArray(data)
      ? data
      : typeof data === 'object' && data !== null
        ? ((data as { data?: unknown }).data ??
          (data as { data?: { data?: unknown } }).data?.data ??
          [])
        : [];

    const campos = Array.isArray(raw) ? raw : [];

    return campos
      .map((campo: any) => ({
        id: String(campo.id ?? ''),
        nombre: String(campo.nombre ?? 'Campo sin nombre'),
      }))
      .filter((campo) => campo.id)
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
  },
};
