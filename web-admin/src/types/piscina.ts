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
  // [B99] el request de creación/edición de turno NO manda precio ni tarifa_sireb_id:
  // el backend los resuelve vía PiscinaTarifaResolver (SIREB es la fuente de verdad).
  perfiles: {
    perfil: 'mayor_12' | 'menor_12' | 'institucion';
    cupo: number;
  }[];
}

// [B99-precio] respuesta de GET /campos/{campoId}/tarifas-perfil. precio_unitario null =
// el perfil no tiene tarifa SIREB vigente (el front avisa; el store falla 422 al guardar).
export interface PiscinaTarifaPerfil {
  perfil: 'mayor_12' | 'menor_12' | 'institucion';
  precio_unitario: number | null;
  tarifa_sireb_id: string | null;
}
