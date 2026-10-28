import apiClient from '@/services/apiClient';
import type { PiscinaTurno, PiscinaTurnoRequest, PiscinaTarifaPerfil } from '@/types/piscina';
import type {
  DetallePiscinaResponse,
  EstadoSireb,
  FiltrosPiscinaReserva,
  ListadoPiscinaResponse,
} from '@/types/reservas';

export interface LoteResultado {
  creados: number;
  omitidas: string[];
  procesadas: string[];
}

export const piscinaService = {
  async obtenerTurnos(campoId: string, params?: { fecha_desde?: string; fecha_hasta?: string }): Promise<PiscinaTurno[]> {
    const { data } = await apiClient.get<{ data: PiscinaTurno[] }>(
      `/v1/admin/piscina/campos/${campoId}/turnos`,
      { params }
    );
    return data.data;
  },

  /** [B99-precio] precio SIREB resuelto por perfil para una hora (fuente única = resolver). */
  async tarifasPerfil(campoId: string, horaInicio: string): Promise<PiscinaTarifaPerfil[]> {
    const { data } = await apiClient.get<{ data: PiscinaTarifaPerfil[] }>(
      `/v1/admin/piscina/campos/${campoId}/tarifas-perfil`,
      { params: { hora_inicio: horaInicio } },
    );
    return data.data;
  },

  async crearTurno(datos: PiscinaTurnoRequest): Promise<PiscinaTurno> {
    const { data } = await apiClient.post<{ data: PiscinaTurno }>('/v1/admin/piscina/turnos', datos);
    return data.data;
  },

  /** ⟦LOTE⟧ clona los turnos activos de fecha_origen a [desde..hasta]. */
  async crearTurnosLote(payload: {
    campo_id: string;
    fecha_origen: string;
    desde: string;
    hasta: string;
  }): Promise<LoteResultado> {
    const { data } = await apiClient.post<{ message: string; data: LoteResultado }>(
      '/v1/admin/piscina/turnos/lote',
      payload,
    );
    return data.data;
  },

  async actualizarTurno(turnoId: string, datos: Partial<PiscinaTurnoRequest>): Promise<PiscinaTurno> {
    const { data } = await apiClient.patch<{ data: PiscinaTurno }>(`/v1/admin/piscina/turnos/${turnoId}`, datos);
    return data.data;
  },

  async eliminarTurno(turnoId: string): Promise<void> {
    await apiClient.delete(`/v1/admin/piscina/turnos/${turnoId}`);
  },

  async listarReservas(params?: FiltrosPiscinaReserva): Promise<ListadoPiscinaResponse> {
    const { data } = await apiClient.get<ListadoPiscinaResponse>('/v1/admin/piscina/reservas', {
      params,
    });
    return data;
  },

  async obtenerReserva(id: string): Promise<DetallePiscinaResponse> {
    const { data } = await apiClient.get<{ data: DetallePiscinaResponse }>(
      `/v1/admin/piscina/reservas/${id}`,
    );
    return data.data;
  },

  async refrescarSireb(id: string): Promise<{
    estado_sireb: EstadoSireb | null;
    puede_anularse: boolean;
  }> {
    const { data } = await apiClient.get<{
      data: {
        estado_sireb: EstadoSireb | null;
        puede_anularse: boolean;
      };
    }>(`/v1/admin/piscina/reservas/${id}/refrescar-sireb`);

    return data.data;
  },

  async turnosDisponibles(campoId: string, fecha: string): Promise<unknown> {
    const { data } = await apiClient.get(
      `/v1/public/piscina/campos/${campoId}/turnos`,
      { params: { fecha } },
    );
    return data.data ?? data;
  },

  async crearDesdeVentanilla(payload: {
    fecha: string;
    turno_id: string;
    items: Array<{ perfil: string; cantidad: number }>;
    nombre_pagador: string;
    ci_nit_pagador: string;
    telefono_pagador?: string;
  }) {
    const { data } = await apiClient.post<{
      message: string;
      data: {
        reserva_id: string;
        codigo_seguimiento: string;
        monto_total: number;
        expira_en: string;
        referencia_recaudaciones: string | null;
        qr_string: string | null;
      };
    }>('/v1/admin/piscina/reservas', payload);
    return data;
  },

  exportarCsv: async (filtros: FiltrosPiscinaReserva): Promise<void> => {
    const params = new URLSearchParams();
    if (filtros.estado && filtros.estado !== 'todos') params.append('estado', filtros.estado);
    if (filtros.buscar) params.append('buscar', filtros.buscar);
    if (filtros.fecha_desde) params.append('fecha_desde', filtros.fecha_desde);
    if (filtros.fecha_hasta) params.append('fecha_hasta', filtros.fecha_hasta);
    if (filtros.campo_id && filtros.campo_id !== 'todos') params.append('campo_id', filtros.campo_id);

    const { data } = await apiClient.get<Blob>(
      `/v1/admin/piscina/reservas/exportar-csv?${params}`,
      { responseType: 'blob' },
    );

    const url = window.URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = `reservas-piscina-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  },
};
