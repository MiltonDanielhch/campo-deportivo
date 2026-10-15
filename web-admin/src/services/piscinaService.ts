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
};
