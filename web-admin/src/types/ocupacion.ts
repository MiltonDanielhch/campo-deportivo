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

export interface VerificarCodigoResponse {
  data: {
    codigo_reserva: string;
    campo_id: string;
    campo_nombre: string;
    fecha_reserva: string;
    hora_inicio: string;
    hora_fin: string;
    asistencia_marcada_en: string | null;
  };
}
