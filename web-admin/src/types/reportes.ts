export interface IngresoPorCampo {
  campo_id: string;
  campo_nombre: string;
  campo_codigo: string;
  total_ingresos: number;
  total_reservas: number;
}

export interface IngresosResponse {
  data: IngresoPorCampo[];
  meta: {
    desde: string;
    hasta: string;
    total_general: number;
    nota: string;
  };
}

export interface HoraPico {
  hora: number;
  total_reservas: number;
}

export interface HorasPicoResponse {
  data: HoraPico[];
  meta: {
    desde: string;
    hasta: string;
    hora_pico: number | null;
    total_reservas: number;
    nota: string;
  };
}

export interface ClienteFrecuente {
  clave_agrupacion: string;
  nombre_mas_reciente: string;
  total_reservas: number;
  monto_total_gastado: number;
}

export interface ClientesFrecuentesResponse {
  data: ClienteFrecuente[];
  meta: {
    desde: string;
    hasta: string;
    total_clientes: number;
    nota: string;
  };
}
