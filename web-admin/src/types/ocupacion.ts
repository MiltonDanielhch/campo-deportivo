export type EstadoFranja = 'libre' | 'pendiente' | 'ocupada';

export interface Franja {
  hora_inicio: string;
  hora_fin: string;
  estado: EstadoFranja;
  codigo_reserva: string | null;
  asistencia_marcada_en: string | null;
}

export interface CampoOcupacion {
  campo_id: string;
  campo_nombre: string;
  abierto: boolean;
  fecha: string;
  franjas: Franja[];
}

export interface MisCamposResponse {
  data: CampoOcupacion[];
  meta: {
    fecha: string;
    total_campos: number;
  };
}

/** Franja devuelta por el verificador (1 por codigo_reserva). */
export interface FranjaVerificada {
  id: string;
  codigo_reserva: string;
  campo_nombre: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  asistencia_marcada_en: string | null;
}

/** Titular de la solicitud — solo por el canal interno protegido. */
export interface TitularVerificado {
  nombre: string | null;
  ci_nit: string | null;
  telefono: string | null;
}

export interface VerificarCodigoResponse {
  data: {
    codigo_seguimiento: string;
    estado_solicitud: string;
    titular: TitularVerificado;
    campo_id: string;
    campo_nombre: string;
    fecha_reserva: string;
    franjas: FranjaVerificada[];
  };
}
