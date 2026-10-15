import { api } from '@/lib/api';
import type {
  PiscinaDisponibilidad,
  PiscinaReservaRequest,
  PiscinaReservaResponse,
} from '@/types/piscina';

export const piscinaService = {
  /**
   * Obtiene turnos disponibles para un campo piscina en una fecha.
   */
  async obtenerTurnos(campoId: string, fecha: string): Promise<PiscinaDisponibilidad> {
    const { data } = await api.get<{ data: PiscinaDisponibilidad }>(
      `/public/piscina/campos/${campoId}/turnos`,
      { params: { fecha } }
    );
    return data.data;
  },

  /**
   * Crea una nueva reserva de piscina.
   */
  async crearReserva(datos: PiscinaReservaRequest): Promise<PiscinaReservaResponse> {
    const { data } = await api.post<{ data: PiscinaReservaResponse; message: string }>(
      '/public/piscina/reservas',
      datos
    );
    return data.data;
  },
};
