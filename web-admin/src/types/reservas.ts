export type EstadoReserva = 'pendiente' | 'confirmada' | 'expirada' | 'cancelada' | 'rechazada';

export interface DetalleReserva {
  id: string;
  campo_id: string;
  fecha_reserva: string;
  hora_inicio: string;
  hora_fin: string;
  tarifa_aplicada: number;
  campo?: {
    id: string;
    nombre: string;
    codigo: string;
  };
}

export interface ReservaConfirmada {
  id: string;
  codigo_reserva: string;
  campo_id: string;
  fecha_reserva: string;
  hora_inicio: string;
  hora_fin: string;
  confirmado_en: string;
  campo?: {
    nombre: string;
  };
}

export interface SolicitudReservaAdmin {
  id: string;
  codigo_seguimiento: string;
  estado: EstadoReserva;
  monto_total: number;
  monto_confirmado: number | null;
  nombre_pagador: string;
  telefono_pagador: string;
  ci_nit_pagador: string | null;
  referencia_recaudaciones: string | null;
  liquidacion_id: string | null;
  datos_cobro_pendiente: any | null;
  motivo_rechazo: string | null;
  creado_en: string;
  expira_en: string | null;
  detalles?: DetalleReserva[];
  reservas?: ReservaConfirmada[];
}

export interface EstadoSireb {
  estado: string;
  pagado: boolean;
  monto?: number;
  codigo_publico?: string;
}

export interface DetalleSolicitudResponse {
  solicitud: SolicitudReservaAdmin;
  estado_sireb: EstadoSireb | null;
  puede_anularse: boolean;
}

export interface ListadoSolicitudesResponse {
  data: SolicitudReservaAdmin[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
