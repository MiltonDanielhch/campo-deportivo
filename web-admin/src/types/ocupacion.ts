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
  /** Solo piscina: perfil del turno. */
  perfil?: 'mayor_12' | 'menor_12' | 'institucion' | string;
  /** Solo piscina: cantidad de personas del turno. */
  cantidad?: number;
}

/** Titular de la solicitud — solo por el canal interno protegido. */
export interface TitularVerificado {
  nombre: string | null;
  ci_nit: string | null;
  telefono: string | null;
}

export type TipoVerificacion = 'campo' | 'piscina';

export interface VerificarCodigoResponse {
  data: {
    tipo?: TipoVerificacion;
    codigo_seguimiento: string;
    estado_solicitud: string;
    titular: TitularVerificado;
    campo_id: string;
    campo_nombre: string;
    fecha_reserva: string;
    franjas: FranjaVerificada[];
  };
}

// ─── Grilla de turnos de piscina (control en sitio) ─────────────────────

export interface PiscinaTurnoPerfilOcupacion {
  perfil: string;
  etiqueta: string;
  cupo: number;
  reservado: number;
  disponible: number;
}

export interface PiscinaTurnoOcupacion {
  turno_id: string;
  hora_inicio: string;
  hora_fin: string;
  cupo_total: number | null;
  perfiles: PiscinaTurnoPerfilOcupacion[];
}

export interface CampoPiscinaOcupacion {
  campo_id: string;
  campo_nombre: string;
  fecha: string;
  turnos: PiscinaTurnoOcupacion[];
}

export interface PiscinaTurnosResponse {
  data: CampoPiscinaOcupacion[];
  meta: {
    fecha: string;
    total_campos: number;
  };
}
