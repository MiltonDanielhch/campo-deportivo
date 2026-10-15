export interface PiscinaTurno {
  id: string;
  campo_id: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  cupo_total: number | null;
  estado: 'activo' | 'inactivo';
  observacion: string | null;
  perfiles: PiscinaTurnoPerfil[];
}

export interface PiscinaTurnoPerfil {
  id: string;
  turno_id: string;
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  cupo: number;
  reservado: number;
  tarifa_sireb_id: string | null;
  // El backend castea el decimal a string en el JSON ("15.00").
  precio_unitario: number | string;
  estado: 'activo' | 'inactivo';
}

export interface PiscinaTurnoRequest {
  campo_id: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  cupo_total: number | null;
  estado: 'activo' | 'inactivo';
  observacion: string | null;
  perfiles: {
    perfil: 'mayor_12' | 'menor_12' | 'institucion';
    cupo: number;
    precio_unitario: number;
    tarifa_sireb_id: string | null;
  }[];
}
