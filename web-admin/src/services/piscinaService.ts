import apiClient from '@/services/apiClient';
import type { PiscinaTurno, PiscinaTurnoRequest } from '@/types/piscina';
import type {
  FiltrosPiscinaReserva,
  ListadoPiscinaResponse,
  PiscinaReservaAdmin,
} from '@/types/reservas';

export const piscinaService = {
  async obtenerTurnos(campoId: string, params?: { fecha_desde?: string; fecha_hasta?: string }): Promise<PiscinaTurno[]> {
    const { data } = await apiClient.get<{ data: PiscinaTurno[] }>(
      `/v1/admin/piscina/campos/${campoId}/turnos`,
      { params }
    );
    return data.data;
  },

  async crearTurno(datos: PiscinaTurnoRequest): Promise<PiscinaTurno> {
    const { data } = await apiClient.post<{ data: PiscinaTurno }>('/v1/admin/piscina/turnos', datos);
    return data.data;
  },

  async actualizarTurno(turnoId: string, datos: Partial<PiscinaTurnoRequest>): Promise<PiscinaTurno> {
    const { data } = await apiClient.patch<{ data: PiscinaTurno }>(`/v1/admin/piscina/turnos/${turnoId}`, datos);
    return data.data;
  },

  async eliminarTurno(turnoId: string): Promise<void> {
    await apiClient.delete(`/v1/admin/piscina/turnos/${turnoId}`);
  },

  /** Lista reservas de piscina para el panel (con filtros, paginación y stats). */
  async listarReservas(params?: FiltrosPiscinaReserva): Promise<ListadoPiscinaResponse> {
    const { data } = await apiClient.get<ListadoPiscinaResponse>('/v1/admin/piscina/reservas', {
      params,
    });
    return data;
  },

  /** Detalle de una reserva de piscina (incluye turnos/perfiles). */
  async obtenerReserva(id: string): Promise<PiscinaReservaAdmin> {
    const { data } = await apiClient.get<{ data: PiscinaReservaAdmin }>(
      `/v1/admin/piscina/reservas/${id}`,
    );
    return data.data;
  },

  /** Turnos disponibles para ventanilla (endpoint público, sin token). */
  async turnosDisponibles(campoId: string, fecha: string): Promise<unknown> {
    const { data } = await apiClient.get(
      `/v1/public/piscina/campos/${campoId}/turnos`,
      { params: { fecha } },
    );
    return data.data ?? data;
  },

  /** Creación desde ventanilla (admin). Devuelve código + QR. */
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
};
