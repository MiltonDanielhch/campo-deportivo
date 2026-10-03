/**
 * Tipos para campos deportivos públicos con integración SIREB.
 * Refleja el JSON devuelto por el backend en
 * GET /api/v1/public/campos y GET /api/v1/public/campos/{id}.
 */

export interface TarifaSireb {
  id?: string | null;
  tipo: string | null;
  etiqueta: string;
  precio: number | null;
  unidad_medida?: string | null;
  vigente_desde?: string | null;
}

export interface ServicioSireb {
  id: string;
  codigo: string;
  nombre: string;
  descripcion?: string | null;
  rubro?: string | null;
  estado?: string | null;
  modo_tarifa?: string | null;
  tarifario?: string | null;
  unidad_medida?: string | null;
  precio_min?: number | null;
  precio_max?: number | null;
  tarifas: TarifaSireb[];
  fuente: string;
}

export interface TarifaPublica {
  precio_por_hora: number;
  etiqueta?: string;
  tipo?: string;
  sireb_tarifa_id?: string | null;
  vigente_desde?: string | null;
}

export interface TarifasCampo {
  diurna?: TarifaPublica | null;
  nocturna?: TarifaPublica | null;
}

export interface HorarioAtencion {
  dia_semana: number;
  hora_apertura: string;
  hora_cierre: string;
}

export interface TipoCampo {
  id: string;
  nombre: string;
}

export interface CampoPublico {
  id: string;
  /** Nombre oficial de SIREB/Paitití. */
  nombre: string;
  /** Nombre interno que el GAD le dio al campo. */
  nombre_local?: string;
  tipo_campo?: TipoCampo | null;
  direccion?: string | null;
  imagen_url?: string | null;
  latitud?: number | null;
  longitud?: number | null;
  estado: string;
  hora_inicio_noche?: string | null;
  servicio_sireb_id?: string | null;
  /** Código oficial del servicio en SIREB (SEDEDE-CS1, 0005, …). */
  servicio_sireb_codigo?: string | null;
  horarios_atencion?: HorarioAtencion[];
  tarifas?: TarifasCampo;
  sireb?: ServicioSireb | null;
  reservable_online: boolean;
  mensaje_no_reservable?: string | null;
  fuente_precios?: string;
}

export interface CamposResponse {
  data: CampoPublico[];
  meta: {
    fuente_precios: string;
    sincronizado_en?: string | null;
    aviso?: string | null;
  };
}

export interface CampoDetalleResponse {
  data: CampoPublico;
  meta: {
    fuente_precios: string;
    sincronizado_en?: string | null;
    aviso?: string | null;
  };
}
