export type EstadoReserva =
  | 'pendiente'
  | 'confirmada'
  | 'expirada'
  | 'cancelada'
  | 'rechazada';

export interface CampoOption {
  id: string;
  nombre: string;
}

export interface ReservaAnidada {
  id: string;
  codigo_reserva: string;
  confirmado_en?: string | null;
  asistencia_marcada_en?: string | null;
}

export interface DetalleSolicitudAdmin {
  id: string;
  campo_id: string;
  campo_nombre: string | null;
  fecha_reserva: string | null;
  hora_inicio: string;
  hora_fin: string;
  tarifa_aplicada: number;
  reserva: ReservaAnidada | null;
}

export interface SolicitudReservaAdmin {
  id: string;
  codigo_seguimiento: string;
  estado: EstadoReserva;
  monto_total: number;
  monto_confirmado: number | null;
  nombre_pagador: string | null;
  telefono_pagador: string | null;
  ci_nit_pagador: string | null;
  referencia_recaudaciones: string | null;
  motivo_rechazo: string | null;
  creado_en: string | null;
  expira_en: string | null;
  confirmado_en?: string | null;
  detalles?: DetalleSolicitudAdmin[];
}

export interface EntradaAuditoria {
  id: string;
  tabla: string;
  registro_id: string;
  accion: string;
  usuario_id: string | null;
  usuario_nombre: string | null;
  fecha: string | null;
  antes: unknown;
  despues: unknown;
}

export interface EstadoSireb {
  estado: string;
  pagado: boolean;
  monto?: number;
  codigo_publico?: string;
  [key: string]: unknown;
}

export interface DetalleSolicitudResponse {
  solicitud: SolicitudReservaAdmin;
  auditoria: EntradaAuditoria[];
  estado_sireb: EstadoSireb | null;
  puede_anularse: boolean;
}

export interface StatsReserva {
  total: number;
  pendientes: number;
  confirmadas: number;
  expiradas: number;
  canceladas: number;
  rechazadas: number;
  monto_confirmado: number;
}

export interface MetaListado {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  stats: StatsReserva;
}

export interface ListadoSolicitudesResponse {
  data: SolicitudReservaAdmin[];
  links?: {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
  };
  meta: MetaListado;
}

export interface FiltrosReserva {
  estado?: EstadoReserva | string;
  desde?: string;
  hasta?: string;
  campo_id?: string;
  funcionario_control_id?: string;
  buscar?: string;
  page?: number;
  per_page?: number;
}

export interface AsistenciaReservaResponse {
  id: string;
  codigo_reserva: string;
  solicitud_reserva_id?: string;
  campo_id?: string;
  fecha_reserva?: string | null;
  hora_inicio?: string | null;
  hora_fin?: string | null;
  asistencia_marcada_en: string | null;
  asistencia_marcada_por: string | null;
}
