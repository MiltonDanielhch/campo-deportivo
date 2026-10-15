export interface PiscinaTurno {
  id: string;
  hora_inicio: string; // HH:MM
  hora_fin: string; // HH:MM
  perfiles: PiscinaPerfil[];
}

export interface PiscinaPerfil {
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  etiqueta: string;
  precio: number;
  cupo: number;
  disponible: number;
  tarifa_sireb_id: string;
  horario: 'diurno' | 'nocturno';
}

export interface PiscinaDisponibilidad {
  campo_id: string;
  fecha: string;
  abierto: boolean;
  turnos: PiscinaTurno[];
}

export interface PiscinaReservaItem {
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  cantidad: number;
}

export interface PiscinaReservaRequest {
  fecha: string;
  turno_id: string;
  items: PiscinaReservaItem[];
  nombre_pagador: string;
  telefono_pagador: string;
  ci_nit_pagador: string;
}

export interface PiscinaReservaResponse {
  reserva_id: string;
  codigo_seguimiento: string;
  monto_total: number;
  expira_en: string;
}

export interface PiscinaReserva {
  id: string;
  codigo_seguimiento: string;
  campo_id: string;
  estado: 'pendiente' | 'confirmada' | 'expirada' | 'cancelada' | 'rechazada';
  monto_total: number;
  monto_confirmado: number | null;
  nombre_pagador: string;
  telefono_pagador: string;
  ci_nit_pagador: string;
  expira_en: string | null;
  referencia_recaudaciones: string | null;
  liquidacion_id: string | null;
  creado_en: string;
  actualizado_en: string;
  campo?: {
    id: string;
    nombre: string;
  };
  detalles?: PiscinaReservaDetalle[];
}

export interface PiscinaReservaDetalle {
  id: string;
  reserva_id: string;
  turno_id: string;
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  cantidad: number;
  precio_unitario: number;
  tarifa_sireb_id: string | null;
  servicio_sireb_id: string | null;
  etiqueta_sireb: string | null;
  hora_inicio: string;
  hora_fin: string;
  fecha: string;
  creado_en: string;
}
