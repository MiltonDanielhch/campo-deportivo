export interface ResumenOcupacion {
  franjas_totales: number;
  franjas_ocupadas: number;
  franjas_pendientes: number;
  franjas_libres: number;
  porcentaje_ocupacion: number;
  ocupado_ahora: boolean;
}

export interface VinculacionSireb {
  vinculado: boolean;
  servicio_sireb_id: string | null;
  servicio_sireb_codigo: string | null;
}

export interface CampoMapaGlobal {
  campo_id: string;
  campo_nombre: string;
  abierto: boolean;
  fecha: string;
  franjas: any[]; // No necesitamos detalle de franjas para el mapa
  latitud: number;
  longitud: number;
  direccion: string;
  estado_operativo: 'activo' | 'mantenimiento';
  vinculacion_sireb: VinculacionSireb;
  resumen_ocupacion: ResumenOcupacion;
}

export interface MapaGlobalResponse {
  data: CampoMapaGlobal[];
  meta: {
    fecha: string;
    total_campos: number;
    activos: number;
    en_mantenimiento: number;
    vinculados_sireb: number;
  };
}
