export interface ReservaConfirmada {
  codigo_reserva: string;
  campo_nombre: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  confirmado_en?: string | null;
}

export interface EstadoSolicitudPublico {
  codigo_seguimiento: string;
  estado: string;
  monto_total: number;
  expira_en: string | null;
  reservas?: ReservaConfirmada[];
}
